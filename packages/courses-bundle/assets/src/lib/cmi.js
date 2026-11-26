// The tracking data of a SCORM attempt in the browser, checked by the data model the server hands over: the same rules
// as phil/elearning-packages' CmiData, so that GetValue() and SetValue() answer at once what the server will accept.

const CODES = {
    1.2: {
        Undefined: 401,
        Unimplemented: 401,
        NotInitialized: 0,
        ReadOnly: 403,
        WriteOnly: 404,
        Keyword: 402,
        TypeMismatch: 405,
        OutOfRange: 405,
        Dependency: 405,
        OutOfOrder: 201,
        GetOutOfRange: 201,
        NoChildren: 202,
        NoCount: 203,
        SetFailed: 101,
        GetFailed: 101,
    },
    2004: {
        Undefined: 401,
        Unimplemented: 402,
        NotInitialized: 403,
        ReadOnly: 404,
        Keyword: 404,
        WriteOnly: 405,
        TypeMismatch: 406,
        OutOfRange: 407,
        Dependency: 408,
        OutOfOrder: 351,
        SetFailed: 351,
        GetOutOfRange: 301,
        NoChildren: 301,
        NoCount: 301,
        GetFailed: 301,
    },
};

const KEYWORDS = ['_children', '_count', '_version'];
const TARGET = /^(adl\.nav\.request_valid\.(?:choice|jump))\.(\{target=[^\s{}]{1,200}\})$/;
const NAME = /^[a-z0-9_.]+$/;
const DIGITS = /^\d+$/;
const MAX_ELEMENT_LENGTH = 255;
export const MAX_BYTES = 1048576;

/** The number of a mistake in a version. */
export function errorCode(version, mistake) {
    return CODES[version][mistake];
}

/** The name of an element read into its pattern, its indexes and its keyword; null when it is not one. */
export function parseName(name) {
    if ('string' !== typeof name || '' === name || name.length > MAX_ELEMENT_LENGTH) {
        return null;
    }

    const target = name.startsWith('adl.nav.request_valid.') ? TARGET.exec(name) : null;

    if (target) {
        return { name, pattern: target[1], indexes: [], keyword: null, target: target[2] };
    }

    if (!NAME.test(name)) {
        return null;
    }

    const segments = name.split('.');
    const pattern = [];
    const indexes = [];
    let keyword = null;

    for (const [position, segment] of segments.entries()) {
        if (0 === segment.length || segment.length > 41) {
            return null;
        }

        if (DIGITS.test(segment)) {
            if (segment.length > 4 || ('0' === segment[0] && 1 !== segment.length)) {
                return null;
            }

            pattern.push('n');
            indexes.push(Number(segment));
            continue;
        }

        // `n` stands for an index in the patterns of the data model: a name never holds it.
        if (DIGITS.test(segment[0]) || 'n' === segment) {
            return null;
        }

        if (KEYWORDS.includes(segment)) {
            if (position !== segments.length - 1) {
                return null;
            }

            keyword = segment;
            continue;
        }

        pattern.push(segment);
    }

    return { name, pattern: pattern.join('.'), indexes, keyword, target: null };
}

/** The collection of an element at a depth of indexes, those above filled: `cmi.interactions.3.objectives`. */
export function collection(parsed, depth) {
    const filled = [];
    let seen = 0;

    for (const segment of parsed.pattern.split('.')) {
        if ('n' === segment) {
            if (seen === depth) {
                break;
            }

            filled.push(String(parsed.indexes[seen]));
            seen++;
            continue;
        }

        filled.push(segment);
    }

    return filled.join('.');
}

function collectionPattern(parsed, depth) {
    const kept = [];
    let seen = 0;

    for (const segment of parsed.pattern.split('.')) {
        if ('n' === segment) {
            if (seen === depth) {
                break;
            }

            seen++;
        }

        kept.push(segment);
    }

    return kept.join('.');
}

function sibling(parsed, depth, suffix) {
    return `${collection(parsed, depth)}.${parsed.indexes[depth]}.${suffix}`;
}

function bytes(text) {
    return new TextEncoder().encode(text).length;
}

function text(value) {
    if ('string' === typeof value) {
        return value;
    }

    return 'number' === typeof value && Number.isFinite(value) ? String(value) : null;
}

export class CmiData {
    #model;
    #values = new Map();
    #counts = new Map();
    #bytes = 0;
    #maxBytes;
    #patterns = new Map();

    constructor(model, values = {}, maxBytes = MAX_BYTES) {
        this.#model = model;
        this.#maxBytes = maxBytes;

        for (const [element, value] of Object.entries(values)) {
            const parsed = parseName(element);

            if (parsed && null === parsed.keyword && model.elements[parsed.pattern] && 'string' === typeof value) {
                this.#store(parsed, value);
            }
        }
    }

    get version() {
        return this.#model.version;
    }

    /** The values kept, by element, in the order they were first written. */
    values() {
        return Object.fromEntries(this.#values);
    }

    value(element) {
        return this.#values.get(element) ?? null;
    }

    count(name) {
        return this.#counts.get(name) ?? 0;
    }

    /** The message of an error code, as GetErrorString() gives it. */
    message(code) {
        return this.#model.errors[code] ?? '';
    }

    get(element) {
        if ('' === element) {
            return this.#error('1.2' === this.version ? 'OutOfOrder' : 'GetFailed', 'No element is named.');
        }

        const parsed = parseName(element);

        if (!parsed) {
            return this.#error('Undefined', 'The data model defines no such element.');
        }

        if (null !== parsed.keyword) {
            return this.#keyword(parsed);
        }

        const definition = this.#model.elements[parsed.pattern];

        if (!definition) {
            return this.#error('Undefined', 'The data model defines no such element.');
        }

        if ('wo' === definition.access) {
            return this.#error('WriteOnly', 'The element is write only.');
        }

        if (!this.#inRange(parsed)) {
            return this.#error('GetOutOfRange', 'The collection holds no element at this index.');
        }

        // A target of a navigation request given no value of its own reads the value of its request:
        // `adl.nav.request_valid.choice` answers for every target the platform did not name.
        const value =
            this.#evaluated(parsed.pattern) ??
            this.#values.get(element) ??
            this.#values.get(parsed.pattern) ??
            definition.default ??
            null;

        if (null === value) {
            return '1.2' === this.version
                ? { value: '', error: 0, diagnostic: '' }
                : this.#error('NotInitialized', 'The element holds no value yet.');
        }

        return { value, error: 0, diagnostic: '' };
    }

    set(element, value) {
        if ('' === element) {
            return this.#error('1.2' === this.version ? 'OutOfOrder' : 'SetFailed', 'No element is named.');
        }

        const parsed = parseName(element);

        if (!parsed) {
            return this.#error('Undefined', 'The data model defines no such element.');
        }

        if (null !== parsed.keyword) {
            return this.#knowsKeyword(parsed)
                ? this.#error('Keyword', 'A keyword is read only.')
                : this.#error('Undefined', 'The data model defines no such element.');
        }

        const definition = this.#model.elements[parsed.pattern];

        if (!definition) {
            return this.#error('Undefined', 'The data model defines no such element.');
        }

        if ('ro' === definition.access) {
            return this.#error('ReadOnly', 'The element is read only.');
        }

        const written = text(value);

        if (null === written) {
            return this.#error('TypeMismatch', 'The value is not text or a number.');
        }

        const refused =
            this.#order(parsed) ??
            this.#dependency(parsed) ??
            this.#check(definition, parsed, written) ??
            this.#uniqueness(parsed, written);

        if (refused) {
            return this.#error(...refused);
        }

        const current = this.#values.get(element);
        const growth = bytes(written) - (undefined === current ? -bytes(element) : bytes(current));

        if (this.#bytes + growth > this.#maxBytes) {
            return this.#error('SetFailed', `The tracking data of an attempt holds ${this.#maxBytes} bytes at most.`);
        }

        this.#store(parsed, written);

        return { value: '', error: 0, diagnostic: '' };
    }

    #keyword(parsed) {
        if ('_version' === parsed.keyword) {
            return 'cmi' === parsed.pattern
                ? { value: this.#model.dataModelVersion, error: 0, diagnostic: '' }
                : this.#error('Undefined', 'Only "cmi._version" is defined.');
        }

        if (!this.#inRange(parsed)) {
            return this.#error('GetOutOfRange', 'The collection holds no element at this index.');
        }

        if ('_children' === parsed.keyword) {
            if (this.#model.parents.includes(parsed.pattern)) {
                return { value: this.#children(parsed.pattern), error: 0, diagnostic: '' };
            }

            return this.#known(parsed.pattern)
                ? this.#error('NoChildren', 'The element has no children.')
                : this.#error('Undefined', 'The data model defines no such element.');
        }

        if (undefined !== this.#model.collections[parsed.pattern]) {
            return { value: String(this.count(this.#filled(parsed))), error: 0, diagnostic: '' };
        }

        return this.#known(parsed.pattern)
            ? this.#error('NoCount', 'The element is not a collection.')
            : this.#error('Undefined', 'The data model defines no such element.');
    }

    #children(parent) {
        const prefix = `${parent}.${undefined !== this.#model.collections[parent] ? 'n.' : ''}`;
        const children = [];

        for (const name of Object.keys(this.#model.elements)) {
            if (name.startsWith(prefix)) {
                const child = name.slice(prefix.length).split('.')[0];

                if (!children.includes(child)) {
                    children.push(child);
                }
            }
        }

        return children.join(',');
    }

    #knowsKeyword(parsed) {
        return '_version' === parsed.keyword ? 'cmi' === parsed.pattern : this.#known(parsed.pattern);
    }

    #known(pattern) {
        return (
            undefined !== this.#model.elements[pattern] ||
            Object.keys(this.#model.elements).some((name) => name.startsWith(`${pattern}.`))
        );
    }

    #filled(parsed) {
        const indexes = [...parsed.indexes];

        return parsed.pattern
            .split('.')
            .map((segment) => ('n' === segment ? String(indexes.shift()) : segment))
            .join('.');
    }

    #inRange(parsed) {
        return parsed.indexes.every((index, depth) => index < this.count(collection(parsed, depth)));
    }

    #order(parsed) {
        for (const [depth, index] of parsed.indexes.entries()) {
            const count = this.count(collection(parsed, depth));
            const pattern = collectionPattern(parsed, depth);
            let bound = this.#model.collections[pattern] ?? 0;

            if ('cmi.interactions.n.correct_responses' === pattern && '2004' === this.version) {
                bound = this.#model.correctResponses[this.#values.get(sibling(parsed, 0, 'type')) ?? ''] ?? 1;
            }

            if (index > count) {
                return ['OutOfOrder', 'The elements of a collection are written in order of their index.'];
            }

            if (index >= bound) {
                return ['SetFailed', `The collection holds ${bound} elements at most.`];
            }
        }

        return null;
    }

    #dependency(parsed) {
        if ('2004' !== this.version || 0 === parsed.indexes.length) {
            return null;
        }

        const first = collectionPattern(parsed, 0);

        if (
            ('cmi.objectives' === first || 'cmi.interactions' === first) &&
            `${first}.n.id` !== parsed.pattern &&
            !this.#values.has(sibling(parsed, 0, 'id'))
        ) {
            return ['Dependency', 'The identifier is set first.'];
        }

        if (
            ['cmi.interactions.n.correct_responses.n.pattern', 'cmi.interactions.n.learner_response'].includes(
                parsed.pattern,
            ) &&
            !this.#values.has(sibling(parsed, 0, 'type'))
        ) {
            return ['Dependency', 'The type of the interaction is set first.'];
        }

        return null;
    }

    #check(definition, parsed, written) {
        if (undefined !== definition.maxChars && [...written].length > definition.maxChars) {
            return ['TypeMismatch', `The value holds ${definition.maxChars} characters at most.`];
        }

        let pattern = definition.pattern ?? null;

        if (definition.check) {
            const type = this.#values.get(sibling(parsed, 0, 'type')) ?? '';
            const responses = this.#model.responses[type];
            pattern = responses ? responses['interaction-pattern' === definition.check ? 'pattern' : 'response'] : null;
        }

        if (null !== pattern && !this.#regex(pattern).test(written)) {
            return ['TypeMismatch', 'The value is not of the type of the element.'];
        }

        if ('' !== written && (undefined !== definition.min || undefined !== definition.max)) {
            const number = Number.parseFloat(written);

            if (
                (undefined !== definition.min && number < definition.min) ||
                (undefined !== definition.max && number > definition.max)
            ) {
                return ['OutOfRange', 'The value is out of the range of the element.'];
            }
        }

        return null;
    }

    #uniqueness(parsed, written) {
        if ('2004' !== this.version) {
            return null;
        }

        if ('cmi.objectives.n.id' === parsed.pattern) {
            const current = this.#values.get(parsed.name);

            if (undefined !== current && current !== written) {
                return ['SetFailed', 'The identifier of an objective never changes.'];
            }

            return this.#duplicate('cmi.objectives', parsed.indexes[0], written);
        }

        if ('cmi.interactions.n.objectives.n.id' === parsed.pattern) {
            return this.#duplicate(collection(parsed, 1), parsed.indexes[1], written);
        }

        return null;
    }

    #duplicate(name, index, written) {
        for (let other = 0; other < this.count(name); other++) {
            if (other !== index && this.#values.get(`${name}.${other}.id`) === written) {
                return ['SetFailed', 'The identifier is already given to another element of the collection.'];
            }
        }

        return null;
    }

    #evaluated(pattern) {
        if ('2004' !== this.version) {
            return null;
        }

        const threshold = this.#values.get('cmi.completion_threshold');

        if ('cmi.completion_status' === pattern && undefined !== threshold) {
            const progress = this.#values.get('cmi.progress_measure');

            if (undefined === progress) {
                return 'unknown';
            }

            return Number.parseFloat(progress) >= Number.parseFloat(threshold) ? 'completed' : 'incomplete';
        }

        const passing = this.#values.get('cmi.scaled_passing_score');

        if ('cmi.success_status' === pattern && undefined !== passing) {
            const scaled = this.#values.get('cmi.score.scaled');

            if (undefined === scaled) {
                return 'unknown';
            }

            return Number.parseFloat(scaled) >= Number.parseFloat(passing) ? 'passed' : 'failed';
        }

        return null;
    }

    #regex(pattern) {
        let regex = this.#patterns.get(pattern);

        if (!regex) {
            regex = new RegExp(pattern, 'u');
            this.#patterns.set(pattern, regex);
        }

        return regex;
    }

    #store(parsed, value) {
        const current = this.#values.get(parsed.name);
        this.#bytes += bytes(value) - (undefined === current ? -bytes(parsed.name) : bytes(current));
        this.#values.set(parsed.name, value);

        for (const [depth, index] of parsed.indexes.entries()) {
            const name = collection(parsed, depth);
            this.#counts.set(name, Math.max(this.count(name), index + 1));
        }
    }

    #error(mistake, diagnostic) {
        return { value: '', error: CODES[this.version][mistake], diagnostic };
    }
}
