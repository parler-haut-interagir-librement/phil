import { Controller } from '@hotwired/stimulus';

/**
 * Says that the date of a scheduling poll is chosen: on connect, it dispatches the DOM event `scheduling-poll:chosen`
 * on the window, its detail naming the poll and the start of the date, once per poll in a browser tab, so that coming
 * back to the page does not play it again. The kit's Celebration listens with
 * `data-action="scheduling-poll:chosen@window->celebration#play"`, and draws nothing where motion is reduced; anything
 * else may listen too.
 */
export default class extends Controller {
    static values = { poll: String, date: String };

    connect() {
        if (this.alreadySaid()) {
            return;
        }

        this.remember();
        this.dispatch('chosen', {
            prefix: 'scheduling-poll',
            target: window,
            detail: { poll: this.pollValue, date: this.dateValue },
        });
    }

    key() {
        return `phil-scheduling-poll-chosen:${this.pollValue}`;
    }

    // Storage may be refused, by a private window or the site's settings: the event is then dispatched every time.
    alreadySaid() {
        try {
            return window.sessionStorage.getItem(this.key()) === '1';
        } catch {
            return false;
        }
    }

    remember() {
        try {
            window.sessionStorage.setItem(this.key(), '1');
        } catch {
            // Nothing kept: the next visit says it again.
        }
    }
}
