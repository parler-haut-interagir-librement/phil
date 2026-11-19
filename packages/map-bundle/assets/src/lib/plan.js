// What the map controller decides without Leaflet: the class of a shape from its state, and the options of a plan.

/** The states a booth may be in, each a class of the stylesheet. */
export const BOOTH_STATES = ['free', 'taken', 'closed', 'unknown'];

/**
 * The class of a shape the bundle drew: a booth by its state, an area of the neighbourhood, or nothing.
 *
 * @param {Record<string, unknown>|undefined} extra
 * @returns {string|null}
 */
export function shapeClass(extra) {
    if (extra && typeof extra.booth === 'string') {
        const state = BOOTH_STATES.includes(extra.state) ? extra.state : 'unknown';

        return `phil-map-booth is${state[0].toUpperCase()}${state.slice(1)}`;
    }

    if (extra && Number.isInteger(extra.count)) {
        return 'phil-map-area';
    }

    return null;
}

/**
 * The plan the server described under extra.phil.plan, checked, or null.
 *
 * @param {unknown} plan
 * @returns {{image: string, tiles: string|null, nativeZoom: number, bounds: [[number, number], [number, number]]}|null}
 */
export function readPlan(plan) {
    if (!plan || typeof plan !== 'object' || typeof plan.image !== 'string' || !Number.isInteger(plan.nativeZoom)) {
        return null;
    }

    const bounds = plan.bounds;
    const valid =
        Array.isArray(bounds) &&
        bounds.length === 2 &&
        bounds.every((corner) => Array.isArray(corner) && corner.length === 2 && corner.every(Number.isFinite));

    if (!valid || plan.nativeZoom < 0 || plan.nativeZoom > 20) {
        return null;
    }

    return {
        image: plan.image,
        tiles: typeof plan.tiles === 'string' ? plan.tiles : null,
        nativeZoom: plan.nativeZoom,
        bounds,
    };
}

/**
 * The PMTiles archive the server described under extra.phil.pmtiles, checked, or null.
 *
 * @param {unknown} archive
 * @returns {{url: string, kind: 'raster'|'vector', attribution: string, maxZoom: number, flavor: string}|null}
 */
export function readArchive(archive) {
    if (
        !archive ||
        typeof archive !== 'object' ||
        typeof archive.url !== 'string' ||
        !['raster', 'vector'].includes(archive.kind)
    ) {
        return null;
    }

    return {
        url: archive.url,
        kind: archive.kind,
        attribution: typeof archive.attribution === 'string' ? archive.attribution : '',
        maxZoom: Number.isInteger(archive.maxZoom) ? archive.maxZoom : 15,
        flavor: typeof archive.flavor === 'string' ? archive.flavor : 'light',
    };
}
