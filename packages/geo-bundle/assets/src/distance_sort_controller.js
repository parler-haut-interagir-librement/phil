/*
 * The Stimulus controller of phil/geo-bundle that sorts places by their distance to the visitor — in the browser:
 * the visitor's position is asked of the browser and never sent anywhere.
 *
 *     <div data-controller="phil--geo-bundle--distance-sort" data-phil--geo-bundle--distance-sort-locate-value="true">
 *         <button data-action="phil--geo-bundle--distance-sort#locate">Nearest first</button>
 *         <ul>
 *             <li {{ geo_attributes(place.position, 2) }}>{{ place.name }} <span data-distance></span></li>
 *         </ul>
 *     </div>
 *
 * Each `item` target carries `data-latitude` and `data-longitude`, which Twig's `geo_attributes()` writes. The
 * controller sorts the items within their parent, nearest first, and writes each distance into the item's element
 * with `data-distance`. The origin is the `latitude` and `longitude` values when given, or the visitor's position:
 * asked when the page connects if `locate` is true, or by the `locate` action. It dispatches `sorted` with the
 * origin, and `denied` when the browser gives no position.
 */
import { Controller } from '@hotwired/stimulus';
import { formatDistance, positionOf, sortByDistance } from './distance.js';

export default class extends Controller {
    static targets = ['item'];

    static values = {
        latitude: Number,
        longitude: Number,
        locate: { type: Boolean, default: false },
        system: { type: String, default: 'metric' },
        locale: String,
    };

    connect() {
        if (this.hasLatitudeValue && this.hasLongitudeValue) {
            this.sortFrom({ latitude: this.latitudeValue, longitude: this.longitudeValue });
        } else if (this.locateValue) {
            this.locate();
        }
    }

    locate() {
        const geolocation = globalThis.navigator?.geolocation;

        if (!geolocation) {
            this.dispatch('denied');

            return;
        }

        geolocation.getCurrentPosition(
            ({ coords }) => this.sortFrom({ latitude: coords.latitude, longitude: coords.longitude }),
            () => this.dispatch('denied'),
            { maximumAge: 600000, timeout: 10000 },
        );
    }

    sortFrom(origin) {
        const locale = this.hasLocaleValue ? this.localeValue : undefined;

        for (const { item, meters } of sortByDistance(this.itemTargets, origin, positionOf)) {
            item.parentNode.appendChild(item);
            const label = item.querySelector('[data-distance]');

            if (label) {
                label.textContent = meters === null ? '' : formatDistance(meters, this.systemValue, locale);
            }
        }

        this.dispatch('sorted', { detail: { origin } });
    }
}
