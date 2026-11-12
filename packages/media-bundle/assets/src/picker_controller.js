import { Controller } from '@hotwired/stimulus';

/**
 * The media field of a form: opens the explorer's picker in a dialog, follows its collections and pages inside it, and
 * puts the chosen medium's identifier in the hidden input, with its name and thumbnail beside the button.
 */
export default class extends Controller {
    static targets = ['input', 'choice', 'dialog', 'body'];
    static values = { url: String };

    async open() {
        await this.load(this.urlValue);
        this.dialogTarget.showModal();
    }

    async follow(event) {
        const link = event.target.closest('a[href]');

        if (link && this.bodyTarget.contains(link)) {
            event.preventDefault();
            await this.load(link.href);
        }
    }

    choose(event) {
        const { mediaId, mediaLabel, mediaPreview } = event.currentTarget.dataset;
        this.inputTarget.value = mediaId;
        this.choiceTarget.replaceChildren();

        if (mediaPreview) {
            const image = document.createElement('img');
            image.src = mediaPreview;
            image.alt = '';
            image.className = 'phil-media-thumb';
            this.choiceTarget.append(image, ' ');
        }

        this.choiceTarget.append(mediaLabel);
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.dialogTarget.close();
    }

    clear() {
        this.inputTarget.value = '';
        this.choiceTarget.textContent = 'No file chosen';
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async load(url) {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        this.bodyTarget.innerHTML = await response.text();
    }
}
