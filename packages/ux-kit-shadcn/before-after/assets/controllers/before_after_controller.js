import { Controller } from '@hotwired/stimulus';

/*
 * Moves the cut where the slider stands, through a custom property: nothing is written in the markup, which a strict
 * Content-Security-Policy would refuse.
 */
export default class extends Controller {
    static targets = ['range', 'before'];

    connect() {
        this.move();
    }

    move() {
        this.element.style.setProperty('--phil-before-after-position', `${this.rangeTarget.value}%`);
    }
}
