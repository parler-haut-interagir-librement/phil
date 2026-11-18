// A few elements, as much of the DOM as the controllers touch: classes, attributes, children, text and a selector
// that finds descendants by one class or by heading.
export class FakeClassList {
    constructor(names = []) {
        this.names = new Set(names);
    }

    add(...names) {
        for (const name of names) {
            this.names.add(name);
        }
    }

    remove(...names) {
        for (const name of names) {
            this.names.delete(name);
        }
    }

    toggle(name, force) {
        const on = force ?? !this.names.has(name);

        if (on) {
            this.names.add(name);
        } else {
            this.names.delete(name);
        }

        return on;
    }

    contains(name) {
        return this.names.has(name);
    }
}

export class FakeElement {
    constructor(tagName = 'DIV', { classes = [], attributes = {}, children = [], text = '' } = {}) {
        this.tagName = tagName;
        this.classList = new FakeClassList(classes);
        this.attributes = new Map(Object.entries(attributes));
        this.children = children;
        this.ownText = text;
        this.hidden = false;
        this.dataset = {};
        this.isContentEditable = false;
    }

    get id() {
        return this.attributes.get('id') ?? '';
    }

    get textContent() {
        return this.ownText + this.children.map((child) => child.textContent).join('');
    }

    set textContent(value) {
        this.ownText = value;
        this.children = [];
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    descendants() {
        return this.children.flatMap((child) => [child, ...child.descendants()]);
    }

    querySelectorAll(selector) {
        if (selector === '*') {
            return this.descendants();
        }

        if (selector.startsWith('.')) {
            return this.descendants().filter((element) => element.classList.contains(selector.slice(1)));
        }

        const tags = selector.split(',').map((tag) => tag.trim().toUpperCase());

        return this.descendants().filter((element) => tags.includes(element.tagName));
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    contains(element) {
        return element === this || this.descendants().includes(element);
    }

    cloneNode() {
        const copy = new FakeElement(this.tagName, {
            classes: [...this.classList.names],
            attributes: Object.fromEntries(this.attributes),
            children: this.children.map((child) => child.cloneNode(true)),
            text: this.ownText,
        });

        return copy;
    }

    replaceChildren(...children) {
        this.children = children;
    }
}

// A slide with a heading and fragments.
export function slide(number, { title = `Title ${number}`, fragments = 0 } = {}) {
    const children = [new FakeElement('H2', { text: title, attributes: { id: `h${number}` } })];

    for (let index = 0; index < fragments; index += 1) {
        children.push(new FakeElement('DIV', { classes: ['fragment'], text: `F${index}` }));
    }

    return new FakeElement('SECTION', { attributes: { id: `slide-${number}`, tabindex: '-1', 'data-x-target': 'slide' }, children });
}

// The channels opened, by name, each recording what was sent.
export class FakeChannel {
    static opened = [];

    constructor(name) {
        this.name = name;
        this.sent = [];
        this.closed = false;
        FakeChannel.opened.push(this);
    }

    postMessage(message) {
        this.sent.push(message);
    }

    close() {
        this.closed = true;
    }
}
