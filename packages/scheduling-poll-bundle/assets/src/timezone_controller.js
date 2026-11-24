import { Controller } from '@hotwired/stimulus';

/**
 * Writes the dates of a scheduling poll in the time zone the respondent picks, before anything is posted. On a first
 * visit — no answer saved yet — it picks the browser's time zone in the select when the select offers it. Each
 * `time` target, whose `datetime` and `data-end` hold the start and the end, is then written again with the
 * browser's own words for dates. Without this controller the page writes them in the zone it was given, and the
 * zone posted with the answers is used from then on.
 */
export default class extends Controller {
    static targets = ['zone', 'time'];
    static values = { chosen: Boolean };

    connect() {
        if (!this.chosenValue && this.hasZoneTarget) {
            const browser = this.browserZone();

            if (browser && [...this.zoneTarget.options].some((option) => option.value === browser)) {
                this.zoneTarget.value = browser;
            }
        }

        this.render();
    }

    change() {
        this.render();
    }

    browserZone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
            return null;
        }
    }

    render() {
        if (!this.hasZoneTarget) {
            return;
        }

        const timeZone = this.zoneTarget.value;

        for (const time of this.timeTargets) {
            const start = new Date(time.getAttribute('datetime'));
            const end = new Date(time.dataset.end);

            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                continue;
            }

            try {
                const day = new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short', timeZone });
                const hour = new Intl.DateTimeFormat(undefined, { timeStyle: 'short', timeZone });
                const sameDay = this.dayOf(start, timeZone) === this.dayOf(new Date(end.getTime() - 1000), timeZone);
                time.textContent = `${day.format(start)} – ${sameDay ? hour.format(end) : day.format(end)}`;
            } catch {
                // A zone the browser does not know: the page keeps the words it was given.
            }
        }
    }

    dayOf(moment, timeZone) {
        return new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone }).format(
            moment,
        );
    }
}
