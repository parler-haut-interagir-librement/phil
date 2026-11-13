// The labels of the desk controller, in English and French; a locale such as `fr_CA` takes French, an unknown one
// English. `{label}`, `{time}` and `{count}` are replaced.

export const MESSAGES = {
    en: {
        admitted: 'Admitted',
        checked_out: 'Checked out',
        already_admitted: 'Already admitted',
        already_admitted_at: 'Already admitted at {time}',
        not_inside: 'Not checked in',
        not_yet_valid: 'Not valid yet',
        expired: 'Expired',
        revoked: 'Revoked',
        wrong_checkpoint: 'Not valid at this checkpoint',
        unknown: 'Unknown code',
        collision: 'Duplicate scan, scan again',
        busy: 'Busy, scan again',
        refused: 'The scan was refused',
        offline: 'Offline',
        online: 'Online',
        offlineNoManifest: 'Offline, and no list of credentials on this device',
        decidedOffline: 'decided offline',
        pending: '{count} scans to send',
        notKept: 'not kept on this device',
    },
    fr: {
        admitted: 'Admis',
        checked_out: 'Sortie notée',
        already_admitted: 'Déjà admis',
        already_admitted_at: 'Déjà admis à {time}',
        not_inside: 'Pas entré',
        not_yet_valid: 'Pas encore valide',
        expired: 'Expiré',
        revoked: 'Révoqué',
        wrong_checkpoint: 'Non valide à ce point de contrôle',
        unknown: 'Code inconnu',
        collision: 'Scan en double, scannez de nouveau',
        busy: 'Occupé, scannez de nouveau',
        refused: 'Le scan a été refusé',
        offline: 'Hors ligne',
        online: 'En ligne',
        offlineNoManifest: 'Hors ligne, et aucune liste de titres sur cet appareil',
        decidedOffline: 'décidé hors ligne',
        pending: '{count} scans à envoyer',
        notKept: 'non gardé sur cet appareil',
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
