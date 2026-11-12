// A small stand-in for the DOM: elements with attributes, children, events and `closest()`/`querySelector()` on
// attribute selectors, enough for the controller and its modules.

const SELECTOR = /^\[([\w-]+)(?:(~?=)"([^"]*)")?\]$/;

function matches(element, selector) {
    const [, name, operator, value] = selector.match(SELECTOR) ?? [];

    if (undefined === name || !element.attributes.has(name)) {
        return false;
    }

    const actual = element.attributes.get(name);

    return undefined === operator ? true : '=' === operator ? actual === value : actual.split(/\s+/).includes(value);
}

export class FakeElement {
    constructor(tag, attributes = {}, properties = {}) {
        this.tagName = tag.toUpperCase();
        this.attributes = new Map(Object.entries(attributes));
        this.children = [];
        this.parent = null;
        this.listeners = new Map();
        this.events = [];
        this.hidden = false;
        this.textContent = '';
        Object.assign(this, properties);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    append(...children) {
        for (const child of children) {
            child.parent = this;
            this.children.push(child);
        }
    }

    prepend(...children) {
        for (const child of children.reverse()) {
            child.parent = this;
            this.children.unshift(child);
        }
    }

    closest(selector) {
        for (let element = this; null !== element; element = element.parent) {
            if (matches(element, selector)) {
                return element;
            }
        }

        return null;
    }

    querySelector(selector) {
        for (const child of this.children) {
            if (matches(child, selector)) {
                return child;
            }

            const found = child.querySelector(selector);

            if (null !== found) {
                return found;
            }
        }

        return null;
    }

    addEventListener(type, handler) {
        this.listeners.set(type, [...(this.listeners.get(type) ?? []), handler]);
    }

    removeEventListener(type, handler) {
        this.listeners.set(
            type,
            (this.listeners.get(type) ?? []).filter((listener) => listener !== handler),
        );
    }

    dispatchEvent(event) {
        this.events.push(event.type);

        for (let element = this; null !== element; element = event.bubbles ? element.parent : null) {
            for (const handler of element.listeners.get(event.type) ?? []) {
                handler(event);
            }
        }
    }

    emit(type) {
        for (const handler of this.listeners.get(type) ?? []) {
            handler({ type });
        }
    }
}

/** A field: an input, a select or a textarea, with its attributes and its properties. */
export function field(type, name, properties = {}, attributes = {}) {
    return new FakeElement('input', { name, ...attributes }, { type, name, value: '', disabled: false, ...properties });
}

/** A form holding fields, its `elements` its fields in order. */
export function form(fields, attributes = {}) {
    const element = new FakeElement('form', attributes);
    element.append(...fields);
    Object.defineProperty(element, 'elements', { get: () => fields });

    return element;
}

export class FakeStorage {
    constructor(entries = {}) {
        this.entries = new Map(Object.entries(entries));
    }

    getItem(key) {
        return this.entries.get(key) ?? null;
    }

    setItem(key, value) {
        this.entries.set(key, value);
    }

    removeItem(key) {
        this.entries.delete(key);
    }
}

export const fakeDocument = {
    createElement: (tag) => new FakeElement(tag),
};
