// What the tests need of Live Components, which the application provides: a component with its hooks.
export const component = {
    handlers: {},
    rendered: 0,
    on(name, handler) {
        this.handlers[name] = handler;
    },
    off(name, handler) {
        if (this.handlers[name] === handler) {
            delete this.handlers[name];
        }
    },
    render() {
        this.rendered += 1;
    },
};

export const getComponent = async () => component;
