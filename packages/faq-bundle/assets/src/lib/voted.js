// The questions this browser voted for, kept in its own storage and never sent: it hides the buttons of a question
// already answered. A browser that refuses the storage simply shows them again.

const KEY = 'phil-faq-voted';
const MAX = 500;

export function voted(storage) {
    try {
        const list = JSON.parse(storage?.getItem(KEY) ?? '[]');

        return Array.isArray(list) ? list.filter((one) => typeof one === 'string') : [];
    } catch {
        return [];
    }
}

export function remember(storage, anchor) {
    const list = voted(storage).filter((one) => one !== anchor);
    list.push(anchor);

    try {
        storage?.setItem(KEY, JSON.stringify(list.slice(-MAX)));
    } catch {
        // A full or refused storage: the vote is counted all the same.
    }
}
