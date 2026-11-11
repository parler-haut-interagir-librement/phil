/*
 * Distances of phil/geo-bundle in the browser: the same great-circle distance as `Phil\Geo\Coordinates::distanceTo()`,
 * over a sphere of the Earth's mean radius, places sorted by it, and a distance written for people.
 */

export const EARTH_RADIUS = 6371008.8;

const METERS_PER_MILE = 1609.344;

const radians = (degrees) => (degrees * Math.PI) / 180;

/** The great-circle distance between two `{latitude, longitude}` points, in meters. */
export function distance(from, to) {
    const half =
        Math.sin((radians(to.latitude) - radians(from.latitude)) / 2) ** 2 +
        Math.cos(radians(from.latitude)) *
            Math.cos(radians(to.latitude)) *
            Math.sin(radians(to.longitude - from.longitude) / 2) ** 2;

    return 2 * EARTH_RADIUS * Math.asin(Math.min(1, Math.sqrt(half)));
}

/**
 * The items nearest first, each with its distance; an item without a position — `position(item)` returns null — keeps
 * its place among the others without one, after every placed item. The sort is stable.
 *
 * @returns {Array<{item: *, meters: ?number}>}
 */
export function sortByDistance(items, origin, position) {
    return items
        .map((item, index) => {
            const at = position(item);

            return { item, index, meters: at === null ? null : distance(origin, at) };
        })
        .sort((a, b) => {
            if (a.meters === null || b.meters === null) {
                return (a.meters === null) - (b.meters === null) || a.index - b.index;
            }

            return a.meters - b.meters || a.index - b.index;
        })
        .map(({ item, meters }) => ({ item, meters }));
}

/**
 * `850 m` and `12.4 km`, or `0.5 mi` in the imperial system, in the locale's digits. The locale is a BCP 47 tag or a
 * Symfony locale — `fr_CA` is read as `fr-CA` —; none, or an empty one, is the browser's.
 */
export function formatDistance(meters, system = 'metric', locale = undefined) {
    const tag = typeof locale === 'string' && locale !== '' ? locale.replaceAll('_', '-') : undefined;
    const number = new Intl.NumberFormat(tag, { maximumFractionDigits: 1 });

    if (system === 'imperial') {
        return `${number.format(meters / METERS_PER_MILE)} mi`;
    }

    // Rounded before the unit is chosen: 999.6 m is 1 km, never 1,000 m.
    const rounded = Math.round(meters);

    return rounded < 1000 ? `${number.format(rounded)} m` : `${number.format(meters / 1000)} km`;
}

/** The `{latitude, longitude}` of an element's `data-latitude` and `data-longitude`, or null without both. */
export function positionOf(element) {
    const latitude = Number.parseFloat(element.dataset.latitude ?? '');
    const longitude = Number.parseFloat(element.dataset.longitude ?? '');

    return Number.isFinite(latitude) && Number.isFinite(longitude) ? { latitude, longitude } : null;
}
