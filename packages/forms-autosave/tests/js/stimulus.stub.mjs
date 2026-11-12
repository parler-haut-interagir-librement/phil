// What the tests need of Stimulus, which the application provides: a base class whose values are set by the test and
// whose events are recorded.
export class Controller {
    constructor(element, values = {}) {
        this.element = element;
        this.dispatched = [];
        Object.assign(this, values);
    }

    dispatch(name, options = {}) {
        this.dispatched.push([name, options.detail ?? null]);
    }
}
