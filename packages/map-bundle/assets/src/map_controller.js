import { Controller } from '@hotwired/stimulus';

import { readArchive, readPlan, shapeClass } from './lib/plan.js';

/**
 * Decorates the map of Symfony UX Map's Leaflet controller, which sits on the same element and connects after this
 * one: through its events, a floor plan is drawn on Leaflet's flat reference system, its image or its tiles under the
 * booths; a PMTiles archive replaces the tile layer, which comes back if the archive's package cannot be loaded; each
 * booth and area gets the class of its state. Without JavaScript, the element stays hidden and the list says it all.
 */
export default class extends Controller {
    connect() {
        this.fallback = null;
        this.plan = null;
        this.archive = null;
        this.element.classList.add('hasScript');
        this.onPreConnect = this.preConnect.bind(this);
        this.onConnect = this.mapConnected.bind(this);
        this.onShape = this.shape.bind(this);
        this.element.addEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.addEventListener('ux:map:connect', this.onConnect);
        this.element.addEventListener('ux:map:rectangle:before-create', this.onShape);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:pre-connect', this.onPreConnect);
        this.element.removeEventListener('ux:map:connect', this.onConnect);
        this.element.removeEventListener('ux:map:rectangle:before-create', this.onShape);
    }

    preConnect(event) {
        const { L, options, extra } = event.detail;
        this.plan = readPlan(extra?.phil?.plan);
        this.archive = readArchive(extra?.phil?.pmtiles);

        if (this.plan) {
            event.detail.bridgeOptions = {
                ...(event.detail.bridgeOptions ?? {}),
                crs: L.CRS.Simple,
                maxBounds: this.plan.bounds,
                maxBoundsViscosity: 1,
            };
        }

        if (this.archive && options.tileLayer) {
            this.fallback = options.tileLayer;
            options.tileLayer = false;
        }
    }

    shape(event) {
        const className = shapeClass(event.detail.definition.extra);

        if (className) {
            event.detail.definition.bridgeOptions = { ...(event.detail.definition.bridgeOptions ?? {}), className };
        }
    }

    async mapConnected(event) {
        const { L, map } = event.detail;

        if (this.plan) {
            this.drawPlan(L, map, this.plan);
        }

        if (this.archive) {
            await this.drawArchive(L, map, this.archive);
        }
    }

    drawPlan(L, map, plan) {
        const layer = plan.tiles
            ? L.tileLayer(plan.tiles, {
                  noWrap: true,
                  bounds: plan.bounds,
                  minZoom: 0,
                  maxNativeZoom: plan.nativeZoom,
                  maxZoom: plan.nativeZoom + 2,
              })
            : L.imageOverlay(plan.image, plan.bounds, { className: 'phil-map-plan-image' });

        layer.addTo(map);
        layer.bringToBack();
        map.fitBounds(plan.bounds);
        this.element.dataset.planDrawn = plan.tiles ? 'tiles' : 'image';
    }

    async drawArchive(L, map, archive) {
        try {
            if (archive.kind === 'vector') {
                const protomaps = await import('protomaps-leaflet');
                protomaps
                    .leafletLayer({
                        url: archive.url,
                        flavor: archive.flavor,
                        attribution: archive.attribution,
                        maxDataZoom: archive.maxZoom,
                    })
                    .addTo(map);
            } else {
                const pmtiles = await import('pmtiles');
                pmtiles
                    .leafletRasterLayer(new pmtiles.PMTiles(archive.url), {
                        attribution: archive.attribution,
                        maxNativeZoom: archive.maxZoom,
                    })
                    .addTo(map);
            }

            this.element.dataset.tiles = 'pmtiles';
        } catch (error) {
            console.warn(
                '[phil/map-bundle] The PMTiles archive could not be read; the map falls back on its other tiles.',
                error,
            );

            if (this.fallback) {
                L.tileLayer(this.fallback.url, {
                    attribution: this.fallback.attribution,
                    ...this.fallback.options,
                }).addTo(map);
            }

            this.element.dataset.tiles = 'fallback';
        }
    }
}
