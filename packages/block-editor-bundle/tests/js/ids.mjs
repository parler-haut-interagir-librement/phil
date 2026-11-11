// Identifiers in sequence, so that tests can say which block is which.
export function sequence(prefix = 'n') {
    let next = 0;

    return () => prefix + ++next;
}
