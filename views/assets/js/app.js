import Collapse from './App/Collapse.js'
import DateDistance from './App/DateDistance.js'
import PackageFilter from './App/PackageFilter.js'
import PackageFilterSettings from './App/PackageFilterSettings.js'
import ToggleFields from './App/ToggleFields.js'
import Settings from './App/Settings.js'
import CopySnippet from './App/CopySnippet.js'
import ColorTheme from './App/ColorTheme.js'

function updateTimeElements() {
    DateDistance.calculate('time')
}

document.addEventListener('DOMContentLoaded', function () {
    new Collapse()

    // Filter
    new PackageFilterSettings('[name="default-filter"]', '.filter-field', '.default-filter-field')
    new PackageFilter('input#search', '#package-list', '.card', '.filter-field')

    // Copy the "composer require" command of a release
    new CopySnippet('.copy-snippet')

    new ToggleFields('#toggle-field-all', '.toggle-field')
    new Settings('.settings', '.toggle-settings')
    new ColorTheme('[name="color-theme"]')
    updateTimeElements()
    window.setInterval(updateTimeElements, 5000)
})
