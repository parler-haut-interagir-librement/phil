// The labels of the comments controller, in English and French; a locale such as `fr_CA` takes French, an unknown one
// English. `{name}` and `{count}` are replaced.

export const MESSAGES = {
    en: {
        comments: 'Comments',
        comment: 'Comment',
        suggest: 'Suggest',
        suggestion: 'Replace with',
        removal: 'Remove the passage',
        send: 'Send',
        cancel: 'Cancel',
        reply: 'Reply',
        resolve: 'Resolve',
        reopen: 'Reopen',
        accept: 'Accept',
        reject: 'Reject',
        edit: 'Edit',
        save: 'Save',
        delete: 'Delete',
        confirmDelete: 'Delete this comment?',
        resolved: 'Resolved by {name}',
        detached: 'Orphaned: the quoted passage is no longer in the text',
        page: 'On the page',
        pageComment: 'Comment on the page',
        accepted: 'Suggestion accepted',
        rejected: 'Suggestion rejected',
        pending: 'Suggested replacement',
        showResolved: 'Show resolved',
        none: 'No comments yet.',
        count: '{count} open',
        edited: 'edited',
        stale: 'The passage changed; the suggestion was not applied.',
        locked: 'Someone else is editing this block.',
        failed: 'The request failed.',
        refused: 'You may not do this.',
        noMember: 'No member found',
        membersFound: '{count} members found',
    },
    fr: {
        comments: 'Commentaires',
        comment: 'Commenter',
        suggest: 'Suggérer',
        suggestion: 'Remplacer par',
        removal: 'Supprimer le passage',
        send: 'Envoyer',
        cancel: 'Annuler',
        reply: 'Répondre',
        resolve: 'Résoudre',
        reopen: 'Rouvrir',
        accept: 'Accepter',
        reject: 'Refuser',
        edit: 'Modifier',
        save: 'Enregistrer',
        delete: 'Supprimer',
        confirmDelete: 'Supprimer ce commentaire ?',
        resolved: 'Résolu par {name}',
        detached: "Orphelin : le passage cité n'est plus dans le texte",
        page: 'Sur la page',
        pageComment: 'Commenter la page',
        accepted: 'Suggestion acceptée',
        rejected: 'Suggestion refusée',
        pending: 'Remplacement suggéré',
        showResolved: 'Afficher les résolus',
        none: 'Aucun commentaire.',
        count: '{count} ouverts',
        edited: 'modifié',
        stale: "Le passage a changé ; la suggestion n'a pas été appliquée.",
        locked: "Quelqu'un d'autre modifie ce bloc.",
        failed: 'La requête a échoué.',
        refused: 'Vous ne pouvez pas faire ceci.',
        noMember: 'Aucun membre trouvé',
        membersFound: '{count} membres trouvés',
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
