// The block editor: a document of UJO blocks edited in place, written back into the form's hidden input. Blocks are
// added from the slash menu or the + button, turned into another type, moved by their handle or the keyboard, nested
// in toggles and columns; text is formatted from a toolbar or the keyboard, mentions and page links come from the
// application's searches, images from its uploader, and what is pasted is cleaned by the rules of phil/ujo.
//
//     <div data-controller="phil--block-editor-bundle--editor"
//          data-phil--block-editor-bundle--editor-config-value='{"tools": ["paragraph", "header"], …}'>
//         <input type="hidden" name="body" data-phil--block-editor-bundle--editor-target="input">
//         <div data-phil--block-editor-bundle--editor-target="canvas"></div>
//     </div>

import { Controller } from '@hotwired/stimulus';

import {
    activeMarks,
    atEnd,
    atStart,
    caretRect,
    deleteBefore,
    insertHtml,
    insertLineBreak,
    onEdgeLine,
    placeCaret,
    rangeIn,
    splitAtCaret,
    textBefore,
    toggleMark,
} from './lib/caret.js';
import { csrfHeaders } from './lib/csrf.js';
import { translator } from './lib/i18n.js';
import { isBlank, isSafeUrl, INLINE_TOOLS as MARKS, profile, sanitize, textOf } from './lib/inline.js';
import { autoformat, isApple, shortcut } from './lib/keys.js';
import { filterItems, findTrigger, moveIndex, referenceHtml, searchUrl, suggestions } from './lib/menu.js';
import {
    duplicate,
    flatItems,
    History,
    indentItem,
    isBlock,
    itemAt,
    locate,
    moveBy,
    moveTo,
    newId,
    outdentItem,
    parseDocument,
    place,
    removeBlock,
    removeItem,
    serialize,
    splitItem,
} from './lib/model.js';
import { pasteBlocks, textBlocks } from './lib/paste.js';
import {
    BLOCK_TOOLS,
    blockActions,
    conversions,
    convert,
    createBlock,
    holdsText,
    INLINE_TOOLS,
    isEmpty,
    slashItems,
} from './lib/tools.js';
import { blockElement, h, listElement } from './lib/view.js';

const HEADER = { 'X-Phil-Block-Editor': '1', Accept: 'application/json' };

/** The inline tools the toolbar shows, with the shortcut each has. */
const TOOLBAR = [
    'bold',
    'italic',
    'underline',
    'strikethrough',
    'inlineCode',
    'link',
    'marker',
    'textColor',
    'superscript',
    'subscript',
];

const FIELD = '.pbe-rich[contenteditable], textarea.pbe-code__source';

export default class extends Controller {
    static targets = ['input', 'canvas', 'status'];

    static values = { config: Object };

    connect() {
        const config = this.configValue ?? {};
        this.tools = (config.tools ?? BLOCK_TOOLS).filter((tool) => BLOCK_TOOLS.includes(tool));
        this.inlineTools = (config.inlineTools ?? INLINE_TOOLS).filter((tool) => INLINE_TOOLS.includes(tool));
        this.marks = profile(this.inlineTools);
        this.readOnly = true === config.readOnly;
        this.t = translator(config.locale ?? this.element.ownerDocument.documentElement.lang, config.messages ?? {});
        this.placeholder = config.placeholder ?? this.t('placeholder');
        this.colors = config.colors ?? [];
        this.uploadUrl = this.tools.includes('image') ? (config.uploadUrl ?? null) : null;
        this.mentionsUrl = this.inlineTools.includes('mention') ? (config.mentionsUrl ?? null) : null;
        this.linksUrl = this.inlineTools.includes('wikiLink') ? (config.linksUrl ?? null) : null;
        this.csrfCookie = config.csrfCookie ?? null;
        this.apple = isApple(globalThis.navigator?.platform);
        this.document = parseDocument(this.hasInputTarget ? this.inputTarget.value : '');
        this.history = new History();
        this.uploading = new Set();
        this.menu = null;
        this.lastInput = 0;

        if (!this.hasCanvasTarget) {
            this.element.append(
                h(this.element.ownerDocument, 'div', {
                    class: 'phil-block-editor__canvas',
                    'data-phil--block-editor-bundle--editor-target': 'canvas',
                }),
            );
        }

        this.canvasTarget.setAttribute('role', 'group');
        this.canvasTarget.setAttribute('aria-roledescription', this.t('editor'));

        if (!this.readOnly && 0 === this.document.blocks.length) {
            this.document.blocks.push(this.block('paragraph'));
        }

        this.listeners = [];
        this.locks = {};
        this.focusedId = null;
        this.listen(this.canvasTarget, 'click', (event) => this.click(event));
        this.listen(this.element, 'phil-block-editor:apply', (event) => this.applyRemote(event.detail ?? {}));
        this.listen(this.element, 'phil-block-editor:remove', (event) => this.removeRemote(event.detail ?? {}));
        this.listen(this.element, 'phil-block-editor:lock', (event) => this.lockBlocks(event.detail ?? {}));

        if (!this.readOnly) {
            this.listen(this.canvasTarget, 'input', (event) => this.input(event));
            this.listen(this.canvasTarget, 'change', (event) => this.change(event));
            this.listen(this.canvasTarget, 'keydown', (event) => this.keydown(event));
            this.listen(this.canvasTarget, 'beforeinput', (event) => this.beforeinput(event));
            this.listen(this.canvasTarget, 'paste', (event) => this.paste(event));
            this.listen(this.canvasTarget, 'focusin', (event) => this.focusin(event));
            this.listen(this.canvasTarget, 'focusout', (event) => this.focusout(event));
            this.listen(this.canvasTarget, 'dragstart', (event) => this.dragstart(event));
            this.listen(this.canvasTarget, 'dragover', (event) => this.dragover(event));
            this.listen(this.canvasTarget, 'dragleave', (event) => this.dragleave(event));
            this.listen(this.canvasTarget, 'drop', (event) => this.drop(event));
            this.listen(this.canvasTarget, 'dragend', () => this.dragend());
            this.listen(this.element.ownerDocument, 'selectionchange', () => this.selectionchange());
            this.listen(this.element.ownerDocument, 'mousedown', (event) => this.outside(event));
            const form = this.element.closest('form');

            if (null !== form) {
                this.listen(form, 'submit', () => this.save(true), true);
            }
        }

        this.render();
    }

    disconnect() {
        if (!this.readOnly) {
            this.save(true);
        }

        this.listeners.forEach(([target, type, handler, capture]) => {
            target.removeEventListener(type, handler, capture);
        });
        this.listeners = [];
        this.closeMenu();
        this.toolbar?.remove();
        this.toolbar = null;
    }

    listen(target, type, handler, capture = false) {
        target.addEventListener(type, handler, capture);
        this.listeners.push([target, type, handler, capture]);
    }

    block(type, options = {}) {
        return createBlock(type, options, newId);
    }

    // Drawing ---------------------------------------------------------------------------------------------------

    viewContext() {
        return {
            document: this.element.ownerDocument,
            t: this.t,
            readOnly: this.readOnly,
            marks: this.marks,
            placeholder: this.placeholder,
            uploadUrl: this.uploadUrl,
            uploading: this.uploading,
        };
    }

    /** Draws the document again, then focuses a field: `{id, selector, where}`. */
    render(focus = null) {
        const root = listElement(this.viewContext(), 'root', this.document.blocks);

        if (this.readOnly && 0 === this.document.blocks.length) {
            root.append(h(this.element.ownerDocument, 'p', { class: 'pbe-nothing', text: this.t('emptyEditor') }));
        }

        this.canvasTarget.replaceChildren(root);
        this.canvasTarget.classList.toggle(
            'pbe-sole',
            1 === this.document.blocks.length && 'paragraph' === this.document.blocks[0].type,
        );

        this.applyLocks();

        if (null !== focus) {
            this.focus(focus);
        }
    }

    blockElement(id) {
        return this.canvasTarget.querySelector('.pbe-block[data-id="' + CSS.escape(id) + '"]');
    }

    /** The first field of a block, or the one a selector names. */
    field(id, selector = null) {
        const element = this.blockElement(id);

        if (null === element) {
            return null;
        }

        const body = element.querySelector(':scope > .pbe-body');

        return null === selector
            ? (Array.from(body.querySelectorAll(FIELD + ', input.pbe-picker__address')).find(
                  (field) => field.closest('.pbe-block') === element,
              ) ?? null)
            : body.querySelector(selector);
    }

    focus({ id, selector = null, where = 'end' }) {
        const field = this.field(id, selector);

        if (null !== field) {
            placeCaret(field, where);
        } else {
            this.selectBlock(id);
        }
    }

    announce(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }

    // Saving and history ----------------------------------------------------------------------------------------

    /** Writes the document into the input, at once or after a short pause. */
    save(now = false) {
        clearTimeout(this.saveTimer);

        if (!now) {
            this.saveTimer = setTimeout(() => this.save(true), 200);

            return;
        }

        if (!this.hasInputTarget) {
            return;
        }

        const json = serialize(this.document, isEmpty);

        if (json !== this.inputTarget.value) {
            this.inputTarget.value = json;
            this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
            this.dispatch('change', { detail: { json } });
        }
    }

    snapshot() {
        return JSON.stringify(this.document.blocks);
    }

    /** Keeps the state before a change, so that it can be undone. */
    remember() {
        this.history.record(this.snapshot());
    }

    /** A change of structure: remembered, made, drawn, saved; the focus may be found once the change is made. */
    transact(apply, focus = null, message = null) {
        this.remember();
        apply();
        this.render('function' === typeof focus ? focus() : focus);
        this.save();

        if (null !== message) {
            this.announce(message);
        }
    }

    undo(redo = false) {
        const state = redo ? this.history.redo(this.snapshot()) : this.history.undo(this.snapshot());

        if (null === state) {
            return;
        }

        const current = this.current?.id;
        this.document.blocks = JSON.parse(state);
        this.render();

        if (undefined !== current && null !== locate(this.document.blocks, current)) {
            this.focus({ id: current });
        }

        this.save();
    }

    // Reading the fields ----------------------------------------------------------------------------------------

    /** Where an event happened: the block, its data, the field and what it holds. */
    where(target) {
        const element = target.closest?.('.pbe-block');
        const found =
            null === element || undefined === element ? null : locate(this.document.blocks, element.dataset.id);

        if (null === found) {
            return null;
        }

        const field = target.closest(FIELD + ', input, [data-field]');
        const owned = null !== field && field.closest('.pbe-block') === element ? field : null;

        return { element, block: found.block, found, field: owned };
    }

    /** The inline HTML of a rich field, cleaned. */
    html(field) {
        return sanitize(field.innerHTML, this.marks).replace(/(?:<br>)+$/, '');
    }

    /** Writes what a field shows into the block's data. */
    read(block, field) {
        const data = block.data;

        if (undefined !== field.dataset.itemField) {
            itemAt(data.items, field.dataset.itemField.split('.').map(Number)).content = this.html(field);
        } else if (undefined !== field.dataset.cell) {
            const [r, c] = field.dataset.cell.split('.').map(Number);
            data.content[r][c] = this.html(field);
        } else if ('language' === field.dataset.field) {
            const language = field.value.replace(/[^A-Za-z0-9_+#.-]/g, '').slice(0, 32);

            if ('' === language) {
                delete data.language;
            } else {
                data.language = language;
            }
        } else if ('code' === field.dataset.field) {
            data.code = field.value;
            field.rows = Math.max(2, field.value.split('\n').length);
        } else if (undefined !== field.dataset.field && field.isContentEditable) {
            data[field.dataset.field] = this.html(field);
        }
    }

    // Events ----------------------------------------------------------------------------------------------------

    input(event) {
        const at = this.where(event.target);

        if (null === at || null === at.field || undefined !== at.field.dataset.imageUrl) {
            return;
        }

        if (Date.now() - this.lastInput > 800) {
            this.remember();
        }

        this.lastInput = Date.now();
        const field = at.field;

        if (
            field.isContentEditable &&
            isBlank(field.innerHTML) &&
            '' !== field.innerHTML &&
            null === field.querySelector('br + br')
        ) {
            field.innerHTML = '';
        }

        this.read(at.block, field);

        if ('paragraph' === at.block.type && 'text' === field.dataset.field && this.formatAsTyped(at.block, field)) {
            return;
        }

        if (field.isContentEditable) {
            this.trigger(at, field);
        }

        this.save();
    }

    /** Turns a paragraph into another block as its Markdown-like mark is typed. */
    formatAsTyped(block, field) {
        const format = atEnd(field) ? autoformat(textOf(field.innerHTML)) : null;

        if (null === format || !this.tools.includes(format.type)) {
            return false;
        }

        this.replace(block, format.type, format.options);

        return true;
    }

    /** Puts a new block of a type in the place of a paragraph holding only its mark, under the same identifier. */
    replace(block, type, options = {}) {
        const fresh = { ...this.block(type, options), id: block.id };

        this.transact(
            () => {
                const found = locate(this.document.blocks, block.id);
                found.list.splice(found.index, 1, fresh);

                if ('delimiter' === type) {
                    found.list.splice(found.index + 1, 0, this.block('paragraph'));
                }
            },
            () => this.focusOf(fresh),
            this.t('converted', { type: this.t(this.labelOf(type, options)) }),
        );
    }

    /** Where to put the caret in a new block: its first field, or the paragraph after a divider. */
    focusOf(block) {
        if ('delimiter' === block.type) {
            const found = locate(this.document.blocks, block.id);
            const next = found?.list[found.index + 1];

            return { id: next?.id ?? block.id, where: 'start' };
        }

        return { id: block.id, where: 'start' };
    }

    labelOf(type, options = {}) {
        return (
            slashItems(BLOCK_TOOLS).find(
                (entry) =>
                    entry.type === type &&
                    Object.entries(entry.options).every(([name, value]) => options[name] === value),
            )?.key ?? type
        );
    }

    change(event) {
        const at = this.where(event.target);

        if (null === at) {
            return;
        }

        if (undefined !== event.target.dataset.check) {
            this.remember();
            itemAt(at.block.data.items, event.target.dataset.check.split('.').map(Number)).meta = {
                checked: event.target.checked,
            };
            this.save();
        } else if (undefined !== event.target.dataset.upload && event.target.files.length > 0) {
            this.upload(at.block.id, event.target.files[0]);
        }
    }

    focusin(event) {
        const at = this.where(event.target);
        this.canvasTarget.querySelectorAll('.pbe-focused').forEach((element) => {
            element.classList.remove('pbe-focused');
        });

        if (null !== at) {
            this.current = { id: at.block.id };
            at.element.classList.add('pbe-focused');

            if (at.block.id !== this.focusedId) {
                this.focusedId = at.block.id;
                this.dispatch('focus', { detail: { id: at.block.id } });
            }
        }

        if (null === at || at.element !== event.target) {
            this.unselect();
        }
    }

    /** Focus leaving the editor, its menus and its toolbar: no block is edited any more. */
    focusout(event) {
        const to = event.relatedTarget;
        const inside = [this.element, this.menuElement, this.toolbar].some(
            (element) => null !== to && undefined !== element && null !== element && element.contains(to),
        );

        if (!inside && null !== this.focusedId) {
            this.focusedId = null;
            this.dispatch('focus', { detail: { id: null } });
        }
    }

    // Other writers ---------------------------------------------------------------------------------------------

    /**
     * A block another writer saved, `{block, list, after}`: put in place of its copy, or inserted where it goes, and
     * drawn without disturbing the block being edited. The history starts again, since undoing would bring back what
     * the other writer replaced.
     */
    applyRemote({ block, list = 'root', after = null }) {
        if (!isBlock(block)) {
            return;
        }

        const where = place(
            this.document.blocks,
            block,
            'string' === typeof list ? list : 'root',
            'string' === typeof after ? after : null,
        );
        const old = this.blockElement(block.id);
        const container = this.canvasTarget.querySelector('.pbe-list[data-list="' + CSS.escape(where.key) + '"]');

        if (where.replaced && null !== old) {
            old.replaceWith(blockElement(this.viewContext(), block));
            this.applyLocks();
        } else if (!where.replaced && null !== container) {
            container.querySelector(':scope > .pbe-empty')?.remove();
            const next = container.querySelectorAll(':scope > .pbe-block')[where.index] ?? null;
            container.insertBefore(blockElement(this.viewContext(), block), next);
            this.applyLocks();
        } else {
            this.renderKeepingFocus();
        }

        this.history = new History();

        if (!this.readOnly) {
            this.save();
        }
    }

    /** A block another writer removed, `{id}`. */
    removeRemote({ id }) {
        if ('string' !== typeof id || null === removeBlock(this.document.blocks, id)) {
            return;
        }

        this.renderKeepingFocus();
        this.history = new History();

        if (!this.readOnly) {
            this.save();
        }
    }

    /** The blocks other writers hold, `{blocks: {id: name}}`: shown with the name, and closed to editing. */
    lockBlocks({ blocks = {} }) {
        this.locks = null !== blocks && 'object' === typeof blocks ? blocks : {};
        this.applyLocks();
    }

    applyLocks() {
        this.canvasTarget.querySelectorAll('.pbe-block').forEach((element) => {
            const name = Object.hasOwn(this.locks, element.dataset.id) ? String(this.locks[element.dataset.id]) : null;
            element.classList.toggle('pbe-locked', null !== name);
            element.inert = null !== name;

            if (null === name) {
                element.removeAttribute('data-locked-by');
            } else {
                element.setAttribute('data-locked-by', name);
            }
        });
    }

    /** Draws the document again, the caret back in the block being edited when the editor had the focus. */
    renderKeepingFocus() {
        const current = this.focusedId;
        const focused = this.canvasTarget.contains(this.element.ownerDocument.activeElement);
        this.render(
            focused && null !== current && null !== locate(this.document.blocks, current) ? { id: current } : null,
        );
    }

    click(event) {
        const target = event.target.closest('button');
        const at = this.where(event.target);

        if (null === target) {
            return;
        }

        if (undefined !== target.dataset.toggle) {
            const found = locate(this.document.blocks, target.dataset.toggle);
            found.block.data.open = !found.block.data.open;
            this.render({ id: found.block.id });

            if (!this.readOnly) {
                this.save();
            }

            return;
        }

        if (this.readOnly) {
            return;
        }

        if (undefined !== target.dataset.empty) {
            const fresh = this.block('paragraph');
            this.transact(() => this.listByKey(target.dataset.empty).push(fresh), { id: fresh.id });
        } else if (undefined !== target.dataset.imageInsert) {
            this.insertImageUrl(at.block);
        } else if (target.classList.contains('pbe-add')) {
            this.addAfter(at.block);
        } else if (target.classList.contains('pbe-handle')) {
            this.openBlockMenu(at.block, target);
        }
    }

    listByKey(key) {
        if ('root' === key) {
            return this.document.blocks;
        }

        const [id, column] = key.split(':');
        const block = locate(this.document.blocks, id).block;

        return undefined === column ? block.data.blocks : block.data.cols[Number(column)].blocks;
    }

    /** Adds a paragraph after a block and opens the slash menu in it. */
    addAfter(block) {
        const fresh = this.block('paragraph');
        this.transact(
            () => {
                const found = locate(this.document.blocks, block.id);
                found.list.splice(found.index + 1, 0, fresh);
            },
            { id: fresh.id },
        );
        const field = this.field(fresh.id);
        this.openSlash({ block: fresh, element: this.blockElement(fresh.id), field }, field, '', 0);
    }

    beforeinput(event) {
        if ('historyUndo' === event.inputType || 'historyRedo' === event.inputType) {
            event.preventDefault();
            this.undo('historyRedo' === event.inputType);
        } else if (event.inputType.startsWith('format')) {
            event.preventDefault();
        }
    }

    keydown(event) {
        if (event.isComposing) {
            return;
        }

        if (null !== this.menu && this.menuKey(event)) {
            return;
        }

        const at = this.where(event.target);

        if (null === at) {
            return;
        }

        if (at.element === event.target) {
            this.selectedKey(event, at);

            return;
        }

        const action = shortcut(event, this.apple);

        if (null !== action && this.act(action, at, event)) {
            event.preventDefault();

            return;
        }

        const field = at.field;

        if (null === field || (!field.isContentEditable && 'TEXTAREA' !== field.tagName)) {
            return;
        }

        if ('TEXTAREA' === field.tagName) {
            if ('Enter' === event.key && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                this.newParagraphAfter(at.block);
            } else if ('Backspace' === event.key && '' === field.value) {
                event.preventDefault();
                this.removeAndFocusPrevious(at.block);
            }

            return;
        }

        if ('Enter' === event.key && event.shiftKey) {
            event.preventDefault();

            if (this.inlineTools.includes('lineBreak')) {
                insertLineBreak(field);
                this.input({ target: field });
            }
        } else if ('Enter' === event.key) {
            event.preventDefault();
            this.enter(at, field);
        } else if ('Backspace' === event.key && atStart(field)) {
            if (this.backspace(at, field)) {
                event.preventDefault();
            }
        } else if ('Delete' === event.key && atEnd(field)) {
            if (this.mergeNext(at, field)) {
                event.preventDefault();
            }
        } else if ('Tab' === event.key && undefined !== field.dataset.itemField) {
            event.preventDefault();
            this.indent(at, field, event.shiftKey);
        } else if (
            ('ArrowUp' === event.key || 'ArrowLeft' === event.key) &&
            !event.shiftKey &&
            ('ArrowUp' === event.key ? onEdgeLine(field, true) : atStart(field))
        ) {
            event.preventDefault();
            this.moveFocus(field, -1);
        } else if (
            ('ArrowDown' === event.key || 'ArrowRight' === event.key) &&
            !event.shiftKey &&
            ('ArrowDown' === event.key ? onEdgeLine(field, false) : atEnd(field))
        ) {
            event.preventDefault();
            this.moveFocus(field, 1);
        }
    }

    /** Runs a shortcut's action; whether it was one here. */
    act(action, at, event) {
        const field = at.field;
        const mark = MARKS.find((tool) => tool.name === action);

        if (undefined !== mark) {
            if (null === field || !field.isContentEditable || !this.inlineTools.includes(action)) {
                return false;
            }

            if ('link' === action) {
                this.showToolbar(field, true);
            } else {
                toggleMark(field, mark);
                this.input({ target: field });
            }

            return true;
        }

        switch (action) {
            case 'undo':
            case 'redo':
                this.undo('redo' === action);

                return true;
            case 'duplicate':
                this.duplicateBlock(at.block);

                return true;
            case 'moveUp':
            case 'moveDown':
                this.moveBlock(at.block, 'moveUp' === action ? -1 : 1);

                return true;
            case 'selectBlock':
                this.selectBlock(at.block.id);

                return true;
            case 'selectAll':
                return false;
            default: {
                const entry = slashItems(this.tools).find((item) => item.key === action);

                if (undefined === entry || !holdsText(at.block.type) || event.repeat) {
                    return false;
                }

                this.turnInto(at.block, entry);

                return true;
            }
        }
    }

    enter(at, field) {
        const { block } = at;

        if (undefined !== field.dataset.itemField) {
            this.enterItem(at, field);

            return;
        }

        if (undefined !== field.dataset.cell) {
            const [r, c] = field.dataset.cell.split('.').map(Number);

            if (r + 1 === block.data.content.length) {
                this.transact(() => block.data.content.push(block.data.content[0].map(() => '')), {
                    id: block.id,
                    selector: '[data-cell="' + (r + 1) + '.' + c + '"]',
                });
            } else {
                this.focus({ id: block.id, selector: '[data-cell="' + (r + 1) + '.' + c + '"]', where: 'start' });
            }

            return;
        }

        if ('text' !== field.dataset.field) {
            this.newParagraphAfter(block);

            return;
        }

        this.remember();
        const after = sanitize(splitAtCaret(field), this.marks).replace(/(?:<br>)+$/, '');
        this.read(block, field);
        const fresh = { id: newId(), type: 'paragraph', data: { text: isBlank(after) ? '' : after } };

        if ('toggle' === block.type && block.data.open) {
            block.data.blocks.unshift(fresh);
        } else {
            const found = locate(this.document.blocks, block.id);
            found.list.splice(found.index + 1, 0, fresh);
        }

        this.render({ id: fresh.id, where: 'start' });
        this.save();
    }

    newParagraphAfter(block) {
        const fresh = this.block('paragraph');
        this.transact(
            () => {
                const found = locate(this.document.blocks, block.id);
                found.list.splice(found.index + 1, 0, fresh);
            },
            { id: fresh.id },
        );
    }

    enterItem(at, field) {
        const { block } = at;
        const path = field.dataset.itemField.split('.').map(Number);
        const data = block.data;

        if (isBlank(field.innerHTML) && 0 === itemAt(data.items, path).items.length) {
            if (path.length > 1) {
                this.indent(at, field, true);

                return;
            }

            const fresh = this.block('paragraph');
            this.transact(
                () => {
                    removeItem(data, path);
                    const found = locate(this.document.blocks, block.id);
                    const rest = data.items.splice(path[0]);
                    const tail = rest.length > 0 ? [{ id: newId(), type: 'list', data: { ...data, items: rest } }] : [];
                    found.list.splice(found.index + 1, 0, fresh, ...tail);

                    if (0 === data.items.length) {
                        found.list.splice(found.index, 1);
                    }
                },
                { id: fresh.id },
            );

            return;
        }

        this.remember();
        const after = sanitize(splitAtCaret(field), this.marks).replace(/(?:<br>)+$/, '');
        this.read(block, field);
        const next = splitItem(data, path, itemAt(data.items, path).content, isBlank(after) ? '' : after);
        this.render({ id: block.id, selector: '[data-item-field="' + next.join('.') + '"]', where: 'start' });
        this.save();
    }

    indent(at, field, out) {
        const path = field.dataset.itemField.split('.').map(Number);
        this.remember();
        const next = out ? outdentItem(at.block.data, path) : indentItem(at.block.data, path);

        if (null !== next) {
            const offset = textBefore(field).length;
            this.render({ id: at.block.id, selector: '[data-item-field="' + next.join('.') + '"]', where: offset });
            this.save();
        }
    }

    /** Backspace at the start of a field; whether it did something. */
    backspace(at, field) {
        const { block } = at;

        if (undefined !== field.dataset.itemField) {
            const path = field.dataset.itemField.split('.').map(Number);

            if (path.length > 1) {
                this.indent(at, field, true);

                return true;
            }

            const flat = flatItems(block.data.items);

            if (1 === flat.length || (0 === path[0] && isBlank(field.innerHTML))) {
                this.turnInto(block, { type: 'paragraph', options: {} });

                return true;
            }

            if (0 === path[0]) {
                return false;
            }

            let target = null;
            let offset = 0;
            this.transact(() => {
                const content = itemAt(block.data.items, path).content;
                target = removeItem(block.data, path);
                const previous = itemAt(block.data.items, target);
                offset = textOf(previous.content).length;
                previous.content += content;
            });
            this.focus({ id: block.id, selector: '[data-item-field="' + target.join('.') + '"]', where: offset });

            return true;
        }

        if ('text' !== field.dataset.field) {
            return false;
        }

        if ('paragraph' !== block.type) {
            this.turnInto(block, { type: 'paragraph', options: {} });

            return true;
        }

        const found = locate(this.document.blocks, block.id);
        const previous = found.list[found.index - 1];

        if (isBlank(block.data.text)) {
            this.removeAndFocusPrevious(block);

            return true;
        }

        if (undefined === previous || !['paragraph', 'header', 'quote', 'alert', 'toggle'].includes(previous.type)) {
            return false;
        }

        const offset = textOf(previous.data.text).length;
        this.transact(
            () => {
                previous.data.text = (isBlank(previous.data.text) ? '' : previous.data.text) + block.data.text;
                found.list.splice(found.index, 1);
            },
            { id: previous.id, where: offset },
        );

        return true;
    }

    /** Delete at the end of a paragraph pulls the next paragraph into it. */
    mergeNext(at, field) {
        const { block } = at;

        if ('text' !== field.dataset.field || 'paragraph' !== block.type) {
            return false;
        }

        const found = locate(this.document.blocks, block.id);
        const next = found.list[found.index + 1];

        if (undefined === next || 'paragraph' !== next.type) {
            return false;
        }

        const offset = textOf(block.data.text).length;
        this.transact(
            () => {
                block.data.text += next.data.text;
                found.list.splice(found.index + 1, 1);
            },
            { id: block.id, where: offset },
        );

        return true;
    }

    removeAndFocusPrevious(block) {
        const fields = Array.from(this.canvasTarget.querySelectorAll(FIELD));
        const own = this.field(block.id);
        const index = fields.indexOf(own);
        const previous = fields
            .slice(0, Math.max(0, index))
            .reverse()
            .find((field) => !this.blockElement(block.id).contains(field));
        const target = previous?.closest('.pbe-block')?.dataset.id;
        const selector = undefined === previous ? null : this.selectorOf(previous);

        this.transact(
            () => {
                removeBlock(this.document.blocks, block.id);

                if (0 === this.document.blocks.length) {
                    this.document.blocks.push(this.block('paragraph'));
                }
            },
            undefined === target ? { id: this.document.blocks[0].id } : { id: target, selector },
            this.t('deleted'),
        );
    }

    /** A selector finding a field again after the document is drawn anew. */
    selectorOf(field) {
        for (const name of ['itemField', 'cell', 'field']) {
            if (undefined !== field.dataset[name]) {
                const attribute = 'data-' + name.replace(/[A-Z]/g, (letter) => '-' + letter.toLowerCase());

                return '[' + attribute + '="' + field.dataset[name] + '"]';
            }
        }

        return null;
    }

    moveFocus(field, delta) {
        const fields = Array.from(this.canvasTarget.querySelectorAll(FIELD));
        const next = fields[fields.indexOf(field) + delta];

        if (undefined !== next) {
            placeCaret(next, delta < 0 ? 'end' : 'start');
        }
    }

    // Block selection -------------------------------------------------------------------------------------------

    selectBlock(id) {
        const element = this.blockElement(id);

        if (null === element) {
            return;
        }

        this.unselect();
        this.selected = id;
        element.classList.add('pbe-selected');
        element.setAttribute('aria-selected', 'true');
        element.focus();
    }

    unselect() {
        this.canvasTarget.querySelectorAll('.pbe-selected').forEach((element) => {
            element.classList.remove('pbe-selected');
            element.removeAttribute('aria-selected');
        });
        this.selected = null;
    }

    /** The keys of a selected block: remove, edit, move the selection or the block, duplicate. */
    selectedKey(event, at) {
        const action = shortcut(event, this.apple);
        const found = at.found;

        if ('Backspace' === event.key || 'Delete' === event.key) {
            event.preventDefault();
            const neighbour = found.list[found.index + 1] ?? found.list[found.index - 1];
            this.transact(() => removeBlock(this.document.blocks, at.block.id), null, this.t('deleted'));

            if (0 === this.document.blocks.length) {
                this.document.blocks.push(this.block('paragraph'));
                this.render();
            }

            this.selectBlock(neighbour?.id ?? this.document.blocks[0].id);
        } else if ('Enter' === event.key) {
            event.preventDefault();
            this.focus({ id: at.block.id });
        } else if ('Escape' === event.key) {
            event.preventDefault();
            this.unselect();
        } else if ('moveUp' === action || 'moveDown' === action) {
            event.preventDefault();
            this.moveBlock(at.block, 'moveUp' === action ? -1 : 1, true);
        } else if ('duplicate' === action) {
            event.preventDefault();
            this.duplicateBlock(at.block, true);
        } else if ('undo' === action || 'redo' === action) {
            event.preventDefault();
            this.undo('redo' === action);
        } else if ('ArrowUp' === event.key || 'ArrowDown' === event.key) {
            event.preventDefault();
            const next = found.list[found.index + ('ArrowUp' === event.key ? -1 : 1)];

            if (undefined !== next) {
                this.selectBlock(next.id);
            }
        }
    }

    // Block operations ------------------------------------------------------------------------------------------

    moveBlock(block, delta, keepSelected = false) {
        this.remember();

        if (moveBy(this.document.blocks, block.id, delta)) {
            this.render();

            if (keepSelected) {
                this.selectBlock(block.id);
            } else {
                this.focus({ id: block.id });
            }

            this.save();
            this.announce(this.t('moved'));
        }
    }

    duplicateBlock(block, keepSelected = false) {
        let copy = null;
        this.transact(() => {
            copy = duplicate(this.document.blocks, block.id, newId);
        });

        if (keepSelected) {
            this.selectBlock(copy.id);
        } else {
            this.focus({ id: copy.id });
        }

        this.announce(this.t('duplicated'));
    }

    deleteBlock(block) {
        this.removeAndFocusPrevious(block);
    }

    turnInto(block, entry) {
        const blocks = convert(block, entry.type, entry.options, newId);

        if (null === blocks) {
            return;
        }

        this.transact(
            () => {
                const found = locate(this.document.blocks, block.id);
                found.list.splice(found.index, 1, ...blocks);
            },
            { id: block.id },
            this.t('converted', { type: this.t(entry.key ?? this.labelOf(entry.type, entry.options)) }),
        );
    }

    // Menus -----------------------------------------------------------------------------------------------------

    /** Opens, updates or closes the slash, mention or page-link menu from the text before the caret. */
    trigger(at, field) {
        const kinds = [
            'slash',
            null === this.mentionsUrl ? null : 'mention',
            null === this.linksUrl ? null : 'link',
        ].filter((kind) => null !== kind);
        const found = findTrigger(
            textBefore(field),
            undefined === field.dataset.field || 'text' === field.dataset.field
                ? kinds
                : kinds.filter((kind) => 'slash' !== kind),
        );

        if (null === found) {
            if (null !== this.menu && 'block' !== this.menu.kind) {
                this.closeMenu();
            }

            return;
        }

        if ('slash' === found.kind) {
            this.openSlash(at, field, found.query, found.length);
        } else {
            this.openSearch(at, field, found);
        }
    }

    openSlash(at, field, query, length) {
        const items = slashItems(this.tools).map((entry) => ({ ...entry, label: this.t(entry.key) }));
        const shown = filterItems(items, query);

        if (0 === shown.length && query.length > 3) {
            this.closeMenu();

            return;
        }

        this.showMenu({
            kind: 'slash',
            role: 'listbox',
            label: this.t('blocks'),
            items: shown,
            empty: this.t('noResults'),
            anchor: caretRect(field),
            choose: (entry) => {
                deleteBefore(field, length);
                this.read(at.block, field);
                this.closeMenu();
                const fresh = this.block(entry.type, entry.options);
                const replaceable =
                    'paragraph' === at.block.type && isBlank(at.block.data.text) && 'text' === field.dataset.field;
                fresh.id = replaceable ? at.block.id : fresh.id;

                this.transact(
                    () => {
                        const found = locate(this.document.blocks, at.block.id);

                        if (replaceable) {
                            found.list.splice(found.index, 1, fresh);
                        } else {
                            found.list.splice(found.index + 1, 0, fresh);
                        }

                        if ('delimiter' === entry.type) {
                            found.list.splice(found.list.indexOf(fresh) + 1, 0, this.block('paragraph'));
                        }
                    },
                    () => this.focusOf(fresh),
                );
            },
        });
    }

    openSearch(at, field, found) {
        const url = 'mention' === found.kind ? this.mentionsUrl : this.linksUrl;
        const range = rangeIn(field)?.cloneRange();
        const request = (this.searchRequest ?? 0) + 1;
        this.searchRequest = request;

        this.showMenu({
            kind: found.kind,
            role: 'listbox',
            label: this.t('mention' === found.kind ? 'mention' : 'pageLink'),
            items: this.menu?.kind === found.kind ? this.menu.items : [],
            empty: this.t('searching'),
            anchor: caretRect(field),
            choose: (entry) => {
                this.closeMenu();
                field.focus();

                if (undefined !== range) {
                    const current = field.ownerDocument.getSelection();
                    current.removeAllRanges();
                    current.addRange(range);
                }

                deleteBefore(field, found.length);
                insertHtml(field, referenceHtml(found.kind, entry.suggestion) + ' ');
                this.input({ target: field });
            },
        });

        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            fetch(searchUrl(url, found.query), { headers: this.headers(false), credentials: 'same-origin' })
                .then((response) =>
                    response.ok ? response.json() : Promise.reject(new Error(String(response.status))),
                )
                .then((answer) => {
                    if (request === this.searchRequest && null !== this.menu && found.kind === this.menu.kind) {
                        this.menu.items = suggestions(answer).map((suggestion) => ({
                            key: suggestion.reference,
                            label: suggestion.label,
                            hint: suggestion.hint ?? '',
                            suggestion,
                        }));
                        this.menu.empty = this.t('noResults');
                        this.menu.index = 0;
                        this.drawMenu();
                    }
                })
                .catch(() => {
                    if (null !== this.menu && found.kind === this.menu.kind) {
                        this.menu.items = [];
                        this.menu.empty = this.t('searchFailed');
                        this.drawMenu();
                    }
                });
        }, 150);
    }

    openBlockMenu(block, button) {
        this.hideToolbar();
        const items = [];
        const turns = conversions(block, this.tools).filter(
            (entry) =>
                !(
                    entry.type === block.type &&
                    Object.entries(entry.options).every(
                        ([name, value]) =>
                            block.data[name] === value || (block.data.style === value && 'style' === name),
                    )
                ),
        );

        if (turns.length > 0) {
            items.push({ heading: this.t('turnInto') });
            turns.forEach((entry) => {
                items.push({
                    key: 'turn.' + entry.key,
                    label: this.t(entry.key),
                    run: () => this.turnInto(block, entry),
                });
            });
        }

        const actions = blockActions(block);

        if (actions.length > 0) {
            items.push({ separator: true });
            actions.forEach((action) => {
                items.push({
                    key: action.key,
                    label: this.t(action.key),
                    checked: action.active(block.data),
                    run: () =>
                        this.transact(
                            () => {
                                block.data = action.apply(block.data);
                            },
                            { id: block.id },
                        ),
                });
            });
        }

        items.push({ separator: true });
        items.push({
            key: 'duplicate',
            label: this.t('duplicate'),
            hint: this.apple ? '⌘D' : 'Ctrl+D',
            run: () => this.duplicateBlock(block),
        });
        items.push({
            key: 'moveUp',
            label: this.t('moveUp'),
            hint: this.apple ? '⌘⇧↑' : 'Ctrl+Shift+↑',
            run: () => this.moveBlock(block, -1),
        });
        items.push({
            key: 'moveDown',
            label: this.t('moveDown'),
            hint: this.apple ? '⌘⇧↓' : 'Ctrl+Shift+↓',
            run: () => this.moveBlock(block, 1),
        });
        items.push({ key: 'delete', label: this.t('delete'), danger: true, run: () => this.deleteBlock(block) });

        this.showMenu({
            kind: 'block',
            role: 'menu',
            label: this.t('handle'),
            items,
            anchor: button.getBoundingClientRect(),
            returnFocus: button,
            choose: (entry) => {
                this.closeMenu();
                entry.run();
            },
        });
        this.menuElement.querySelector('[role^="menuitem"]')?.focus();
    }

    showMenu(menu) {
        this.menu = { index: 0, ...menu };
        this.drawMenu();
    }

    drawMenu() {
        const document = this.element.ownerDocument;
        const menu = this.menu;
        const choices = menu.items.filter((item) => undefined !== item.key);
        menu.index = Math.min(Math.max(0, menu.index), Math.max(0, choices.length - 1));

        if (undefined === this.menuElement) {
            this.menuElement = h(document, 'div', { class: 'pbe-menu' });
            this.menuElement.addEventListener('keydown', (event) => (null === this.menu ? null : this.menuKey(event)));
        }

        this.menuElement.setAttribute('role', menu.role);
        this.menuElement.setAttribute('aria-label', menu.label);
        this.menuElement.id = this.menuElement.id || 'pbe-menu-' + newId();
        const children = [];
        let position = 0;

        for (const item of menu.items) {
            if (item.heading) {
                children.push(
                    h(document, 'div', { class: 'pbe-menu__heading', role: 'presentation', text: item.heading }),
                );
                continue;
            }

            if (item.separator) {
                children.push(h(document, 'div', { class: 'pbe-menu__separator', role: 'separator' }));
                continue;
            }

            const index = position++;
            const role =
                'listbox' === menu.role ? 'option' : undefined === item.checked ? 'menuitem' : 'menuitemcheckbox';
            const element = h(
                document,
                'button',
                {
                    type: 'button',
                    class:
                        'pbe-menu__item' +
                        (index === menu.index ? ' pbe-menu__item--active' : '') +
                        (item.danger ? ' pbe-menu__item--danger' : ''),
                    role,
                    id: this.menuElement.id + '-' + index,
                    tabindex: 'menu' === menu.role ? '-1' : null,
                    'aria-selected': 'option' === role ? String(index === menu.index) : null,
                    'aria-checked': 'menuitemcheckbox' === role ? String(item.checked) : null,
                    'data-index': String(index),
                },
                [
                    h(document, 'span', { class: 'pbe-menu__label', text: item.label }),
                    item.hint ? h(document, 'span', { class: 'pbe-menu__hint', text: item.hint }) : null,
                ],
            );
            element.addEventListener('mousedown', (event) => event.preventDefault());
            element.addEventListener('click', () => menu.choose(choices[index]));
            children.push(element);
        }

        if (0 === choices.length) {
            children.push(h(document, 'div', { class: 'pbe-menu__empty', text: menu.empty ?? '' }));
        }

        this.menuElement.replaceChildren(...children);

        if (!this.menuElement.isConnected) {
            this.element.append(this.menuElement);
        }

        this.place(this.menuElement, menu.anchor);
        const active = this.menuElement.querySelector('.pbe-menu__item--active');
        active?.scrollIntoView?.({ block: 'nearest' });

        if ('listbox' === menu.role) {
            const field = this.element.ownerDocument.activeElement;
            field?.setAttribute?.('aria-activedescendant', active?.id ?? '');
            field?.setAttribute?.('aria-controls', this.menuElement.id);
        } else {
            active?.focus();
        }
    }

    /** Places a popup under a box, inside the editor, above it when there is no room below. */
    place(popup, anchor) {
        const frame = this.element.getBoundingClientRect();
        const view = this.element.ownerDocument.defaultView;
        const below = anchor.bottom + popup.offsetHeight + 8 <= view.innerHeight;
        popup.style.left = Math.max(0, Math.min(anchor.left - frame.left, frame.width - popup.offsetWidth)) + 'px';
        popup.style.top =
            (below ? anchor.bottom - frame.top + 4 : anchor.top - frame.top - popup.offsetHeight - 4) + 'px';
    }

    /** The keys of an open menu; whether the menu took the key. */
    menuKey(event) {
        const choices = this.menu.items.filter((item) => undefined !== item.key);

        if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
            event.preventDefault();
            this.menu.index = moveIndex(this.menu.index, 'ArrowDown' === event.key ? 1 : -1, choices.length);
            this.drawMenu();

            return true;
        }

        if (('Enter' === event.key || 'Tab' === event.key) && choices.length > 0) {
            event.preventDefault();
            this.menu.choose(choices[this.menu.index]);

            return true;
        }

        if ('Escape' === event.key) {
            event.preventDefault();
            const back = this.menu.returnFocus;
            this.closeMenu();
            back?.focus();

            return true;
        }

        return 'block' === this.menu.kind;
    }

    closeMenu() {
        const field = this.element.ownerDocument.activeElement;
        field?.removeAttribute?.('aria-activedescendant');
        field?.removeAttribute?.('aria-controls');
        this.menuElement?.remove();
        this.menu = null;
    }

    outside(event) {
        if (null !== this.menu && !this.menuElement.contains(event.target)) {
            this.closeMenu();
        }

        if (undefined !== this.toolbar && null !== this.toolbar && !this.toolbar.contains(event.target)) {
            this.linkEditing = false;
        }
    }

    // Inline toolbar --------------------------------------------------------------------------------------------

    selectionchange() {
        if (this.linkEditing) {
            return;
        }

        const selection = this.element.ownerDocument.getSelection();
        const node = selection?.anchorNode;
        const field =
            null === node || undefined === node
                ? null
                : (3 === node.nodeType ? node.parentElement : node).closest?.('.pbe-rich[contenteditable]');

        if (
            null === field ||
            undefined === field ||
            !this.canvasTarget.contains(field) ||
            selection.isCollapsed ||
            null === rangeIn(field)
        ) {
            this.hideToolbar();

            return;
        }

        this.showToolbar(field, false);
    }

    showToolbar(field, link) {
        const document = this.element.ownerDocument;
        const tools = TOOLBAR.filter((name) => this.inlineTools.includes(name)).map((name) =>
            MARKS.find((tool) => tool.name === name),
        );

        if (0 === tools.length) {
            return;
        }

        const range = rangeIn(field)?.cloneRange();
        const active = activeMarks(field, tools);
        this.toolbar ??= h(document, 'div', { class: 'pbe-toolbar', role: 'toolbar', 'aria-label': this.t('editor') });
        const buttons = tools.map((tool) => {
            const button = h(document, 'button', {
                type: 'button',
                class: 'pbe-toolbar__button pbe-toolbar__button--' + tool.name,
                'aria-pressed':
                    'textColor' === tool.name || 'link' === tool.name ? null : String(active.includes(tool.name)),
                'aria-label': this.t(tool.name),
                title: this.t(tool.name),
                'data-mark': tool.name,
            });
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => this.markClicked(field, tool, range));

            return button;
        });

        this.toolbar.replaceChildren(...buttons);

        if (link) {
            this.toolbar.append(this.linkForm(field, range));
        }

        if (!this.toolbar.isConnected) {
            this.element.append(this.toolbar);
        }

        const box = caretRect(field);
        this.place(this.toolbar, {
            left: box.left,
            right: box.right,
            bottom: box.top - this.toolbar.offsetHeight - 8,
            top: box.top,
        });
    }

    hideToolbar() {
        this.toolbar?.remove();
    }

    restore(field, range) {
        if (undefined === range) {
            return;
        }

        field.focus();
        const selection = field.ownerDocument.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    markClicked(field, tool, range) {
        this.restore(field, range);

        if ('link' === tool.name) {
            this.showToolbar(field, true);

            return;
        }

        if ('textColor' === tool.name) {
            this.toolbar.querySelector('.pbe-palette')?.remove();
            this.toolbar.append(this.palette(field, range));

            return;
        }

        this.remember();
        toggleMark(field, tool);
        this.input({ target: field });
        this.showToolbar(field, false);
    }

    linkForm(field, range) {
        const document = this.element.ownerDocument;
        const tool = MARKS.find((mark) => 'link' === mark.name);
        const current = null === range || undefined === range ? null : range.commonAncestorContainer;
        const existing =
            null === current ? null : (3 === current.nodeType ? current.parentElement : current).closest('a[href]');
        const input = h(document, 'input', {
            type: 'url',
            class: 'pbe-toolbar__url',
            placeholder: this.t('linkPlaceholder'),
            'aria-label': this.t('link'),
            value: existing?.getAttribute('href') ?? '',
        });
        const message = h(document, 'span', { class: 'pbe-toolbar__message', role: 'alert' });
        const apply = () => {
            const href = input.value.trim();

            if (!isSafeUrl(href)) {
                message.textContent = this.t('linkInvalid');

                return;
            }

            this.linkEditing = false;
            this.restore(field, range);
            this.remember();
            toggleMark(field, tool, { href });
            this.input({ target: field });
            this.hideToolbar();
        };
        const form = h(document, 'div', { class: 'pbe-toolbar__link' }, [
            input,
            h(document, 'button', { type: 'button', class: 'pbe-toolbar__apply', text: this.t('linkApply') }),
            h(document, 'button', { type: 'button', class: 'pbe-toolbar__remove', text: this.t('linkRemove') }),
            message,
        ]);
        form.querySelector('.pbe-toolbar__apply').addEventListener('click', apply);
        form.querySelector('.pbe-toolbar__remove').addEventListener('click', () => {
            this.linkEditing = false;
            this.restore(field, range);
            this.remember();
            toggleMark(field, tool, {}, { remove: true });
            this.input({ target: field });
            this.hideToolbar();
        });
        input.addEventListener('keydown', (event) => {
            if ('Enter' === event.key) {
                event.preventDefault();
                apply();
            } else if ('Escape' === event.key) {
                event.preventDefault();
                this.linkEditing = false;
                this.restore(field, range);
                this.hideToolbar();
            }
        });
        this.linkEditing = true;
        setTimeout(() => input.focus(), 0);

        return form;
    }

    palette(field, range) {
        const document = this.element.ownerDocument;
        const tool = MARKS.find((mark) => 'textColor' === mark.name);
        const choose = (token) => {
            this.restore(field, range);
            this.remember();
            toggleMark(field, tool, null === token ? {} : { 'data-color': token }, { remove: null === token });
            this.input({ target: field });
            this.hideToolbar();
        };
        const swatches = [null, ...this.colors].map((token) => {
            const button = h(document, 'button', {
                type: 'button',
                class: 'pbe-palette__swatch text-color',
                'data-color': token,
                'aria-label': null === token ? this.t('defaultColor') : this.t('color.' + token),
                title: null === token ? this.t('defaultColor') : this.t('color.' + token),
                text: 'A',
            });
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => choose(token));

            return button;
        });

        return h(document, 'div', { class: 'pbe-palette', role: 'group', 'aria-label': this.t('textColor') }, swatches);
    }

    // Paste -----------------------------------------------------------------------------------------------------

    paste(event) {
        const at = this.where(event.target);
        const field = at?.field;

        if (null === at || null === field || !field.isContentEditable) {
            return;
        }

        const data = event.clipboardData;
        const image = Array.from(data?.files ?? []).find((file) => file.type.startsWith('image/'));
        event.preventDefault();

        if (undefined !== image && null !== this.uploadUrl) {
            const fresh = this.block('image');
            this.transact(() => {
                const found = locate(this.document.blocks, at.block.id);
                found.list.splice(found.index + 1, 0, fresh);
            });
            this.upload(fresh.id, image);

            return;
        }

        const html = data?.getData('text/html') ?? '';
        const text = data?.getData('text/plain') ?? '';
        const range = rangeIn(field);

        if (
            '' === html &&
            isSafeUrl(text.trim()) &&
            /^[a-z]+:/i.test(text.trim()) &&
            null !== range &&
            !range.collapsed &&
            this.inlineTools.includes('link')
        ) {
            this.remember();
            toggleMark(
                field,
                MARKS.find((mark) => 'link' === mark.name),
                { href: text.trim() },
            );
            this.input({ target: field });

            return;
        }

        const options = { tools: this.tools, inlineTools: this.inlineTools, makeId: newId };
        const blocks = '' === html ? textBlocks(text, options) : pasteBlocks(html, options);
        const inline =
            undefined !== field.dataset.itemField || undefined !== field.dataset.cell || 'text' !== field.dataset.field;

        if (0 === blocks.length) {
            return;
        }

        if (inline || (1 === blocks.length && 'paragraph' === blocks[0].type)) {
            const joined = blocks
                .map((block) => (holdsText(block.type) ? convert(block, 'paragraph')[0].data.text : ''))
                .filter((part) => !isBlank(part))
                .join(this.inlineTools.includes('lineBreak') ? '<br>' : ' ');
            this.remember();
            insertHtml(field, sanitize(joined, this.marks));
            this.input({ target: field });

            return;
        }

        this.remember();
        const after = sanitize(splitAtCaret(field), this.marks).replace(/(?:<br>)+$/, '');
        this.read(at.block, field);
        const found = locate(this.document.blocks, at.block.id);
        const replace = 'paragraph' === at.block.type && isBlank(at.block.data.text);
        const tail = isBlank(after) ? [] : [{ id: newId(), type: 'paragraph', data: { text: after } }];
        found.list.splice(found.index + (replace ? 0 : 1), replace ? 1 : 0, ...blocks, ...tail);
        const last = blocks.at(-1);
        this.render({ id: last.id, where: 'end' });
        this.save();
    }

    // Images ----------------------------------------------------------------------------------------------------

    insertImageUrl(block) {
        const input = this.blockElement(block.id).querySelector('[data-image-url]');
        const url = input.value.trim();

        if (!isSafeUrl(url, { media: true })) {
            input.setAttribute('aria-invalid', 'true');
            input.focus();

            return;
        }

        this.transact(
            () => {
                block.data.file = { url };
            },
            { id: block.id, selector: '[data-field="caption"]' },
        );
    }

    /**
     * The headers of a request to the bundle's endpoints: the editor's own and, for a request that changes something,
     * a new CSRF token. A `GET` search only reads, so it carries none.
     */
    headers(changes = true) {
        return changes ? { ...HEADER, ...csrfHeaders(this.csrfCookie, this.element.ownerDocument) } : { ...HEADER };
    }

    upload(id, file) {
        if (null === this.uploadUrl) {
            return;
        }

        const body = new FormData();
        body.append('image', file);
        this.uploading.add(id);
        this.render();
        this.announce(this.t('uploading'));

        fetch(this.uploadUrl, { method: 'POST', body, headers: this.headers(), credentials: 'same-origin' })
            .then((response) =>
                response.json().then((answer) => ({ ok: response.ok, status: response.status, answer })),
            )
            .then(({ ok, status, answer }) => {
                const found = locate(this.document.blocks, id);

                if (
                    ok &&
                    1 === answer?.success &&
                    'string' === typeof answer.file?.url &&
                    isSafeUrl(answer.file.url, { media: true }) &&
                    null !== found
                ) {
                    this.remember();
                    found.block.data.file = answer.file;
                    this.save();
                    this.announce('');
                } else {
                    this.announce(
                        422 === status && 'string' === typeof answer?.message ? answer.message : this.t('uploadFailed'),
                    );
                }
            })
            .catch(() => this.announce(this.t('uploadFailed')))
            .finally(() => {
                this.uploading.delete(id);
                this.render(
                    null !== locate(this.document.blocks, id) ? { id, selector: '[data-field="caption"]' } : null,
                );
            });
    }

    // Drag and drop ---------------------------------------------------------------------------------------------

    dragstart(event) {
        const handle = event.target.closest?.('.pbe-handle');

        if (null === handle || undefined === handle) {
            return;
        }

        const element = handle.closest('.pbe-block');
        this.dragging = element.dataset.id;
        element.classList.add('pbe-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('application/x-phil-block', this.dragging);
        event.dataTransfer.setData('text/plain', textOf(this.field(this.dragging)?.innerHTML ?? ''));
        event.dataTransfer.setDragImage?.(element, 0, 0);
        this.closeMenu();
    }

    /** Where a dragged block would land: before or after a block, or into an empty list. */
    dropTarget(event) {
        const empty = event.target.closest?.('.pbe-empty');

        if (null !== empty && undefined !== empty) {
            return { key: empty.dataset.empty, index: 0, element: empty, position: 'into' };
        }

        const element = event.target.closest?.('.pbe-block');

        if (null === element || undefined === element || element.closest('.pbe-dragging')) {
            return null;
        }

        const found = locate(this.document.blocks, element.dataset.id);
        const box = element.getBoundingClientRect();
        const after = event.clientY > box.top + box.height / 2;

        return { key: found.key, index: found.index + (after ? 1 : 0), element, position: after ? 'after' : 'before' };
    }

    dragover(event) {
        const files = Array.from(event.dataTransfer?.types ?? []).includes('Files');

        if (files && null !== event.target.closest?.('[data-drop]')) {
            event.preventDefault();

            return;
        }

        if (undefined === this.dragging || null === this.dragging) {
            return;
        }

        const target = this.dropTarget(event);
        this.clearDrop();

        if (null !== target) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            target.element.classList.add('pbe-drop-' + target.position);
        }
    }

    dragleave(event) {
        if (!this.canvasTarget.contains(event.relatedTarget)) {
            this.clearDrop();
        }
    }

    drop(event) {
        const zone = event.target.closest?.('[data-drop]');
        const file = Array.from(event.dataTransfer?.files ?? []).find((one) => one.type.startsWith('image/'));

        if (null !== zone && undefined !== zone && undefined !== file) {
            event.preventDefault();
            this.upload(zone.dataset.drop, file);

            return;
        }

        const target = undefined === this.dragging || null === this.dragging ? null : this.dropTarget(event);

        if (null === target) {
            return;
        }

        event.preventDefault();
        const id = this.dragging;
        this.remember();

        if (moveTo(this.document.blocks, id, target.key, target.index)) {
            this.render();
            this.selectBlock(id);
            this.save();
            this.announce(this.t('moved'));
        }

        this.dragend();
    }

    dragend() {
        this.clearDrop();
        this.canvasTarget.querySelectorAll('.pbe-dragging').forEach((element) => {
            element.classList.remove('pbe-dragging');
        });
        this.dragging = null;
    }

    clearDrop() {
        this.canvasTarget.querySelectorAll('.pbe-drop-before, .pbe-drop-after, .pbe-drop-into').forEach((element) => {
            element.classList.remove('pbe-drop-before', 'pbe-drop-after', 'pbe-drop-into');
        });
    }
}
