// The activity page of a SCORM session: it frames the frame of the run time from the content origin, hands it the
// state of the session once it says it is ready, and sends the writes it reports to the commit endpoint — in order, on
// each commit and at the end of the session, with `navigator.sendBeacon()` when the learner leaves the page. Each
// answer brings the token of the next commit; a quiet session commits nothing now and then, to keep its token alive.
// Under a time limit, it commits when the deadline comes, so that the server ends the attempt. An ended attempt opened
// for review sends nothing: the content reads its data, and what it writes stays in the frame.

import { Controller } from '@hotwired/stimulus';

const BEACON_BYTES = 60000;
// The activity of the launch address the page gives, replaced by the one to go to.
const PLACEHOLDER = '00000000-0000-0000-0000-000000000000';

export default class extends Controller {
    static targets = ['frame', 'status'];

    static values = {
        frameUrl: String,
        contentOrigin: String,
        commitUrl: String,
        launchUrl: String,
        outlineUrl: String,
        token: String,
        csrf: String,
        state: Object,
        messages: Object,
        renew: { type: Number, default: 600 },
    };

    pending = new Map();
    queue = Promise.resolve();
    ended = false;

    connect() {
        this.onMessage = (event) => this.receive(event);
        this.onLeave = () => this.leave();
        window.addEventListener('message', this.onMessage);
        this.ended = true === this.stateValue.review;

        if (this.ended) {
            this.say('review');
        } else {
            window.addEventListener('pagehide', this.onLeave);
            this.timer = setInterval(() => this.flush(false), this.renewValue * 1000);
            this.arm(this.stateValue.remaining);
        }

        this.frameTarget.src = this.frameUrlValue;
    }

    disconnect() {
        this.leave();
        window.removeEventListener('message', this.onMessage);
        window.removeEventListener('pagehide', this.onLeave);
        this.stop();
    }

    /** Commits once the seconds left before the deadline are spent, so that the server ends the attempt. */
    arm(remaining) {
        clearTimeout(this.deadline);

        if ('number' === typeof remaining && !this.ended) {
            this.deadline = setTimeout(() => this.flush(false), (Math.max(0, remaining) + 1) * 1000);
        }
    }

    stop() {
        clearInterval(this.timer);
        clearTimeout(this.deadline);
    }

    receive(event) {
        if (
            event.origin !== this.contentOriginValue ||
            event.source !== this.frameTarget.contentWindow ||
            'scorm' !== event.data?.phil
        ) {
            return;
        }

        const message = event.data;

        if ('ready' === message.type) {
            this.frameTarget.contentWindow.postMessage(
                { phil: 'scorm', type: 'init', state: this.stateValue },
                this.contentOriginValue,
            );
        } else if ('set' === message.type && 'string' === typeof message.element && 'string' === typeof message.value) {
            this.pending.set(message.element, message.value);
        } else if ('commit' === message.type) {
            this.flush(false);
        } else if ('terminate' === message.type) {
            this.flush(true);
        }
    }

    /** Sends what the content wrote since the last commit; resolves once the server answered. */
    flush(finish) {
        if (this.ended) {
            return this.queue;
        }

        if (finish) {
            this.ended = true;
            this.stop();
        }

        const values = Object.fromEntries(this.pending);
        this.pending.clear();
        this.queue = this.queue.then(() => this.send(values, finish));

        return this.queue;
    }

    async send(values, finish) {
        const body = this.body(values, finish);

        try {
            const response = await fetch(this.commitUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                keepalive: body.length < BEACON_BYTES,
                body,
            });
            const answer = await response.json().catch(() => ({}));

            this.answered(response.status, answer, values);
        } catch {
            this.restore(values);
            this.say('error');
        }
    }

    answered(status, answer, values) {
        if (200 !== status) {
            if (409 === status || 403 === status) {
                this.ended = true;
                this.stop();
                this.say(409 === status ? 'ended' : 'expired');
            } else {
                this.restore(values);
                this.say('error');
            }

            return;
        }

        if ('string' === typeof answer.token) {
            this.tokenValue = answer.token;
        }

        const errors = answer.errors ?? {};
        this.dispatch('committed', {
            detail: { errors, ended: true === answer.ended, timeUp: true === answer.time_up },
        });

        if (true === answer.time_up) {
            this.ended = true;
            this.stop();
            this.say('timeUp');

            return;
        }

        this.say(0 === Object.keys(errors).length ? 'saved' : 'refused');
        this.arm(answer.remaining);

        if ('string' === typeof answer.next) {
            this.go(answer.next);
        } else if (true === answer.exit) {
            window.location.assign(this.outlineUrlValue);
        }
    }

    /** Puts back values a commit could not send, before those written since. */
    restore(values) {
        const later = new Map(this.pending);
        this.pending = new Map([...Object.entries(values), ...later]);
    }

    /** Launches another activity of the course, as its own form would. */
    go(activity) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = this.launchUrlValue.replace(PLACEHOLDER, encodeURIComponent(activity));
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = this.csrfValue;
        form.append(token);
        document.body.append(form);
        form.requestSubmit();
    }

    /** Ends the session as the learner leaves, with whatever the content wrote. */
    leave() {
        if (this.ended) {
            return;
        }

        this.ended = true;
        this.stop();
        const body = this.body(Object.fromEntries(this.pending), true);
        this.pending.clear();
        const blob = new Blob([body], { type: 'text/plain;charset=UTF-8' });

        if (!navigator.sendBeacon?.(this.commitUrlValue, blob)) {
            fetch(this.commitUrlValue, { method: 'POST', credentials: 'same-origin', keepalive: true, body }).catch(
                () => {},
            );
        }
    }

    body(values, finish) {
        return JSON.stringify({ token: this.tokenValue, csrf: this.csrfValue, values, finish });
    }

    say(key) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = this.messagesValue[key] ?? '';
        }
    }
}
