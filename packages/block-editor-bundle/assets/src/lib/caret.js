// The caret and the selection inside an editable field: where the caret is, putting it back, splitting a field at
// it, inserting HTML, and adding or removing an inline mark around the selection. These need a browser.

import { match } from './inline.js';

function selection(field) {
    return field.ownerDocument.getSelection();
}

/** The selection's first range when it lies in the field, else null. */
export function rangeIn(field) {
    const current = selection(field);

    if (null === current || 0 === current.rangeCount) {
        return null;
    }

    const range = current.getRangeAt(0);

    return field.contains(range.startContainer) && field.contains(range.endContainer) ? range : null;
}

function rangeTo(field, range, toEnd) {
    const part = field.ownerDocument.createRange();
    part.selectNodeContents(field);

    if (toEnd) {
        part.setStart(range.endContainer, range.endOffset);
    } else {
        part.setEnd(range.startContainer, range.startOffset);
    }

    return part;
}

function showsNothing(range) {
    return '' === range.toString() && null === range.cloneContents().querySelector('br, img');
}

/** Whether the caret, collapsed, is at the very start of the field. */
export function atStart(field) {
    const range = rangeIn(field);

    return null !== range && range.collapsed && showsNothing(rangeTo(field, range, false));
}

/** Whether the caret, collapsed, is at the very end of the field. */
export function atEnd(field) {
    const range = rangeIn(field);

    return null !== range && range.collapsed && showsNothing(rangeTo(field, range, true));
}

/** The text of the field before the caret. */
export function textBefore(field) {
    const range = rangeIn(field);

    return null === range ? '' : rangeTo(field, range, false).toString();
}

/** Whether the caret is on the field's first line (`up`) or its last (`down`), by where it is drawn. */
export function onEdgeLine(field, up) {
    const range = rangeIn(field);

    if (null === range) {
        return false;
    }

    const rects = range.getClientRects();
    const box = field.getBoundingClientRect();
    const line = parseFloat(field.ownerDocument.defaultView.getComputedStyle(field).lineHeight) || 20;

    if (0 === rects.length) {
        return up ? atStart(field) : atEnd(field);
    }

    return up ? rects[0].top - box.top < line * 0.8 : box.bottom - rects[rects.length - 1].bottom < line * 0.8;
}

/** Puts the caret at the start, the end, or after a number of characters of the field, and focuses it. */
export function placeCaret(field, where = 'end') {
    field.focus({ preventScroll: false });

    if ('TEXTAREA' === field.tagName || 'INPUT' === field.tagName) {
        const at = 'start' === where ? 0 : 'end' === where ? field.value.length : Math.min(where, field.value.length);
        field.setSelectionRange(at, at);

        return;
    }

    const range = field.ownerDocument.createRange();

    if ('number' === typeof where) {
        const walker = field.ownerDocument.createTreeWalker(field, NodeFilter.SHOW_TEXT);
        let left = where;

        for (let node = walker.nextNode(); null !== node; node = walker.nextNode()) {
            if (node.length >= left) {
                range.setStart(node, left);
                range.collapse(true);
                select(field, range);

                return;
            }

            left -= node.length;
        }
    }

    range.selectNodeContents(field);
    range.collapse('start' === where);
    select(field, range);
}

function select(field, range) {
    const current = selection(field);
    current.removeAllRanges();
    current.addRange(range);
}

/**
 * Splits the field at the caret, the selection deleted first: the field keeps what was before it, and the HTML of
 * what followed is returned.
 */
export function splitAtCaret(field) {
    const range = rangeIn(field);

    if (null === range) {
        return '';
    }

    range.deleteContents();
    const after = rangeTo(field, range, true).extractContents();
    const holder = field.ownerDocument.createElement('div');
    holder.append(after);

    return holder.innerHTML;
}

/** The text node and offset just before the caret, skipping the empty ones; null at the start of the field. */
function pointBefore(field, range) {
    if (3 === range.startContainer.nodeType && range.startOffset > 0) {
        return [range.startContainer, range.startOffset];
    }

    const before = rangeTo(field, range, false);
    const walker = field.ownerDocument.createTreeWalker(field, NodeFilter.SHOW_TEXT);
    let last = null;
    for (
        let text = walker.nextNode();
        null !== text && text !== range.startContainer && before.intersectsNode(text);
        text = walker.nextNode()
    ) {
        last = text.length > 0 ? text : last;
    }

    return null === last ? null : [last, last.length];
}

/** Removes the characters typed just before the caret: the trigger of a menu and its query. */
export function deleteBefore(field, length) {
    const range = rangeIn(field);
    const point = null === range || length <= 0 ? null : pointBefore(field, range);

    if (null === point) {
        return;
    }

    const [node, offset] = point;
    const doomed = field.ownerDocument.createRange();
    doomed.setStart(node, Math.max(0, offset - length));
    doomed.setEnd(node, offset);
    doomed.deleteContents();
    doomed.collapse(true);
    select(field, doomed);
    deleteBefore(field, length - Math.min(length, offset));
}

/** Inserts HTML at the caret, the selection deleted first, and puts the caret after it. */
export function insertHtml(field, html) {
    const range =
        rangeIn(field) ??
        (() => {
            const end = field.ownerDocument.createRange();
            end.selectNodeContents(field);
            end.collapse(false);

            return end;
        })();
    range.deleteContents();
    const fragment = range.createContextualFragment(html);
    const last = fragment.lastChild;
    range.insertNode(fragment);

    if (null !== last) {
        range.setStartAfter(last);
        range.collapse(true);
        select(field, range);
    }
}

/** Inserts a line break at the caret; at the end of the field, a second one, so that the new line shows. */
export function insertLineBreak(field) {
    insertHtml(field, atEnd(field) ? '<br><br>' : '<br>');

    if (field.innerHTML.endsWith('<br><br>')) {
        const range = rangeIn(field);
        range.setStartBefore(field.lastChild);
        range.collapse(true);
        select(field, range);
    }
}

function attributesOf(element) {
    return Object.fromEntries(Array.from(element.attributes, (attribute) => [attribute.name, attribute.value]));
}

/** The element of a mark around a node, up to the field, or null. */
export function enclosing(node, field, tool) {
    for (
        let current = 3 === node.nodeType ? node.parentNode : node;
        null !== current && current !== field;
        current = current.parentNode
    ) {
        if (1 === current.nodeType && null !== match([tool], current.tagName.toLowerCase(), attributesOf(current))) {
            return current;
        }
    }

    return null;
}

/** The marks around the selection in the field. */
export function activeMarks(field, tools) {
    const range = rangeIn(field);

    return null === range
        ? []
        : tools
              .filter((tool) => null !== enclosing(range.commonAncestorContainer, field, tool))
              .map((tool) => tool.name);
}

function unwrap(element) {
    element.replaceWith(...element.childNodes);
}

/**
 * Adds a mark around the selection, or removes it when the selection is already inside one. With attributes — a
 * link's address, a colour's token —, a mark already there takes them instead; `remove` takes it away.
 */
export function toggleMark(field, tool, attributes = {}, { remove = false } = {}) {
    const range = rangeIn(field);

    if (null === range) {
        return false;
    }

    const existing = enclosing(range.commonAncestorContainer, field, tool);

    if (null !== existing && (remove || 0 === Object.keys(attributes).length)) {
        unwrap(existing);
        field.normalize();

        return true;
    }

    if (null !== existing) {
        Object.entries(attributes).forEach(([name, value]) => {
            existing.setAttribute(name, value);
        });

        return true;
    }

    if (range.collapsed || remove) {
        return false;
    }

    const element = field.ownerDocument.createElement(tool.tag);

    if (undefined !== tool.className) {
        element.className = tool.className;
    }

    Object.entries(attributes).forEach(([name, value]) => {
        element.setAttribute(name, value);
    });
    const content = range.extractContents();
    content.querySelectorAll(tool.tag).forEach((inner) => {
        null !== match([tool], inner.tagName.toLowerCase(), attributesOf(inner)) ? unwrap(inner) : null;
    });
    element.append(content);
    range.insertNode(element);
    field.normalize();
    const around = field.ownerDocument.createRange();
    around.selectNodeContents(element);
    select(field, around);

    return true;
}

/** Where to draw a popup for the caret or the selection: its box, or the field's when it has none. */
export function caretRect(field) {
    const range = rangeIn(field);
    const rects = null === range ? [] : Array.from(range.getClientRects());

    return rects[0] ?? field.getBoundingClientRect();
}
