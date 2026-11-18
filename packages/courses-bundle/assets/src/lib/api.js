// The run-time API of a SCORM session, as the content calls it: LMSInitialize()… for SCORM 1.2 on `API`, Initialize()…
// for SCORM 2004 on `API_1484_11`. Each call answers at once, as phil/elearning-packages' ScormSession does: "true" or
// "false", the value read, and the error code of the version. The hooks hand each write, commit and end to the page.

const NOT_INITIALIZED = 'not-initialized';
const RUNNING = 'running';
const TERMINATED = 'terminated';

export class ScormSession {
    #data;
    #hooks;
    #state = NOT_INITIALIZED;
    #last = { value: '', error: 0, diagnostic: '' };

    /**
     * @param data  the CmiData of the attempt
     * @param hooks onSet(element, value), onCommit() and onTerminate(), the last two answering whether they could
     */
    constructor(data, hooks = {}) {
        this.#data = data;
        this.#hooks = hooks;
    }

    get state() {
        return this.#state;
    }

    get #is12() {
        return '1.2' === this.#data.version;
    }

    initialize(argument = '') {
        if ('' !== String(argument)) {
            return this.#answer(this.#is12 ? 201 : 201);
        }

        if (RUNNING === this.#state) {
            return this.#answer(this.#is12 ? 101 : 103);
        }

        if (TERMINATED === this.#state) {
            return this.#answer(this.#is12 ? 101 : 104);
        }

        this.#state = RUNNING;

        return this.#answer(0);
    }

    terminate(argument = '') {
        if ('' !== String(argument)) {
            return this.#answer(201);
        }

        if (NOT_INITIALIZED === this.#state) {
            return this.#answer(this.#is12 ? 301 : 112);
        }

        if (TERMINATED === this.#state) {
            return this.#answer(this.#is12 ? 301 : 113);
        }

        if (this.#hooks.onTerminate && false === this.#hooks.onTerminate()) {
            return this.#answer(this.#is12 ? 101 : 111);
        }

        this.#state = TERMINATED;

        return this.#answer(0);
    }

    getValue(element) {
        const refused = this.#refused(122, 123);
        this.#last = refused ?? this.#data.get(String(element));

        return this.#last.value;
    }

    setValue(element, value) {
        const refused = this.#refused(132, 133);

        if (refused) {
            this.#last = refused;

            return 'false';
        }

        const result = this.#data.set(String(element), value);
        this.#last = result;

        if (0 !== result.error) {
            return 'false';
        }

        this.#hooks.onSet?.(String(element), String(value));

        return 'true';
    }

    commit(argument = '') {
        if ('' !== String(argument)) {
            return this.#answer(201);
        }

        const refused = this.#refused(142, 143);

        if (refused) {
            this.#last = refused;

            return 'false';
        }

        if (this.#hooks.onCommit && false === this.#hooks.onCommit()) {
            return this.#answer(this.#is12 ? 101 : 391);
        }

        return this.#answer(0);
    }

    getLastError() {
        return String(this.#last.error);
    }

    getErrorString(code) {
        return /^\d{1,3}$/.test(String(code)) ? this.#data.message(Number(code)) : '';
    }

    getDiagnostic(code = '') {
        if ('' === String(code) || String(this.#last.error) === String(code)) {
            return this.#last.diagnostic;
        }

        return this.getErrorString(code);
    }

    /** The object the content finds, with the names of its version. */
    api() {
        if (this.#is12) {
            return {
                LMSInitialize: (argument) => this.initialize(argument),
                LMSFinish: (argument) => this.terminate(argument),
                LMSGetValue: (element) => this.getValue(element),
                LMSSetValue: (element, value) => this.setValue(element, value),
                LMSCommit: (argument) => this.commit(argument),
                LMSGetLastError: () => this.getLastError(),
                LMSGetErrorString: (code) => this.getErrorString(code),
                LMSGetDiagnostic: (code) => this.getDiagnostic(code),
            };
        }

        return {
            version: '1.0',
            Initialize: (argument) => this.initialize(argument),
            Terminate: (argument) => this.terminate(argument),
            GetValue: (element) => this.getValue(element),
            SetValue: (element, value) => this.setValue(element, value),
            Commit: (argument) => this.commit(argument),
            GetLastError: () => this.getLastError(),
            GetErrorString: (code) => this.getErrorString(code),
            GetDiagnostic: (code) => this.getDiagnostic(code),
        };
    }

    #refused(before, after) {
        if (NOT_INITIALIZED === this.#state) {
            return { value: '', error: this.#is12 ? 301 : before, diagnostic: 'The session is not initialized.' };
        }

        if (TERMINATED === this.#state) {
            return { value: '', error: this.#is12 ? 301 : after, diagnostic: 'The session is terminated.' };
        }

        return null;
    }

    #answer(error) {
        this.#last = { value: '', error, diagnostic: '' };

        return 0 === error ? 'true' : 'false';
    }
}
