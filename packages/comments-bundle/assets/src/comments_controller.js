// Comments on passages of the block editor of phil/block-editor-bundle, and on the whole page: a writer selects words
// in a block and opens a thread on them, with a suggested replacement or not, or writes in the form under the threads
// to comment on the page; threads are listed beside the editor, their passages highlighted in it and followed as the
// text changes, orphaned when they are no longer found; replies, resolve and reopen, and suggestions accepted into
// the editor or rejected, through the bundle's endpoints. Without an editor, only the page is commented on.
//
//     <div data-controller="phil--comments-bundle--comments"
//          data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}">
//         {{ form_row(form.body) }}
//         <aside data-phil--comments-bundle--comments-target="panel"></aside>
//     </div>

import { Controller } from '@hotwired/stimulus';

import { CommentsClient } from './lib/api.js';
import { translator } from './lib/i18n.js';
import {
    CONTEXT,
    fieldOf,
    fieldPath,
    fields,
    offsetIn,
    pointAt,
    reanchor,
    replaceInDocument,
    slice,
} from './lib/passage.js';
import { action, h, textForm, threadElement } from './lib/view.js';

const EDITOR = 'phil--block-editor-bundle--editor';

const MARKS = 'phil-comments';

const ACTIVE = 'phil-comments-active';

let panels = 0;

export default class extends Controller {
    static targets = ['panel'];

    static values = { config: Object };

    connect() {
        const config = this.configValue ?? {};
        this.document = this.element.ownerDocument;
        this.t = translator(config.locale ?? this.document.documentElement.lang, config.messages ?? {});
        this.client = new CommentsClient(config, (url, init) => globalThis.fetch(url, init));
        this.mentions = config.mentions ?? null;
        this.page = false !== config.page;
        this.aria = true === config.ariaRoles;
        this.editor = this.element.querySelector('[data-controller~="' + EDITOR + '"]') ?? this.element;
        this.input = this.editor.querySelector('[data-' + EDITOR + '-target="input"]');
        this.threads = [];
        this.located = new Map();
        this.active = null;
        this.editing = null;
        this.draft = null;
        this.showResolved = false;
        this.listeners = [];
        this.float = null;

        this.listen(this.document, 'selectionchange', () => this.selected());
        this.listen(this.editor, EDITOR + ':change', () => this.schedule());
        this.listen(this.editor, 'click', (event) => this.pointed(event));
        this.listen(this.element, 'click', (event) => this.clicked(event));
        this.listen(this.element, 'submit', (event) => this.submitted(event));
        this.listen(this.element, 'change', (event) => this.toggled(event));

        const seconds = Number(config.refresh) || 0;
        this.timer = seconds > 0 ? setInterval(() => this.load(), seconds * 1000) : null;
        this.load();
    }

    disconnect() {
        clearInterval(this.timer);
        this.listeners.forEach(([target, type, handler]) => {
            target.removeEventListener(type, handler);
        });
        this.listeners = [];
        this.float?.remove();
        this.float = null;
        globalThis.CSS?.highlights?.delete(MARKS);
        globalThis.CSS?.highlights?.delete(ACTIVE);
    }

    listen(target, type, handler) {
        target.addEventListener(type, handler);
        this.listeners.push([target, type, handler]);
    }

    async load() {
        const answer = await this.client.threads();

        if (answer.ok && Array.isArray(answer.data?.threads)) {
            this.threads = answer.data.threads;
            this.render();
        } else {
            this.status(answer);
        }
    }

    /** Whether suggestions can be accepted into the editor: it is there and editable. */
    get editable() {
        return null !== this.input && null !== this.editor.querySelector('.pbe-rich[contenteditable]');
    }

    // Selection --------------------------------------------------------------------------------------------------

    /** Offers to comment on the words selected in one field of the editor. */
    selected() {
        const selection = this.document.getSelection();
        const range =
            null !== selection && selection.rangeCount > 0 && !selection.isCollapsed ? selection.getRangeAt(0) : null;
        const field = null === range ? null : fieldOf(range.startContainer);

        if (null === range || null === field || field !== fieldOf(range.endContainer) || !this.editor.contains(field)) {
            this.hideFloat();

            return;
        }

        const start = offsetIn(field, range.startContainer, range.startOffset);
        const end = offsetIn(field, range.endContainer, range.endOffset);
        const text = field.textContent ?? '';
        const quote = slice(text, start, end);

        if (null === start || null === end || '' === quote.trim()) {
            this.hideFloat();

            return;
        }

        this.selection = {
            block: field.closest('.pbe-block').dataset.id,
            field: fieldPath(field),
            start,
            quote,
            prefix: slice(text, Math.max(0, start - CONTEXT), start),
            suffix: slice(text, end, end + CONTEXT),
        };
        this.showFloat(range.getBoundingClientRect());
    }

    showFloat(rect) {
        if (null === this.float) {
            this.float = h(this.document, 'div', { class: 'phil-comments__float', role: 'toolbar' }, [
                action(this.document, this.t('comment'), 'compose', ''),
                action(this.document, this.t('suggest'), 'suggest', ''),
            ]);
            this.float.addEventListener('mousedown', (event) => event.preventDefault());
            this.float.addEventListener('click', (event) => this.clicked(event));
            this.document.body.append(this.float);
        }

        this.float.style.top = Math.round(rect.bottom + 6) + 'px';
        this.float.style.left = Math.round(rect.left) + 'px';
        this.float.hidden = false;
    }

    hideFloat() {
        if (null !== this.float) {
            this.float.hidden = true;
        }
    }

    // Highlights ---------------------------------------------------------------------------------------------------

    schedule() {
        if (!this.pending) {
            this.pending = true;
            (globalThis.requestAnimationFrame ?? setTimeout)(() => {
                this.pending = false;
                this.highlight();
                this.render(false);
            });
        }
    }

    /** Finds each open thread's passage in the editor as it is now, and highlights it. */
    highlight() {
        const passages = fields(this.editor);
        this.located = new Map();

        for (const thread of this.threads.filter((each) => null !== (each.anchor ?? null))) {
            const anchor = reanchor(thread.anchor, passages);
            const field =
                null === anchor
                    ? null
                    : passages.find((passage) => passage.block === anchor.block && passage.field === anchor.field);
            const start = null === field ? null : pointAt(field.element, anchor.start);
            const end = null === field ? null : pointAt(field.element, anchor.end, true);

            if (null !== start && null !== end) {
                const range = this.document.createRange();
                range.setStart(start.node, start.offset);
                range.setEnd(end.node, end.offset);
                this.located.set(thread.id, { anchor, range });
            }
        }

        const highlights = globalThis.CSS?.highlights;

        if (undefined === highlights || 'function' !== typeof globalThis.Highlight) {
            return;
        }

        const shown = this.threads.filter((thread) => 'open' === thread.status && this.located.has(thread.id));
        highlights.set(MARKS, new globalThis.Highlight(...shown.map((thread) => this.located.get(thread.id).range)));
        highlights.set(
            ACTIVE,
            new globalThis.Highlight(
                ...shown
                    .filter((thread) => thread.id === this.active)
                    .map((thread) => this.located.get(thread.id).range),
            ),
        );
    }

    /** A click in the editor on a highlighted passage brings its thread forward. */
    pointed(event) {
        const point = this.document.caretPositionFromPoint?.(event.clientX, event.clientY);
        const fallback = undefined === point ? this.document.caretRangeFromPoint?.(event.clientX, event.clientY) : null;
        const caret =
            null !== (point ?? null)
                ? { node: point.offsetNode, offset: point.offset }
                : null !== (fallback ?? null)
                  ? { node: fallback.startContainer, offset: fallback.startOffset }
                  : null;

        if (null === caret) {
            return;
        }

        for (const thread of this.threads) {
            const range = 'open' === thread.status ? this.located.get(thread.id)?.range : undefined;

            if (undefined !== range && range.isPointInRange(caret.node, caret.offset)) {
                this.activate(thread.id, false);

                return;
            }
        }
    }

    activate(id, reveal) {
        this.active = id;
        this.highlight();
        this.render(false);
        this.panel
            .querySelector('[data-comments-thread="' + CSS.escape(id) + '"]')
            ?.scrollIntoView({ block: 'nearest' });

        if (reveal) {
            this.located.get(id)?.range.startContainer.parentElement?.scrollIntoView({ block: 'center' });
        }
    }

    // Panel --------------------------------------------------------------------------------------------------------

    get panel() {
        return this.hasPanelTarget ? this.panelTarget : this.element;
    }

    /** The identifier of the heading of the page's comments, unique on the page. */
    get pageHeading() {
        if (undefined === this.heading) {
            panels += 1;
            this.heading = 'phil-comments-page-' + panels;
        }

        return this.heading;
    }

    render(locate = true) {
        if (locate) {
            this.highlight();
        }

        const document = this.document;
        const open = this.threads.filter((thread) => 'open' === thread.status).length;
        const order = fields(this.editor).map((passage) => passage.block + ' ' + passage.field);
        const position = (thread) => {
            const found = this.located.get(thread.id)?.anchor;

            return undefined === found ? [Infinity, 0] : [order.indexOf(found.block + ' ' + found.field), found.start];
        };
        const visible = this.threads.filter((thread) => this.showResolved || 'open' === thread.status);
        const onPage = visible.filter((thread) => null === (thread.anchor ?? null));
        const shown = visible
            .filter((thread) => null !== (thread.anchor ?? null))
            .map((thread) => [position(thread), thread])
            .sort(([a], [b]) => a[0] - b[0] || a[1] - b[1])
            .map(([, thread]) => thread);
        const element = (thread) =>
            threadElement(document, this.t, thread, {
                active: thread.id === this.active,
                // Without an editor, nothing is searched: only the server says a passage is orphaned.
                located: this.located.has(thread.id) || null === this.input,
                editing: this.editing,
                editable: this.editable,
                mentions: this.mentions,
                aria: this.aria,
            });

        this.panel.classList.add('phil-comments');
        this.panel.replaceChildren(
            h(document, 'header', { class: 'phil-comments__header' }, [
                h(document, 'h2', { class: 'phil-comments__title' }, this.t('comments')),
                h(document, 'span', { class: 'phil-comments__count' }, this.t('count', { count: open })),
                h(document, 'label', { class: 'phil-comments__filter' }, [
                    h(document, 'input', { type: 'checkbox', 'data-comments-filter': '', checked: this.showResolved }),
                    ' ',
                    this.t('showResolved'),
                ]),
            ]),
            null === this.draft ? '' : this.composer(),
            0 === shown.length && 0 === onPage.length && null === this.draft
                ? h(document, 'p', { class: 'phil-comments__empty' }, this.t('none'))
                : '',
            ...shown.map(element),
            this.page || onPage.length > 0
                ? h(document, 'section', { class: 'phil-comments__page', 'aria-labelledby': this.pageHeading }, [
                      h(document, 'h3', { class: 'phil-comments__subtitle', id: this.pageHeading }, this.t('page')),
                      ...onPage.map(element),
                      this.page ? textForm(document, this.t, 'page', '', '', null, this.mentions) : null,
                  ])
                : '',
            h(document, 'p', { class: 'phil-comments__status', role: 'status' }, this.message ?? ''),
        );
    }

    composer() {
        const document = this.document;
        const extra = this.draft.suggest
            ? h(document, 'label', { class: 'phil-comments__replacement' }, [
                  this.t('suggestion'),
                  h(document, 'input', { type: 'text', name: 'suggestion', value: this.draft.anchor.quote }),
              ])
            : null;

        return h(document, 'div', { class: 'phil-comments__composer' }, [
            h(document, 'blockquote', { class: 'phil-comments__quote' }, this.draft.anchor.quote),
            textForm(document, this.t, 'open', '', '', extra, this.mentions),
        ]);
    }

    status(answer = null) {
        this.message = null === answer || answer.ok ? '' : this.t(403 === answer.status ? 'refused' : 'failed');
        const element = this.panel.querySelector('.phil-comments__status');

        if (null !== element) {
            element.textContent = this.message;
        }
    }

    /** A thread the server answered, put in place of its copy — or removed when it is gone. */
    replace(id, answer) {
        this.status(answer);

        if (!answer.ok) {
            return false;
        }

        const thread = answer.data?.thread ?? null;
        const index = this.threads.findIndex((each) => each.id === id);

        if (null === thread) {
            this.threads.splice(index, 1);
        } else if (index < 0) {
            this.threads.push(thread);
        } else {
            this.threads[index] = thread;
        }

        this.render();

        return true;
    }

    toggled(event) {
        if (undefined !== event.target.dataset?.commentsFilter) {
            this.showResolved = event.target.checked;
            this.render(false);
        }
    }

    async clicked(event) {
        const button = event.target.closest('[data-comments-do]');

        if (null === button) {
            return;
        }

        const name = button.dataset.commentsDo;
        const id = button.dataset.commentsId;

        if ('compose' === name || 'suggest' === name) {
            this.draft = { anchor: this.selection, suggest: 'suggest' === name };
            this.hideFloat();
            this.render(false);
            this.panel.querySelector('.phil-comments__composer textarea')?.focus();
        } else if ('cancel' === name) {
            this.draft = null;
            this.editing = null;
            this.render(false);
        } else if ('show' === name) {
            this.activate(id, true);
        } else if ('edit' === name) {
            this.editing = id;
            this.render(false);
        } else if ('delete' === name) {
            if (globalThis.confirm?.(this.t('confirmDelete')) ?? true) {
                this.replace(this.threadOf(id), await this.client.remove(id));
            }
        } else if ('accept' === name) {
            await this.accept(id);
        } else {
            this.replace(id, await this.client.act(name, id));
        }
    }

    async submitted(event) {
        const form = event.target.closest('[data-comments-form]');

        if (null === form) {
            return;
        }

        event.preventDefault();
        const name = form.dataset.commentsForm;
        const id = form.dataset.commentsId;
        const body = form.elements.body.value;

        if ('open' === name) {
            const suggestion = form.elements.suggestion?.value ?? null;
            const answer = await this.client.open(this.draft.anchor, body, suggestion);

            if (this.replace(answer.data?.thread?.id ?? '', answer)) {
                this.draft = null;
                this.activate(answer.data.thread.id, false);
            }
        } else if ('page' === name) {
            const answer = await this.client.open(null, body, null);

            if (this.replace(answer.data?.thread?.id ?? '', answer)) {
                this.activate(answer.data.thread.id, false);
            }
        } else if ('edit' === name) {
            this.editing = null;
            this.replace(this.threadOf(id), await this.client.edit(id, body));
        } else {
            this.replace(id, await this.client.reply(id, body));
        }
    }

    threadOf(comment) {
        return this.threads.find((thread) => thread.comments.some((each) => each.id === comment))?.id ?? '';
    }

    /**
     * Accepts a suggestion: the passage, where it is now, is replaced in the editor's block, then the decision recorded;
     * a block another writer holds, or a passage that changed, is left alone.
     */
    async accept(id) {
        const thread = this.threads.find((each) => each.id === id);
        const anchor = this.located.get(id)?.anchor ?? null;
        const block =
            null === anchor || null === this.input
                ? null
                : replaceInDocument(this.input.value, anchor, thread.suggestion.text);

        if (null === block) {
            this.message = this.t('stale');
            this.render(false);

            return;
        }

        if (null !== this.editor.querySelector('.pbe-block.pbe-locked[data-id="' + CSS.escape(block.id) + '"]')) {
            this.message = this.t('locked');
            this.render(false);

            return;
        }

        if (this.replace(id, await this.client.act('accept', id))) {
            this.editor.dispatchEvent(new CustomEvent('phil-block-editor:apply', { detail: { block } }));
        }
    }
}
