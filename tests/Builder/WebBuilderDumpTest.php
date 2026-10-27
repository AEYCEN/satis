<?php

declare(strict_types=1);

/*
 * This file is part of composer/satis.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Composer\Test\Satis;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Satis\Builder\WebBuilder;
use Composer\Semver\Constraint\MatchAllConstraint;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @author James Hautot <james@rezo.net>
 */
class WebBuilderDumpTest extends TestCase
{
    protected RootPackage $rootPackage;

    protected CompletePackage $package;

    protected vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->rootPackage = new RootPackage('dummy root package', '0', '0');

        $this->package = new CompletePackage('vendor/name', '1.0.0.0', '1.0');

        $this->root = $this->setFileSystem();
    }

    protected function setFileSystem(): vfsStreamDirectory
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('build');
        vfsStreamWrapper::setRoot($root);

        return $root;
    }

    public function testNominalCase(): void
    {
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertMatchesRegularExpression('/<title>dummy root package<\/title>/', $html);
        self::assertMatchesRegularExpression('{<div id="[^"]+" class="card-header[^"]+">\s*<a href="#vendor/name" class="[^"]+">vendor/name</a>}si', $html);
        self::assertFalse((bool) preg_match('/<p class="abandoned">/', $html));
    }

    public function testAuthorsWithRole(): void
    {
        $this->package->setAuthors([
            ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'role' => 'Maintainer'],
            ['name' => 'John Doe'],
        ]);
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertMatchesRegularExpression('{<a class="[^"]*" href="mailto:jane@example.com"[^>]*>Jane Doe <span class="[^"]*">\(Maintainer\)</span></a>}', $html);
        self::assertMatchesRegularExpression('{<span class="[^"]*">John Doe</span>}', $html);
    }

    public function testRepositoryWithNoName(): void
    {
        $this->rootPackage = new RootPackage('__root__', '0', '0');
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertMatchesRegularExpression('/<title>A<\/title>/', $html);
    }

    public function testDependencies(): void
    {
        $link = new Link('dummytest', 'vendor/name', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');
        $this->package->setRequires([$link->getTarget() => $link]);
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertMatchesRegularExpression('/<a href="#dummytest">dummytest<\/a>/', $html);
    }

    public function testRequires(): void
    {
        $other = new CompletePackage('vendor/other', '2.0.0.0', '2.0');
        $this->package->setRequires([
            'acme/external' => new Link('vendor/name', 'acme/external', new MatchAllConstraint(), Link::TYPE_REQUIRE, '^1.0'),
            'vendor/other' => new Link('vendor/name', 'vendor/other', new MatchAllConstraint(), Link::TYPE_REQUIRE, '^2.0'),
            'ext-json' => new Link('vendor/name', 'ext-json', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*'),
            'php' => new Link('vendor/name', 'php', new MatchAllConstraint(), Link::TYPE_REQUIRE, '>=8.4'),
        ]);
        $this->package->setDevRequires([
            'phpunit/phpunit' => new Link('vendor/name', 'phpunit/phpunit', new MatchAllConstraint(), Link::TYPE_DEV_REQUIRE, '^11.0'),
        ]);
        $this->package->setSuggests(['acme/optional' => 'For optional things']);
        $this->package->setAutoload(['psr-4' => ['Vendor\\Name\\' => 'src/', '' => 'lib/']]);
        $this->package->setConflicts([
            'acme/legacy' => new Link('vendor/name', 'acme/legacy', new MatchAllConstraint(), Link::TYPE_CONFLICT, '<2.0'),
        ]);
        $this->package->setReplaces([
            'acme/old-name' => new Link('vendor/name', 'acme/old-name', new MatchAllConstraint(), Link::TYPE_REPLACE, 'self.version'),
        ]);
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package, $other]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        // The latest release is shown by default, its requirements are collapsed until clicked
        self::assertStringContainsString('<details class="release" name="release-vendor/name" open>', $html);
        // Only the version number of a release is searchable, not its requirements
        self::assertMatchesRegularExpression('{<summary class="filter-by [^"]*"[^>]*>1\.0</summary>}', $html);
        self::assertStringNotContainsString('<div class="field-releases filter-by', $html);
        self::assertMatchesRegularExpression('{<dt[^>]*>Requires</dt>\s*<dd[^>]*>\s*<details class="links">\s*<summary[^>]*>2 packages</summary>}', $html);
        self::assertMatchesRegularExpression('{<dt[^>]*>Requires \(dev\)</dt>\s*<dd[^>]*>\s*<details class="links">\s*<summary[^>]*>1 package</summary>}', $html);
        self::assertStringNotContainsString('<details class="links" open>', $html);
        // Packages of this repository are linked to their card and listed before other packages, which link to Packagist
        self::assertMatchesRegularExpression('{<a class="[^"]*" href="#vendor/other">vendor/other</a>\s*<span class="[^"]*font-monospace[^"]*">\^2\.0</span>\s*</li>\s*<li[^>]*>\s*<a class="[^"]*" href="https://packagist.org/packages/acme/external" target="_blank" rel="noopener">acme/external</a>\s*<span class="[^"]*font-monospace[^"]*">\^1\.0</span>}', $html);
        // The PHP requirement is shown next to the release, other platform requirements are not shown at all
        self::assertMatchesRegularExpression('{<span class="[^"]*text-bg-primary[^"]*">PHP &gt;=8\.4</span>}', $html);
        self::assertStringNotContainsString('packages/php"', $html);
        self::assertStringNotContainsString('ext-json', $html);
        self::assertMatchesRegularExpression('{<a class="[^"]*" href="https://packagist.org/packages/phpunit/phpunit" target="_blank" rel="noopener">phpunit/phpunit</a>\s*<span class="[^"]*font-monospace[^"]*">\^11\.0</span>}', $html);
        self::assertMatchesRegularExpression('{<dt[^>]*>Suggests</dt>\s*<dd[^>]*>\s*<details class="links">\s*<summary[^>]*>1 package</summary>.*?<a href="https://packagist.org/packages/acme/optional"[^>]*>acme/optional</a>\s*<span class="[^"]*">For optional things</span>}s', $html);
        // Conflicts and replaces are listed like requirements
        self::assertMatchesRegularExpression('{<dt[^>]*>Conflicts</dt>\s*<dd[^>]*>\s*<details class="links">\s*<summary[^>]*>1 package</summary>.*?acme/legacy</a>\s*<span class="[^"]*font-monospace[^"]*">&lt;2\.0</span>}s', $html);
        self::assertMatchesRegularExpression('{<dt[^>]*>Replaces</dt>\s*<dd[^>]*>\s*<details class="links">\s*<summary[^>]*>1 package</summary>.*?acme/old-name</a>\s*<span class="[^"]*font-monospace[^"]*">self\.version</span>}s', $html);
        // One namespace row per PSR-4 rule, a rule without a prefix is shown as "none"
        self::assertMatchesRegularExpression('{<dt[^>]*>Namespace</dt>\s*<dd[^>]*>\s*<span class="font-monospace">Vendor\\\\Name\\\\</span>\s*</dd>\s*<dt[^>]*>Namespace</dt>\s*<dd[^>]*>\s*<span class="[^"]*"[^>]*>none</span>}', $html);
        // The install command is shown and can be copied to the clipboard
        self::assertMatchesRegularExpression('{<span class="[^"]*"[^>]*>composer require vendor/name:\^1\.0</span>\s*<button type="button" class="copy-snippet[^"]*" data-snippet="composer require vendor/name:\^1\.0"}', $html);
    }

    public function testReleaseLinksToDistArchiveOrSource(): void
    {
        $this->package->setDistType('zip');
        $this->package->setDistUrl('https://example.org/dist/vendor-name-1.0.zip');
        $this->package->setDistReference('abc123');
        $this->package->setSourceUrl('https://github.com/vendor/name.git');
        $this->package->setSourceReference('def456');
        $sourceOnly = new CompletePackage('vendor/source-only', '2.0.0.0', '2.0');
        $sourceOnly->setSourceUrl('https://github.com/vendor/source-only.git');
        $sourceOnly->setSourceReference('789abc');
        $noLinks = new CompletePackage('vendor/no-links', '3.0.0.0', '3.0');
        $noLinks->setSourceUrl('git@github.com:vendor/no-links.git');
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package, $sourceOnly, $noLinks]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        // The dist archive wins over the source repository, like the version badges did before
        self::assertMatchesRegularExpression('{<summary class="[^"]*" title="dist-reference: abc123">1\.0</summary>}', $html);
        self::assertMatchesRegularExpression('{<a class="[^"]*" href="https://example.org/dist/vendor-name-1.0.zip" title="dist-reference: abc123">Download</a>}', $html);
        self::assertStringNotContainsString('title="source-reference: def456">Source</a>', $html);
        // Without a dist archive the release links to the source repository
        self::assertMatchesRegularExpression('{<summary class="[^"]*" title="source-reference: 789abc">2\.0</summary>}', $html);
        self::assertMatchesRegularExpression('{<a class="[^"]*" href="https://github.com/vendor/source-only.git" target="_blank" rel="noopener" title="source-reference: 789abc">Source</a>}', $html);
        // A source URL that is not reachable over HTTP gets no link at all
        self::assertSame(1, preg_match_all('/>Download<\/a>/', $html));
        self::assertSame(1, preg_match_all('/>Source<\/a>/', $html));
    }

    public function testOptionalLinkTypesAreOnlyShownWhenPresent(): void
    {
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertStringContainsString('>Requires</dt>', $html);
        self::assertStringNotContainsString('>Requires (dev)</dt>', $html);
        self::assertStringNotContainsString('>Conflicts</dt>', $html);
        self::assertStringNotContainsString('>Replaces</dt>', $html);
        self::assertStringNotContainsString('>Suggests</dt>', $html);
    }

    public function testRepositoryLink(): void
    {
        $this->package->setSourceUrl('git@github.com:vendor/name.git');
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        // The package name stays a permalink to the card, the web view of the source repository is linked separately
        self::assertMatchesRegularExpression('{<a href="#vendor/name" class="[^"]+">vendor/name</a>}', $html);
        self::assertMatchesRegularExpression('{<a class="[^"]*" href="https://github.com/vendor/name" target="_blank" rel="noopener" title="Open repository">Repository</a>}', $html);
    }

    public function testLongRequiresListsAreShownCompletely(): void
    {
        $requires = [];
        for ($i = 1; $i <= 12; ++$i) {
            $name = sprintf('acme/package-%02d', $i);
            $requires[$name] = new Link('vendor/name', $name, new MatchAllConstraint(), Link::TYPE_REQUIRE, '^1.0');
        }
        $this->package->setRequires($requires);
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertStringContainsString('12 packages</summary>', $html);
        self::assertSame(12, preg_match_all('{<a class="[^"]*" href="https://packagist.org/packages/acme/package-\d+"}', $html));
        self::assertSame(1, preg_match_all('/<details class="links">/', $html));
    }

    public function testReleasesBeyondTheFirstRowAreCollapsed(): void
    {
        $versions = [];
        for ($minor = 0; $minor < 12; ++$minor) {
            $versions[] = new CompletePackage('vendor/name', '1.' . $minor . '.0.0', '1.' . $minor . '.0');
        }
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $webBuilder->dump($versions);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertSame(12, preg_match_all('/<details class="release" name="release-vendor\/name"/', $html));
        self::assertStringContainsString('<span class="more">+2 more</span>', $html);
        // The ten newest releases list their requirements
        self::assertSame(10, preg_match_all('{>Requires</dt>\s*<dd[^>]*>\s*<span class="[^"]*">none</span>}', $html));
        // The two oldest releases are inside the collapsed group; they keep the install command but do not list requirements
        $collapsed = substr($html, (int) strpos($html, '<details class="release-more">'));
        self::assertSame(2, preg_match_all('/<details class="release"/', $collapsed));
        self::assertStringContainsString('composer require vendor/name:^1.1.0', $collapsed);
        self::assertStringContainsString('composer require vendor/name:^1.0.0', $collapsed);
        self::assertSame(2, preg_match_all('/only listed for the 10 newest releases/', $collapsed));
        self::assertSame(0, preg_match_all('/<details class="links">/', $collapsed));
    }

    /**
     * @return array<string, mixed>
     */
    public static function dataAbandoned(): array
    {
        $data = [];

        $data['Abandoned not replaced'] = [
            true,
            '/No replacement was suggested/',
        ];

        $data['Abandoned and replaced'] = [
            'othervendor/othername',
            '/Use othervendor\/othername instead/',
        ];

        return $data;
    }

    /**
     * @param bool|string $abandoned
     */
    #[DataProvider('dataAbandoned')]
    public function testAbandoned($abandoned, string $expected): void
    {
        $webBuilder = new WebBuilder(new NullOutput(), vfsStream::url('build'), [], false);
        $webBuilder->setRootPackage($this->rootPackage);
        $this->package->setAbandoned($abandoned);
        $webBuilder->dump([$this->package]);

        /** @var vfsStreamFile $file */
        $file = $this->root->getChild('build/index.html');
        $html = $file->getContent();

        self::assertMatchesRegularExpression('/Package is abandoned, you should avoid using it/', $html);
        self::assertMatchesRegularExpression($expected, $html);
    }
}
