// The timezone controller, under Node's test runner, with targets made of plain objects.

import assert from 'node:assert/strict';
import { test } from 'node:test';

import TimezoneController from '../../assets/src/timezone_controller.js';

const select = (value, offered) => ({ value, options: offered.map((zone) => ({ value: zone })) });

const time = (start, end) => ({
    textContent: 'as the page wrote it',
    dataset: { end },
    getAttribute: (name) => (name === 'datetime' ? start : null),
});

const controller = ({ zone, times = [], chosen = false, browser = 'Europe/Paris' }) => {
    const timezone = new TimezoneController();
    timezone.hasZoneTarget = zone !== undefined;
    timezone.zoneTarget = zone;
    timezone.timeTargets = times;
    timezone.chosenValue = chosen;
    timezone.browserZone = () => browser;
    timezone.connect();

    return timezone;
};

const words = (moment, options) => new Intl.DateTimeFormat(undefined, options).format(new Date(moment));

test('a first visit picks the browser zone when the select offers it, and writes the dates in it', () => {
    const zone = select('America/Toronto', ['America/Toronto', 'Europe/Paris']);
    const slot = time('2026-12-05T19:00:00-05:00', '2026-12-05T21:00:00-05:00');

    controller({ zone, times: [slot] });

    assert.equal(zone.value, 'Europe/Paris');
    assert.equal(
        slot.textContent,
        `${words('2026-12-05T19:00:00-05:00', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Europe/Paris' })} – ${words('2026-12-05T21:00:00-05:00', { timeStyle: 'short', timeZone: 'Europe/Paris' })}`,
    );
});

test('a date that ends on another day in the zone writes both days', () => {
    const zone = select('Asia/Tokyo', ['Asia/Tokyo']);
    const slot = time('2026-12-05T09:00:00-05:00', '2026-12-05T11:00:00-05:00');

    controller({ zone, times: [slot], chosen: true });

    assert.equal(
        slot.textContent,
        `${words('2026-12-05T09:00:00-05:00', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Asia/Tokyo' })} – ${words('2026-12-05T11:00:00-05:00', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Asia/Tokyo' })}`,
    );
});

test('a respondent who answered keeps their zone, and a zone the select lacks is not picked', () => {
    const answered = select('America/Toronto', ['America/Toronto', 'Europe/Paris']);
    controller({ zone: answered, chosen: true });
    assert.equal(answered.value, 'America/Toronto');

    const lacking = select('America/Toronto', ['America/Toronto']);
    controller({ zone: lacking });
    assert.equal(lacking.value, 'America/Toronto');

    const unknown = select('America/Toronto', ['America/Toronto']);
    controller({ zone: unknown, browser: null });
    assert.equal(unknown.value, 'America/Toronto');
});

test('changing the zone writes the dates again; a date unreadable or a zone unknown keeps the words given', () => {
    const zone = select('America/Toronto', ['America/Toronto', 'UTC', 'Not/AZone']);
    const slot = time('2026-12-05T19:00:00-05:00', '2026-12-05T21:00:00-05:00');
    const broken = time('not a date', '2026-12-05T21:00:00-05:00');
    const timezone = controller({ zone, times: [slot, broken], chosen: true });

    zone.value = 'UTC';
    timezone.change();
    assert.match(slot.textContent, /–/);
    assert.notEqual(slot.textContent, 'as the page wrote it');
    assert.equal(broken.textContent, 'as the page wrote it');

    const before = slot.textContent;
    zone.value = 'Not/AZone';
    timezone.change();
    assert.equal(slot.textContent, before);
});

test('without a select, nothing is written', () => {
    const slot = time('2026-12-05T19:00:00-05:00', '2026-12-05T21:00:00-05:00');
    controller({ zone: undefined, times: [slot] });

    assert.equal(slot.textContent, 'as the page wrote it');
});
