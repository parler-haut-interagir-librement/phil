// The preview of a math, mermaid, chart or qrCode block while its author writes it: after each pause in typing, the
// fields of the block are sent to the bundle's endpoint, and the drawing the server returns — sanitized SVG or
// MathML, made by the same code that draws the block when it is saved — replaces the preview. A slower answer to an
// older request is dropped.
//
// <div data-controller="phil--svg-blocks-bundle--preview"
//      data-phil--svg-blocks-bundle--preview-url-value="{{ path('phil_svg_blocks_preview') }}"
//      data-phil--svg-blocks-bundle--preview-type-value="math"
//      data-phil--svg-blocks-bundle--preview-csrf-cookie-value="{{ csrf_token('phil_svg_blocks') }}">
//     <textarea data-field="latex" data-phil--svg-blocks-bundle--preview-target="field"
//               data-action="phil--svg-blocks-bundle--preview#update"></textarea>
//     <div data-phil--svg-blocks-bundle--preview-target="output"></div>
//     <p data-phil--svg-blocks-bundle--preview-target="error" role="alert" hidden></p>
// </div>

import { Controller } from '@hotwired/stimulus';
import { csrfHeaders } from './lib/csrf.js';
import { answer, collect, debounce, requestInit } from './lib/preview.js';

export default class extends Controller {
    static targets = ['field', 'output', 'error'];

    static values = {
        url: String,
        type: String,
        csrfCookie: String,
        delay: { type: Number, default: 400 },
    };

    connect() {
        this.sequence = 0;
        this.schedule = debounce(() => this.refresh(), this.delayValue);
        this.refresh();
    }

    disconnect() {
        this.schedule.cancel();
        this.sequence++;
    }

    /** Called on each input of a field: the drawing is asked for once the author pauses. */
    update() {
        this.schedule();
    }

    async refresh() {
        let data;

        try {
            data = collect(
                this.fieldTargets.map((field) => ({
                    name: field.dataset.field ?? field.name,
                    value: field.value,
                    kind: field.dataset.kind,
                })),
            );
        } catch (error) {
            this.show({ error: error.message });

            return;
        }

        const sequence = ++this.sequence;
        this.element.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(
                this.urlValue,
                requestInit(this.typeValue, data, csrfHeaders(this.csrfCookieValue)),
            );
            const json = await response.json().catch(() => ({}));

            if (sequence === this.sequence) {
                this.show(answer(response.status, json));
            }
        } catch {
            if (sequence === this.sequence) {
                this.show({ error: 'The preview could not be reached.' });
            }
        } finally {
            if (sequence === this.sequence) {
                this.element.removeAttribute('aria-busy');
            }
        }
    }

    show(result) {
        if (this.hasOutputTarget && 'string' === typeof result.markup) {
            // The markup comes from this site's endpoint, drawn and sanitized on the server.
            this.outputTarget.innerHTML = result.markup;
        }

        if (this.hasErrorTarget) {
            this.errorTarget.textContent = result.error ?? '';
            this.errorTarget.hidden = undefined === result.error;
        }

        this.dispatch('rendered', { detail: result });
    }
}
