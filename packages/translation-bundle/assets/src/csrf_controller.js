// The translation screen's forms, each given a new CSRF token as it is sent: in its field `_csrf_token` and in a
// cookie, for Symfony's stateless check. Without JavaScript, the forms are checked by their origin alone.
//
//     <main data-controller="phil--translation-bundle--csrf" data-action="submit->phil--translation-bundle--csrf#fill">

import { Controller } from '@hotwired/stimulus';

import { fillForm } from './lib/csrf.js';

export default class extends Controller {
    fill(event) {
        fillForm(event.target, this.element.ownerDocument);
    }
}
