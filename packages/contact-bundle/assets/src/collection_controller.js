/*
 * The Stimulus controller of phil/contact-bundle that adds entries to a collection of the card form and removes
 * them, as the bundle's form theme renders it:
 *
 *     <div data-controller="phil--contact-bundle--collection" data-phil--contact-bundle--collection-index-value="3">
 *         <div data-phil--contact-bundle--collection-target="entries" data-prototype="…">
 *             <div data-phil--contact-bundle--collection-target="entry">… <button data-action="phil--contact-bundle--collection#remove">Remove</button></div>
 *         </div>
 *         <button data-action="phil--contact-bundle--collection#add">Add</button>
 *     </div>
 *
 * A new entry is the prototype with its placeholder, `__name__` unless the `prototype-name` value says otherwise,
 * replaced by an index no entry had. The form theme writes that index in the `index` value, after the highest key the
 * page came with, since entries kept from a submission that failed may skip numbers; without it, the entries are
 * taken as numbered from zero. It dispatches `added` with the index, and `removed`.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['entries', 'entry'];

    static values = {
        index: Number,
        prototypeName: { type: String, default: '__name__' },
    };

    connect() {
        if (!this.hasIndexValue) {
            this.indexValue = this.entryTargets.length;
        }
    }

    add(event) {
        event?.preventDefault();
        const index = this.indexValue;
        this.entriesTarget.insertAdjacentHTML(
            'beforeend',
            this.entriesTarget.dataset.prototype.replaceAll(this.prototypeNameValue, String(index)),
        );
        this.indexValue = index + 1;
        this.dispatch('added', { detail: { index } });
    }

    remove(event) {
        event?.preventDefault();
        const entry = this.entryTargets.find((candidate) => candidate.contains(event.target));

        if (entry) {
            entry.remove();
            this.dispatch('removed');
        }
    }
}
