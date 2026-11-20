// The ProseMirror trial: a ProseMirror view inside each rich field, loaded only when the editor's configuration asks
// for `richEngine: 'prosemirror'`. The view edits the field's inline text and marks; the blocks, the menus, the
// paste of blocks, the undo history and the UJO (Universal JSON Output) written to the input stay the controller's.
// Needs a browser and the `prosemirror-model`, `prosemirror-state`, `prosemirror-transform` and `prosemirror-view`
// modules in the page's import map.

import { DOMParser, DOMSerializer, Schema } from 'prosemirror-model';
import { EditorState, Plugin } from 'prosemirror-state';
import { EditorView } from 'prosemirror-view';
import { sanitize } from './inline.js';
import { schemaSpec } from './rich-schema.js';

const schemas = new Map();

/** The schema of a set of inline tools, built once per set. */
export function schemaFor(tools) {
    const key = tools.map((tool) => tool.name).join(' ');

    if (!schemas.has(key)) {
        schemas.set(key, new Schema(schemaSpec(tools)));
    }

    return schemas.get(key);
}

/** Inline HTML read into a document of the schema, through an inert template: nothing loads, nothing runs. */
export function parseHtml(schema, html, document) {
    const template = document.createElement('template');
    template.innerHTML = html;

    return DOMParser.fromSchema(schema).parse(template.content);
}

/** Inline HTML read into a slice of the schema, to insert at the selection. */
export function parseSlice(schema, html, document) {
    const template = document.createElement('template');
    template.innerHTML = html;

    return DOMParser.fromSchema(schema).parseSlice(template.content);
}

/** A document of the schema written as inline HTML, through the sanitizer, in phil/ujo's canonical form. */
export function toHtml(doc, tools, document) {
    const holder = document.createElement('div');
    holder.append(DOMSerializer.fromSchema(doc.type.schema).serializeFragment(doc.content, { document }));

    return sanitize(holder.innerHTML, tools).replace(/(?:<br>)+$/, '');
}

/** The meta of a transaction the controller makes itself, which is not reported back to it. */
const QUIET = 'pbe-quiet';

/** Marks the field empty, so that the stylesheet shows its placeholder. */
const emptiness = new Plugin({
    props: {
        attributes: (state) => (0 === state.doc.content.size ? { class: 'pbe-rich-empty' } : {}),
    },
});

/**
 * Puts a ProseMirror view in a rich field, the field itself becoming the view's editable element. `changed(field)`
 * is called after each change of the text or its marks. A paste goes to the controller, which turns it into blocks.
 */
export function mount(field, tools, changed) {
    const schema = schemaFor(tools);
    const doc = parseHtml(schema, field.innerHTML, field.ownerDocument);
    field.replaceChildren();
    const view = new EditorView(
        { mount: field },
        {
            state: EditorState.create({ doc, plugins: [emptiness] }),
            handleDOMEvents: { paste: () => true },
            dispatchTransaction(transaction) {
                view.updateState(view.state.apply(transaction));

                if (transaction.docChanged && true !== transaction.getMeta(QUIET)) {
                    changed(field);
                }
            },
        },
    );

    return view;
}

/** The range of a mark around the caret, to change a link's address with the caret in it. */
function markRange($pos, type) {
    const parent = $pos.parent;
    let index = $pos.index();
    let start = $pos.start();

    if (index === parent.childCount || !type.isInSet(parent.child(index).marks)) {
        if (0 === index || !type.isInSet(parent.child(index - 1).marks)) {
            return null;
        }

        index -= 1;
    }

    for (let at = 0; at < index; at++) {
        start += parent.child(at).nodeSize;
    }

    let from = start;
    let to = start + parent.child(index).nodeSize;

    for (let at = index - 1; at >= 0 && type.isInSet(parent.child(at).marks); at--) {
        from -= parent.child(at).nodeSize;
    }

    for (let at = index + 1; at < parent.childCount && type.isInSet(parent.child(at).marks); at++) {
        to += parent.child(at).nodeSize;
    }

    return { from, to };
}

/**
 * Adds a mark to the selection, or removes it when the selection already has it; with attributes, a mark already
 * there takes them instead; `remove` takes it away. At a collapsed caret, the mark applies to what is typed next.
 */
export function toggleMark(view, tool, attributes = {}, { remove = false } = {}) {
    const state = view.state;
    const type = state.schema.marks[tool.name];

    if (undefined === type) {
        return false;
    }

    const { empty, from, to, $from } = state.selection;
    const around = empty ? markRange($from, type) : null;
    const has = empty
        ? null !== around || undefined !== type.isInSet(state.storedMarks ?? $from.marks())
        : state.doc.rangeHasMark(from, to, type);
    const withAttributes = 0 !== Object.keys(attributes).length;
    const range = null !== around ? around : { from, to };
    let transaction = state.tr;

    if (has && (remove || !withAttributes)) {
        transaction =
            empty && null === around
                ? transaction.removeStoredMark(type)
                : transaction.removeMark(range.from, range.to, type);
    } else if (has) {
        transaction = transaction
            .removeMark(range.from, range.to, type)
            .addMark(range.from, range.to, type.create(attributes));
    } else if (remove) {
        return false;
    } else if (empty) {
        transaction = transaction.addStoredMark(type.create(attributes));
    } else {
        transaction = transaction.addMark(from, to, type.create(attributes));
    }

    view.dispatch(transaction);
    view.focus();

    return true;
}

/** Inserts inline HTML at the selection, through the schema. */
export function insertHtml(view, html) {
    view.dispatch(
        view.state.tr.replaceSelection(parseSlice(view.state.schema, html, view.dom.ownerDocument)).scrollIntoView(),
    );
}

/** Inserts a line break at the selection, when the schema has one. */
export function insertLineBreak(view) {
    const type = view.state.schema.nodes.hard_break;

    if (undefined !== type) {
        view.dispatch(view.state.tr.replaceSelectionWith(type.create()).scrollIntoView());
    }
}

/** Deletes the characters before the caret, a trigger and its query once a menu's choice replaces them. */
export function deleteBefore(view, length) {
    const at = view.state.selection.from;
    view.dispatch(view.state.tr.delete(Math.max(0, at - length), at));
}

/**
 * Splits the field at the caret, the selection deleted first: the field keeps what was before it, and the HTML of
 * what followed is returned. The change is not reported: the controller reads the field and draws the blocks again.
 */
export function splitAtCaret(view, tools) {
    const { from, to } = view.state.selection;
    const doc = view.state.doc;
    const after = toHtml(doc.cut(to), tools, view.dom.ownerDocument);
    view.dispatch(view.state.tr.delete(from, doc.content.size).setMeta(QUIET, true));

    return after;
}
