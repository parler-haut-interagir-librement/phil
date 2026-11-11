// A small stand-in for the DOM and for what Stimulus adds to a controller, for the controllers' tests.
export class FakeElement {
    constructor({
        attributes = {},
        dataset = {},
        tagName = 'DIV',
        rect = { top: 0, left: 0, right: 0, bottom: 0 },
        children = [],
    } = {}) {
        this.attributes = new Map(Object.entries(attributes));
        this.dataset = { ...dataset };
        this.tagName = tagName;
        this.rect = rect;
        this.children = children;
        this.style = {
            setProperty(name, value) {
                this[name] = value;
            },
        };
        this.hidden = false;
        this.disabled = false;
        this.tabIndex = 0;
        this.offsetWidth = 100;
        this.offsetHeight = 40;
        this.popoverOpen = false;
        this.calls = [];
        this.parentMenu = null;
        this.parentDetails = null;
        this.textContent = '';
        this.classes = new Set();
        this.classList = {
            toggle: (name, on) => (on ? this.classes.add(name) : this.classes.delete(name)),
            contains: (name) => this.classes.has(name),
            add: (...names) => names.forEach((name) => this.classes.add(name)),
            remove: (...names) => names.forEach((name) => this.classes.delete(name)),
        };
    }

    getAttribute(name) {
        return this.attributes.has(name) ? this.attributes.get(name) : null;
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    hasAttribute(name) {
        return this.attributes.has(name);
    }

    getBoundingClientRect() {
        return this.rect;
    }

    focus(options) {
        FakeElement.focused = this;
        this.calls.push(['focus', options]);
    }

    click() {
        this.calls.push(['click']);
    }

    matches(selector) {
        return selector === ':popover-open' && this.popoverOpen;
    }

    showPopover() {
        this.popoverOpen = true;
        this.calls.push(['showPopover']);
    }

    hidePopover() {
        this.popoverOpen = false;
        this.calls.push(['hidePopover']);
    }

    closest(selector) {
        if (selector === 'details') {
            return this.parentDetails;
        }

        if (selector === '[role="menu"]') {
            return this.parentMenu;
        }

        return selector === '[role^="menuitem"]' ? this : null;
    }

    querySelector(selector) {
        return this.selectors?.[selector] ?? null;
    }

    querySelectorAll(selector) {
        return this.selectorsAll?.[selector] ?? [];
    }

    contains(element) {
        return element === this || this.children.includes(element);
    }

    dispatchEvent(event) {
        this.calls.push(['dispatchEvent', event.type]);
    }
}

const camel = (name) => name[0].toUpperCase() + name.slice(1);

/**
 * A controller as Stimulus gives it: its targets, its values (their callbacks called on change) and `dispatch`.
 */
export function stimulate(Controller, { element = new FakeElement(), targets = {}, values = {} } = {}) {
    const controller = new Controller();
    controller.element = element;
    controller.dispatched = [];
    controller.dispatch = (name, options) => controller.dispatched.push([name, options]);

    for (const [name, list] of Object.entries(targets)) {
        Object.defineProperty(controller, `${name}Targets`, { get: () => list });
        Object.defineProperty(controller, `${name}Target`, { get: () => list[0] });
        Object.defineProperty(controller, `has${camel(name)}Target`, { get: () => list.length > 0 });
    }

    for (const [name, initial] of Object.entries(values)) {
        let current = initial;
        Object.defineProperty(controller, `${name}Value`, {
            get: () => current,
            set: (value) => {
                if (value !== current) {
                    current = value;
                    controller[`${name}ValueChanged`]?.();
                }
            },
        });
    }

    return controller;
}

/** The globals the controllers read, with the listeners they add to the window and the document. */
export function browser({ width = 1000, height = 800, direction = 'ltr', reduced = false, hidden = false } = {}) {
    const listeners = { window: new Map(), document: new Map() };
    const target = (where) => ({
        addEventListener: (type, listener) => listeners[where].set(type, listener),
        removeEventListener: (type) => listeners[where].delete(type),
    });

    globalThis.window = {
        ...target('window'),
        matchMedia: () => ({ matches: reduced }),
        dispatchEvent: (event) => (globalThis.lastWindowEvent = event),
    };
    globalThis.document = {
        ...target('document'),
        documentElement: { clientWidth: width, clientHeight: height },
        hidden,
        querySelector: () => null,
    };
    globalThis.getComputedStyle = () => ({ direction });
    globalThis.CustomEvent = class {
        constructor(type, options) {
            this.type = type;
            this.detail = options?.detail;
        }
    };
    globalThis.Event = class {
        constructor(type) {
            this.type = type;
        }
    };

    return listeners;
}

export const keydown = (key, target, extra = {}) => {
    const event = {
        key,
        target,
        currentTarget: target,
        prevented: false,
        preventDefault() {
            this.prevented = true;
        },
        ...extra,
    };

    return event;
};
