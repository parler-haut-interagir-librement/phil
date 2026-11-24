// The search of the questions, folded as the server folds it: compatibility forms decomposed, accents and case
// dropped, every run of what is neither a letter nor a digit written as one space.

/** The longest search, in characters. */
export const MAX_LENGTH = 100;

/** The most words a search is matched on. */
export const MAX_TERMS = 10;

/** The shortest search counted when it finds nothing. */
export const MIN_COUNTED = 3;

export function fold(text) {
    return String(text)
        .normalize('NFKD')
        .replace(/\p{Mn}+/gu, '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim();
}

export function normalize(query) {
    return fold(query).slice(0, MAX_LENGTH).trim();
}

export function terms(normalized) {
    return normalized === '' ? [] : [...new Set(normalized.split(' '))].slice(0, MAX_TERMS);
}

export function matches(text, words) {
    return words.every((word) => text.includes(word));
}
