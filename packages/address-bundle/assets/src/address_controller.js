/*
 * The Stimulus controller of phil/address-bundle that makes an address form follow the country chosen, as the
 * bundle's AddressType renders it:
 *
 *     <div data-controller="phil--address-bundle--address" data-phil--address-bundle--address-url-value="/address-format/__COUNTRY__">
 *         <select data-phil--address-bundle--address-target="country" data-action="phil--address-bundle--address#change">…</select>
 *         <div data-phil--address-bundle--address-target="row" data-field="postalCode"><label>…</label><input …></div>
 *     </div>
 *
 * When the country changes, it reads the country's format from the endpoint and, for each row, shows or hides it —
 * a hidden field is disabled, so it is not sent —, writes its label, and marks it required; the row of the state or
 * province gets a choice of the country's, or a text field when it has none. It dispatches `changed` with the format.
 *
 * Only the answer to the latest change is applied, and only while its country is still the one chosen: a slow answer
 * for a country chosen before never overwrites the fields of the one chosen after. A request that fails leaves the
 * fields as they are and dispatches `failed` with the error.
 */
import { Controller } from '@hotwired/stimulus';
import { formatUrl, plan } from './address.js';

export default class extends Controller {
    static targets = ['country', 'row'];

    static values = { url: String };

    requests = 0;

    async change() {
        const country = this.countryTarget.value;
        const request = ++this.requests;

        if (country === '') {
            return;
        }

        try {
            const response = await fetch(formatUrl(this.urlValue, country), {
                headers: { Accept: 'application/json' },
            });
            const format = response.ok ? await response.json() : null;

            if (format !== null && request === this.requests && country === this.countryTarget.value) {
                this.apply(format);
            }
        } catch (error) {
            this.dispatch('failed', { detail: error });
        }
    }

    apply(format) {
        const rows = new Map(this.rowTargets.map((row) => [row.dataset.field, row]));

        for (const field of plan(format, [...rows.keys()])) {
            const row = rows.get(field.name);
            let control = row.querySelector('input, select');

            if (field.name === 'administrativeArea' && control !== null) {
                control = this.subdivisionControl(row, control, format.subdivisions);
            }

            row.hidden = !field.shown;

            if (control !== null) {
                control.disabled = !field.shown;
                control.required = field.required;
            }

            const label = row.querySelector('label');

            if (label !== null && field.label !== null) {
                label.textContent = field.label;
            }
        }

        this.dispatch('changed', { detail: format });
    }

    /**
     * A choice of subdivisions, or a text field when there are none, keeping the field's name and id.
     */
    subdivisionControl(row, control, subdivisions) {
        const wanted = subdivisions.length > 0 ? 'SELECT' : 'INPUT';
        const document = row.ownerDocument;
        const replacement = control.tagName === wanted ? control : document.createElement(wanted.toLowerCase());

        if (replacement !== control) {
            replacement.name = control.name;
            replacement.id = control.id;
            replacement.className = control.className;
            control.replaceWith(replacement);
        }

        if (wanted === 'SELECT') {
            replacement.replaceChildren(
                ...[{ code: '', name: '' }, ...subdivisions].map(({ code, name }) => {
                    const option = document.createElement('option');
                    option.value = code;
                    option.textContent = name;

                    return option;
                }),
            );
        } else {
            replacement.type = 'text';
            replacement.value = '';
        }

        return replacement;
    }
}
