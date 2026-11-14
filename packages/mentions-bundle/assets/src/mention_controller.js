// An `@` autocomplete on a plain text field — a comment, a Markdown proposal —: as the writer types a handle after `@`,
// a list of members from the block editor's member search opens under the field; the arrows move in it, Enter or Tab
// writes the chosen handle, Escape closes it, and a polite live region says how many members were found. The field
// stays a plain text: the server finds the handles again.
//
//     <textarea name="body"
//               data-controller="phil--mentions-bundle--mention"
//               data-phil--mentions-bundle--mention-url-value="{{ path('phil_block_editor_mentions') }}"></textarea>

import { Controller } from '@hotwired/stimulus';

import { suggestions } from './lib/api.js';
import { activeQuery, insertMention } from './lib/query.js';

let lists = 0;

export default class extends Controller {
    static values = {
        url: String,
        delay: { type: Number, default: 150 },
        empty: { type: String, default: 'No member found' },
        found: { type: String, default: '{count} members found' },
    };

    connect() {
        const doc = this.element.ownerDocument;
        lists += 1;
        this.list = doc.createElement('ul');
        this.list.id = `phil-mentions-${lists}`;
        this.list.className = 'phil-mentions-menu';
        this.list.setAttribute('role', 'listbox');
        this.list.hidden = true;
        this.element.insertAdjacentElement('afterend', this.list);
        this.status = doc.createElement('span');
        this.status.className = 'phil-mentions-status';
        this.status.setAttribute('role', 'status');
        this.list.insertAdjacentElement('afterend', this.status);
        this.element.setAttribute('role', 'combobox');
        this.element.setAttribute('aria-autocomplete', 'list');
        this.element.setAttribute('aria-controls', this.list.id);
        this.element.setAttribute('aria-expanded', 'false');
        this.items = [];
        this.active = -1;
        this.range = null;
        this.timer = null;
        this.request = null;
        this.handlers = {
            input: () => this.typed(),
            keydown: (event) => this.key(event),
            blur: () => this.close(),
        };
        this.list.addEventListener('mousedown', (event) => this.picked(event));
        Object.entries(this.handlers).forEach(([type, handler]) => {
            this.element.addEventListener(type, handler);
        });
    }

    disconnect() {
        Object.entries(this.handlers).forEach(([type, handler]) => {
            this.element.removeEventListener(type, handler);
        });
        clearTimeout(this.timer);
        this.request?.abort();
        this.list.remove();
        this.status.remove();
        ['role', 'aria-autocomplete', 'aria-controls', 'aria-expanded', 'aria-activedescendant'].forEach((name) => {
            this.element.removeAttribute(name);
        });
    }

    typed() {
        clearTimeout(this.timer);
        this.range = activeQuery(this.element.value, this.element.selectionStart ?? this.element.value.length);

        if (null === this.range) {
            this.close();

            return;
        }

        const range = this.range;
        this.timer = setTimeout(() => this.search(range), this.delayValue);
    }

    async search(range) {
        this.request?.abort();
        this.request = new AbortController();
        const items = await suggestions(
            this.urlValue,
            range.query,
            (url, init) => globalThis.fetch(url, init),
            this.request.signal,
        );

        if (this.range !== range) {
            return;
        }

        this.show(items);
    }

    show(items) {
        this.items = items;
        this.active = items.length > 0 ? 0 : -1;
        this.list.replaceChildren(
            ...(items.length > 0 ? items.map((item, index) => this.option(item, index)) : [this.option(null, -1)]),
        );
        this.list.hidden = false;
        this.element.setAttribute('aria-expanded', 'true');
        this.status.textContent =
            items.length > 0 ? this.foundValue.replace('{count}', String(items.length)) : this.emptyValue;
        this.highlight();
    }

    option(item, index) {
        const option = this.element.ownerDocument.createElement('li');

        if (null === item) {
            option.className = 'phil-mentions-empty';
            option.textContent = this.emptyValue;

            return option;
        }

        option.id = `${this.list.id}-${index}`;
        option.dataset.index = String(index);
        option.setAttribute('role', 'option');
        const name = this.element.ownerDocument.createElement('span');
        name.textContent = item.label;
        const handle = this.element.ownerDocument.createElement('small');
        handle.textContent = `@${item.handle}`;
        option.append(name, ' ', handle);

        return option;
    }

    highlight() {
        [...this.list.children].forEach((option, index) => {
            option.setAttribute('aria-selected', String(index === this.active));
        });

        if (this.active >= 0) {
            this.element.setAttribute('aria-activedescendant', `${this.list.id}-${this.active}`);
        } else {
            this.element.removeAttribute('aria-activedescendant');
        }
    }

    key(event) {
        if (this.list.hidden) {
            return;
        }

        if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
            event.preventDefault();
            const count = this.items.length;
            this.active = count > 0 ? (this.active + ('ArrowDown' === event.key ? 1 : count - 1)) % count : -1;
            this.highlight();
        } else if (('Enter' === event.key || 'Tab' === event.key) && this.active >= 0) {
            event.preventDefault();
            this.choose(this.items[this.active]);
        } else if ('Escape' === event.key) {
            event.preventDefault();
            this.close();
        }
    }

    picked(event) {
        const option = event.target.closest('[role="option"]');

        if (null !== option) {
            event.preventDefault();
            this.choose(this.items[Number(option.dataset.index)]);
        }
    }

    choose(item) {
        if (undefined === item || null === this.range) {
            return;
        }

        const { text, caret } = insertMention(this.element.value, this.range, item.handle);
        this.element.value = text;
        this.element.setSelectionRange(caret, caret);
        this.element.dispatchEvent(new Event('input', { bubbles: true }));
        this.close();
        this.dispatch('chosen', { detail: { handle: item.handle, reference: item.reference } });
    }

    close() {
        clearTimeout(this.timer);
        this.request?.abort();
        this.range = null;
        this.items = [];
        this.active = -1;
        this.list.hidden = true;
        this.list.replaceChildren();
        this.status.textContent = '';
        this.element.setAttribute('aria-expanded', 'false');
        this.element.removeAttribute('aria-activedescendant');
    }
}
