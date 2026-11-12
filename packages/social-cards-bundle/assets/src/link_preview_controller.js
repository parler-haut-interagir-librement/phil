/*
 * The Stimulus controller of phil/social-cards-bundle that shows, below a text a member writes, the preview of the
 * first link of that text, read from the bundle's `/social-cards/link-preview` endpoint, and lets the author remove it:
 *
 *     <div data-controller="phil--social-cards-bundle--link-preview"
 *          data-phil--social-cards-bundle--link-preview-endpoint-value="{{ path('phil_social_cards_link_preview') }}">
 *         <textarea name="message" data-phil--social-cards-bundle--link-preview-target="source"
 *                   data-action="input->phil--social-cards-bundle--link-preview#scan paste->phil--social-cards-bundle--link-preview#scan"></textarea>
 *         <input type="hidden" name="link" data-phil--social-cards-bundle--link-preview-target="field">
 *         <div data-phil--social-cards-bundle--link-preview-target="preview" hidden></div>
 *     </div>
 *
 * The text is scanned `delay` milliseconds after the author stops typing. The link shown is written in the `field`
 * target, so that the server keeps its preview with the message; a link removed stays removed while it is in the text.
 * The card is built from text nodes, never from HTML, its image loaded lazily and without a referrer. It dispatches
 * `shown` with the preview, and `removed` with the address.
 */
import { Controller } from '@hotwired/stimulus';

const MARKS = ['.', ',', ';', ':', '!', '?', "'", '»', '”', '’', '›', '…', ')', ']'];

/**
 * The first link of a text, as phil/social-cards finds it: an http or https address up to the next blank, quote or
 * angle bracket, without the punctuation that follows it nor a closing parenthesis or bracket it did not open.
 */
export function firstLink(text) {
    // biome-ignore lint/suspicious/noControlCharactersInRegex: a control character ends a link, as in phil/social-cards.
    const match = /(?<![\w/.@-])https?:\/\/[^\s<>"`{}|\\^\u0000-\u001F\u007F]+/iu.exec(String(text).slice(0, 65536));

    if (!match) {
        return null;
    }

    let link = match[0];
    const unopened = {
        ')': link.split(')').length - link.split('(').length,
        ']': link.split(']').length - link.split('[').length,
    };

    for (
        let mark = MARKS.find((candidate) => link.endsWith(candidate));
        mark;
        mark = MARKS.find((candidate) => link.endsWith(candidate))
    ) {
        if (mark in unopened) {
            if (unopened[mark] < 1) {
                break;
            }

            unopened[mark] -= 1;
        }

        link = link.slice(0, -mark.length);
    }

    return /^https?:\/\/[^/?#.\s]/i.test(link) && link.length <= 2048 ? link : null;
}

export default class extends Controller {
    static targets = ['source', 'field', 'preview'];

    static values = {
        endpoint: String,
        delay: { type: Number, default: 400 },
        removeLabel: { type: String, default: 'Remove the preview' },
    };

    connect() {
        this.current = null;
        this.dismissed = new Set();
        this.request = 0;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    scan() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.update(), this.delayValue);
    }

    async update() {
        const link = firstLink(this.sourceTarget.value);

        if (link === this.current) {
            return;
        }

        if (link === null || this.dismissed.has(link)) {
            this.clear();

            return;
        }

        this.current = link;
        const request = ++this.request;
        let preview = null;

        try {
            const response = await fetch(`${this.endpointValue}?url=${encodeURIComponent(link)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            preview = response.status === 200 ? await response.json() : null;
        } catch {
            preview = null;
        }

        if (request !== this.request) {
            return;
        }

        if (preview && typeof preview.title === 'string') {
            this.show(link, preview);
        } else {
            this.clear(link);
        }
    }

    show(link, preview) {
        const document = this.element.ownerDocument;
        const card = document.createElement('div');
        card.className = 'phil-link-preview';

        if (typeof preview.image === 'string' && /^https?:\/\//i.test(preview.image)) {
            const image = document.createElement('img');
            image.className = 'phil-link-preview__image';
            image.src = preview.image;
            image.alt = typeof preview.image_alt === 'string' ? preview.image_alt : '';
            image.loading = 'lazy';
            image.referrerPolicy = 'no-referrer';
            card.append(image);
        }

        const body = document.createElement('span');
        body.className = 'phil-link-preview__body';

        for (const [key, part] of [
            ['site_name', 'site'],
            ['title', 'title'],
            ['description', 'description'],
        ]) {
            if (typeof preview[key] === 'string' && preview[key] !== '') {
                const span = document.createElement(part === 'title' ? 'strong' : 'span');
                span.className = `phil-link-preview__${part}`;
                span.textContent = preview[key];
                body.append(span);
            }
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'phil-link-preview__remove';
        remove.textContent = this.removeLabelValue;
        remove.addEventListener('click', () => this.remove(link));
        card.append(body, remove);

        this.previewTarget.replaceChildren(card);
        this.previewTarget.hidden = false;
        this.fieldTarget.value = link;
        this.dispatch('shown', { detail: { preview } });
    }

    remove(link) {
        this.dismissed.add(link);
        this.clear(link);
        this.dispatch('removed', { detail: { url: link } });
    }

    clear(link = null) {
        this.request += 1;
        this.current = link;
        this.previewTarget.replaceChildren();
        this.previewTarget.hidden = true;
        this.fieldTarget.value = '';
    }
}
