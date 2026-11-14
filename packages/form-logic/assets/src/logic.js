// The evaluator of the browser: the JsonLogic subset of phil/form-logic, rule for rule the same as the PHP classes
// `Evaluator` and `Values`, so the browser shows what the server will judge. A rule outside the subset throws: the
// controller then leaves every field shown, and the server, which reads the rules strictly, stays the judge.

const MAX_DATA_DEPTH = 16;
const MAX_DEPTH = 64;
const SPACES = '\t\n\x0B\f\r ';
const NUMBER = /^[\t\n\v\f\r ]*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?[\t\n\v\f\r ]*$/;
const NAME = /^[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z0-9_]{1,64}){0,7}$/;
const INDEX = /^(?:0|[1-9]\d{0,8})$/;
const COMPARISONS = ['==', '!=', '===', '!==', '<', '<=', '>', '>='];
const ARITHMETIC = ['+', '-', '*', '/', '%', 'min', 'max'];

export class UnreadableRule extends Error {}

function isObject(value) {
    return null !== value && 'object' === typeof value && !Array.isArray(value);
}

function trimSpaces(text) {
    let start = 0;
    let end = text.length;

    while (start < end && SPACES.includes(text[start])) {
        start++;
    }

    while (end > start && SPACES.includes(text[end - 1])) {
        end--;
    }

    return text.slice(start, end);
}

// A number as logic keeps it: `-0` as `0`, a non-finite number as `null`.
export function number(value) {
    if (!Number.isFinite(value)) {
        return null;
    }

    return 0 === value ? 0 : value;
}

// A value of the data: anything that is not `null`, a boolean, a finite number, a text or a list reads as `null`.
export function fromData(value, depth = 0) {
    if ('number' === typeof value) {
        return number(value);
    }

    if ('string' === typeof value || 'boolean' === typeof value) {
        return value;
    }

    if (!Array.isArray(value) || depth >= MAX_DATA_DEPTH) {
        return null;
    }

    return Array.from(value, (item) => fromData(item, depth + 1));
}

export function truthy(value) {
    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return null !== value && false !== value && 0 !== value && '' !== value && !Number.isNaN(value);
}

export function toText(value) {
    if (Array.isArray(value)) {
        return value
            .map((item) => {
                const read = fromData(item);

                return null === read ? '' : toText(read);
            })
            .join(',');
    }

    return String(value);
}

export function toNumber(value) {
    const read = Array.isArray(value) ? toText(value) : value;

    if (null === read || false === read) {
        return 0;
    }

    if (true === read) {
        return 1;
    }

    if ('string' === typeof read) {
        if (!NUMBER.test(read)) {
            return '' === trimSpaces(read) ? 0 : Number.NaN;
        }

        return Number(trimSpaces(read));
    }

    return read;
}

export function looselyEqual(left, right) {
    if (null === left || null === right) {
        return null === left && null === right;
    }

    if ('boolean' === typeof left && 'boolean' === typeof right) {
        return left === right;
    }

    if ('boolean' === typeof left) {
        return looselyEqual(toNumber(left), right);
    }

    if ('boolean' === typeof right) {
        return looselyEqual(left, toNumber(right));
    }

    if (Array.isArray(left) || Array.isArray(right)) {
        return (
            !(Array.isArray(left) && Array.isArray(right)) &&
            looselyEqual(Array.isArray(left) ? toText(left) : left, Array.isArray(right) ? toText(right) : right)
        );
    }

    if ('string' === typeof left && 'string' === typeof right) {
        return left === right;
    }

    return toNumber(left) === toNumber(right);
}

export function strictlyEqual(left, right) {
    return !Array.isArray(left) && left === right;
}

export function less(left, right, orEqual = false) {
    const a = Array.isArray(left) ? toText(left) : left;
    const b = Array.isArray(right) ? toText(right) : right;

    if ('string' === typeof a && 'string' === typeof b) {
        return orEqual ? a <= b : a < b;
    }

    const x = toNumber(a);
    const y = toNumber(b);

    return orEqual ? x <= y : x < y;
}

export function contains(needle, haystack) {
    if ('string' === typeof haystack) {
        return '' !== haystack && haystack.includes(toText(needle));
    }

    if (!Array.isArray(haystack)) {
        return false;
    }

    return haystack.some((item) => strictlyEqual(fromData(item), needle));
}

function lookup(name, data) {
    let current = data;

    for (const segment of name.split('.')) {
        if (Array.isArray(current)) {
            if (!INDEX.test(segment) || Number(segment) >= current.length) {
                return null;
            }

            current = current[Number(segment)];
        } else if (isObject(current) && Object.hasOwn(current, segment)) {
            current = current[segment];
        } else {
            return null;
        }
    }

    return fromData(current);
}

function operands(argument) {
    return Array.isArray(argument) ? argument : [argument];
}

function only(operator, values) {
    if (1 !== values.length) {
        throw new UnreadableRule(`A "${operator}" takes 1 operand.`);
    }

    return values[0];
}

function variable(argument, data, depth) {
    const list = Array.isArray(argument) && argument.length >= 1 && argument.length <= 2;
    const name = list ? argument[0] : argument;

    if ('string' !== typeof name || !NAME.test(name)) {
        throw new UnreadableRule('A "var" names its variable by a text.');
    }

    const value = lookup(name, data);

    return null === value && list && 2 === argument.length ? evaluate(argument[1], data, depth + 1) : value;
}

function missing(operator, argument, data) {
    let atLeast = null;
    let names = argument;

    if ('missing_some' === operator) {
        if (!Array.isArray(argument) || 2 !== argument.length || !Number.isInteger(argument[0])) {
            throw new UnreadableRule('A "missing_some" takes how many names are needed, then the list of names.');
        }

        [atLeast, names] = argument;
    }

    names = operands(names);

    if (0 === names.length || names.some((name) => 'string' !== typeof name || !NAME.test(name))) {
        throw new UnreadableRule(`A "${operator}" lists the names of variables.`);
    }

    if (null !== atLeast && (atLeast < 0 || atLeast > names.length)) {
        throw new UnreadableRule(`A "missing_some" needs 0 to ${names.length} of its names.`);
    }

    const absent = names.filter((name) => {
        const value = lookup(name, data);

        return null === value || '' === value;
    });

    return null !== atLeast && names.length - absent.length >= atLeast ? [] : absent;
}

function compare(operator, values, data, depth) {
    const read = (index) => evaluate(values[index], data, depth + 1);

    if (3 === values.length && ('<' === operator || '<=' === operator)) {
        const value = read(1);
        const inclusive = '<=' === operator;

        return less(read(0), value, inclusive) && less(value, read(2), inclusive);
    }

    if (2 !== values.length) {
        throw new UnreadableRule(`A "${operator}" compares 2 operands.`);
    }

    const left = read(0);
    const right = read(1);

    switch (operator) {
        case '==':
            return looselyEqual(left, right);
        case '!=':
            return !looselyEqual(left, right);
        case '===':
            return strictlyEqual(left, right);
        case '!==':
            return !strictlyEqual(left, right);
        case '<':
            return less(left, right);
        case '<=':
            return less(left, right, true);
        case '>':
            return less(right, left);
        default:
            return less(right, left, true);
    }
}

function first(values, data, depth, truth) {
    if (0 === values.length) {
        throw new UnreadableRule('An "and" or an "or" has at least one operand.');
    }

    let value = null;

    for (const operand of values) {
        value = evaluate(operand, data, depth + 1);

        if (truth === truthy(value)) {
            return value;
        }
    }

    return value;
}

function conditional(values, data, depth) {
    if (values.length < 2) {
        throw new UnreadableRule('An "if" has at least one condition and its value.');
    }

    let index = 0;

    for (; index + 1 < values.length; index += 2) {
        if (truthy(evaluate(values[index], data, depth + 1))) {
            return evaluate(values[index + 1], data, depth + 1);
        }
    }

    return index < values.length ? evaluate(values[index], data, depth + 1) : null;
}

function arithmetic(operator, values, data, depth) {
    const counts = { '-': [1, 2], '/': [2, 2], '%': [2, 2] }[operator] ?? [1, Number.POSITIVE_INFINITY];

    if (values.length < counts[0] || values.length > counts[1]) {
        throw new UnreadableRule(`A "${operator}" does not take ${values.length} operands.`);
    }

    const numbers = values.map((operand) => toNumber(evaluate(operand, data, depth + 1)));
    const [a, b] = numbers;

    switch (operator) {
        case '+':
            return number(numbers.reduce((sum, item) => sum + item, 0));
        case '*':
            return number(numbers.reduce((product, item) => product * item, 1));
        case '-':
            return number(undefined === b ? -a : a - b);
        case '/':
            return 0 === b ? null : number(a / b);
        case '%':
            return 0 === b ? null : number(a % b);
        case 'min':
            return number(Math.min(...numbers));
        default:
            return number(Math.max(...numbers));
    }
}

const ARITY = {
    '==': [2, 2],
    '!=': [2, 2],
    '===': [2, 2],
    '!==': [2, 2],
    '<': [2, 3],
    '<=': [2, 3],
    '>': [2, 2],
    '>=': [2, 2],
    '!': [1, 1],
    '!!': [1, 1],
    and: [1, Number.POSITIVE_INFINITY],
    or: [1, Number.POSITIVE_INFINITY],
    if: [2, Number.POSITIVE_INFINITY],
    in: [2, 2],
    '+': [1, Number.POSITIVE_INFINITY],
    '-': [1, 2],
    '*': [1, Number.POSITIVE_INFINITY],
    '/': [2, 2],
    '%': [2, 2],
    min: [1, Number.POSITIVE_INFINITY],
    max: [1, Number.POSITIVE_INFINITY],
};

// Refuses a rule outside the subset as a whole, before it is evaluated, as the server's reader does: a branch that
// would not be reached is checked too.
export function check(rule, depth = 0) {
    if (depth > MAX_DEPTH) {
        throw new UnreadableRule('The rule is nested too deep.');
    }

    if (null === rule || ['boolean', 'string', 'number'].includes(typeof rule)) {
        evaluate(rule, {});

        return;
    }

    if (Array.isArray(rule)) {
        for (const item of rule) {
            check(item, depth + 1);
        }

        return;
    }

    if (!isObject(rule) || 1 !== Object.keys(rule).length) {
        throw new UnreadableRule('An object of a rule is one operation.');
    }

    const [operator] = Object.keys(rule);
    const argument = rule[operator];

    if ('var' === operator) {
        variable(argument, {}, depth);

        if (Array.isArray(argument) && 2 === argument.length) {
            check(argument[1], depth + 1);
        }

        return;
    }

    if ('missing' === operator || 'missing_some' === operator) {
        missing(operator, argument, {});

        return;
    }

    const arity = ARITY[operator];
    const values = operands(argument);

    if (undefined === arity || !Object.hasOwn(ARITY, operator)) {
        throw new UnreadableRule(`The operator "${operator}" is not read.`);
    }

    if (values.length < arity[0] || values.length > arity[1]) {
        throw new UnreadableRule(`A "${operator}" does not take ${values.length} operands.`);
    }

    for (const value of values) {
        check(value, depth + 1);
    }
}

// The value of a rule for this data.
export function evaluate(rule, data, depth = 0) {
    if (depth > MAX_DEPTH) {
        throw new UnreadableRule('The rule is nested too deep.');
    }

    if (null === rule || 'boolean' === typeof rule || 'string' === typeof rule) {
        return rule;
    }

    if ('number' === typeof rule) {
        return number(rule);
    }

    if (Array.isArray(rule)) {
        return rule.map((item) => evaluate(item, data, depth + 1));
    }

    if (!isObject(rule)) {
        throw new UnreadableRule('A rule holds JSON values only.');
    }

    const keys = Object.keys(rule);

    if (1 !== keys.length) {
        throw new UnreadableRule('An object of a rule is one operation.');
    }

    const operator = keys[0];
    const argument = rule[operator];

    if ('var' === operator) {
        return variable(argument, data, depth);
    }

    if ('missing' === operator || 'missing_some' === operator) {
        return missing(operator, argument, data);
    }

    const values = operands(argument);

    if (COMPARISONS.includes(operator)) {
        if (3 === values.length && !['<', '<='].includes(operator)) {
            throw new UnreadableRule(`A "${operator}" compares 2 operands.`);
        }

        return compare(operator, values, data, depth);
    }

    if (ARITHMETIC.includes(operator)) {
        return arithmetic(operator, values, data, depth);
    }

    switch (operator) {
        case '!':
            return !truthy(evaluate(only(operator, values), data, depth + 1));
        case '!!':
            return truthy(evaluate(only(operator, values), data, depth + 1));
        case 'and':
            return first(values, data, depth, false);
        case 'or':
            return first(values, data, depth, true);
        case 'if':
            return conditional(values, data, depth);
        case 'in':
            if (2 !== values.length) {
                throw new UnreadableRule('An "in" takes a needle and what it is looked for in.');
            }

            return contains(evaluate(values[0], data, depth + 1), evaluate(values[1], data, depth + 1));
        default:
            throw new UnreadableRule(`The operator "${operator}" is not read.`);
    }
}

export function holds(rule, data) {
    return truthy(evaluate(rule, data));
}

// The steps of a form's logic, as `FormLogic::toArray()` gives them, run as the server's `Judge` runs them: a hidden
// field reads as unanswered for the steps after it, a computed variable is readable by the steps after it.
export function judge(steps, answer) {
    if (!Array.isArray(steps)) {
        throw new UnreadableRule('The logic is a list of steps.');
    }

    for (const step of steps) {
        check(isObject(step) ? ('string' === typeof step.compute ? step.value : step.when) : undefined);
    }

    const data = { ...answer };
    const hidden = [];
    const computed = {};

    for (const step of steps) {
        if (isObject(step) && 'string' === typeof step.compute) {
            computed[step.compute] = evaluate(step.value, data);
            data[step.compute] = computed[step.compute];
        } else if (isObject(step) && 'string' === typeof step.field) {
            if (!holds(step.when, data)) {
                hidden.push(step.field);
                data[step.field] = null;
            }
        } else {
            throw new UnreadableRule('A step is a field condition or a computed variable.');
        }
    }

    return { hidden, computed };
}
