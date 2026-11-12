/**
 * What the upload controller decides without the DOM: which files are too large, and how a size and a progress read.
 */

/**
 * @param {number} bytes
 * @returns {string} `512 B`, `1.5 KiB`, `12.3 MiB`
 */
export function formatBytes(bytes) {
    const units = ['B', 'KiB', 'MiB', 'GiB'];
    let value = Math.max(0, bytes);
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return 0 === unit ? `${value} B` : `${value.toFixed(1)} ${units[unit]}`;
}

/**
 * @template {{size: number}} F
 * @param {Iterable<F>} files
 * @param {number} maxBytes
 * @returns {{accepted: F[], refused: F[]}}
 */
export function splitBySize(files, maxBytes) {
    const accepted = [];
    const refused = [];

    for (const file of files) {
        (file.size > maxBytes ? refused : accepted).push(file);
    }

    return { accepted, refused };
}

/**
 * @param {number} loaded
 * @param {number} total
 * @returns {number} the share sent, from 0 to 100, rounded down
 */
export function percent(loaded, total) {
    if (total <= 0) {
        return 0;
    }

    return Math.min(100, Math.floor((loaded / total) * 100));
}
