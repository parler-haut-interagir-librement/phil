// The parts of a browser the controllers read, as plain objects a test sets: window and its events, navigator, the
// document, and the element of a controller with its attributes.

export class Events {
    listeners = {};

    addEventListener(type, listener, options = {}) {
        this.listeners[type] = [...(this.listeners[type] ?? []), { listener, once: options.once === true }];
    }

    removeEventListener(type, listener) {
        this.listeners[type] = (this.listeners[type] ?? []).filter((entry) => entry.listener !== listener);
    }

    emit(type, event = {}) {
        for (const entry of [...(this.listeners[type] ?? [])]) {
            if (entry.once) {
                this.removeEventListener(type, entry.listener);
            }

            entry.listener(event);
        }
    }

    count(type) {
        return (this.listeners[type] ?? []).length;
    }
}

export class Element {
    attributes = new Set();

    toggleAttribute(name, force) {
        if (force) {
            this.attributes.add(name);
        } else {
            this.attributes.delete(name);
        }
    }
}

export function install(globals) {
    for (const [name, value] of Object.entries(globals)) {
        Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
    }
}

export function target() {
    return { hidden: true };
}
