// What the tests need of Stimulus, which the application provides as a peer dependency: a base class whose
// `dispatch()` records what it was asked.
export class Controller {
    dispatched = [];

    dispatch(name, options) {
        this.dispatched.push({ name, ...options });
    }
}
