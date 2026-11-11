// The labels of the collaboration controller, in English and French; a locale such as `fr_CA` takes French, an
// unknown one English. `{name}` and `{count}` are replaced.

export const MESSAGES = {
    en: {
        presence: 'Also here',
        editing: 'editing',
        blocks: '{count} blocks',
        block: '1 block',
        whole: 'the whole document',
        you: 'you',
        takeOver: 'Take over',
        held: '{name} is editing this block.',
        takenOver: '{name} took over the block you were editing.',
        lost: 'You no longer hold the block you were editing.',
        tookOver: 'You hold the block now.',
        refused: 'This block cannot be taken over.',
    },
    fr: {
        presence: 'Aussi ici',
        editing: 'modifie',
        blocks: '{count} blocs',
        block: '1 bloc',
        whole: 'tout le document',
        you: 'vous',
        takeOver: 'Reprendre',
        held: '{name} modifie ce bloc.',
        takenOver: '{name} a repris le bloc que vous modifiiez.',
        lost: 'Vous ne tenez plus le bloc que vous modifiiez.',
        tookOver: 'Vous tenez maintenant ce bloc.',
        refused: 'Ce bloc ne peut pas être repris.',
    },
};

export function translator(locale, overrides = {}) {
    const language = String(locale ?? '')
        .toLowerCase()
        .split(/[-_]/)[0];
    const messages = { ...MESSAGES.en, ...(MESSAGES[language] ?? {}), ...overrides };

    return (key, values = {}) =>
        String(messages[key] ?? key).replace(/\{(\w+)\}/g, (whole, name) =>
            undefined === values[name] ? whole : String(values[name]),
        );
}
