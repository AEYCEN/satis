/**
 * Copies the "composer require" command of a release to the clipboard
 * @class
 */
class CopySnippet {
    /**
     * Bootstraps the class
     * @param {string} buttons - Buttons that carry the snippet to copy in their data-snippet attribute
     */
    constructor(buttons) {
        this.buttons = Array.prototype.slice.call(document.querySelectorAll(buttons))
        this.feedbackTimeout = 2000
        this.buttons.forEach((button) => button.addEventListener('click', (event) => this.copy(event.currentTarget)))
    }

    /**
     * Copies the snippet of a button to the clipboard and shows feedback on the button
     * @param {HTMLButtonElement} button - The button that was clicked
     */
    copy(button) {
        const snippet = button.dataset.snippet

        // The Clipboard API is only available in secure contexts and may reject, e.g. when the document is not focused
        if ('clipboard' in navigator) {
            navigator.clipboard.writeText(snippet).then(
                () => this.showFeedback(button),
                () => this.copyWithExecCommand(button, snippet),
            )
            return
        }

        this.copyWithExecCommand(button, snippet)
    }

    /**
     * Fallback for insecure contexts and older browsers
     * @param {HTMLButtonElement} button - The button that was clicked
     * @param {string} snippet - The text to copy
     */
    copyWithExecCommand(button, snippet) {
        const textarea = document.createElement('textarea')
        textarea.value = snippet
        textarea.setAttribute('readonly', '')
        textarea.style.position = 'absolute'
        textarea.style.left = '-9999px'
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
        this.showFeedback(button)
    }

    /**
     * Marks the button as copied for a moment
     * @param {HTMLButtonElement} button - The button that was clicked
     */
    showFeedback(button) {
        button.classList.add('copied')
        window.clearTimeout(button.feedbackTimer)
        button.feedbackTimer = window.setTimeout(() => button.classList.remove('copied'), this.feedbackTimeout)
    }
}

export default CopySnippet
