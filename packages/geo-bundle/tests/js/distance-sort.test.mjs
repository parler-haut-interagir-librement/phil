// The distance sort, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { EARTH_RADIUS, distance, formatDistance, positionOf, sortByDistance } from '../../assets/src/distance.js';
import DistanceSortController from '../../assets/src/distance_sort_controller.js';

const montreal = { latitude: 45.5017, longitude: -73.5673 };
const quebec = { latitude: 46.8139, longitude: -71.208 };
const toronto = { latitude: 43.6532, longitude: -79.3832 };

test('the distance is the great circle, as the PHP library computes it', () => {
    assert.equal(EARTH_RADIUS, 6371008.8);
    assert.equal(distance(montreal, montreal), 0);
    assert.ok(Math.abs(distance(montreal, quebec) - 233_029.3) < 0.1);
    assert.ok(Math.abs(distance(montreal, toronto) - 504_262.72) < 0.1);
    assert.equal(distance(montreal, quebec), distance(quebec, montreal));
    assert.ok(Math.abs(distance({ latitude: 0, longitude: 0 }, { latitude: 0, longitude: 180 }) - Math.PI * EARTH_RADIUS) < 1e-6);
});

test('places are sorted nearest first, those without a position last, in their order', () => {
    const places = [
        { name: 'toronto', at: toronto },
        { name: 'nowhere', at: null },
        { name: 'quebec', at: quebec },
        { name: 'elsewhere', at: null },
        { name: 'montreal', at: montreal },
        { name: 'montreal again', at: montreal },
    ];

    const sorted = sortByDistance(places, montreal, (place) => place.at);

    assert.deepEqual(sorted.map(({ item }) => item.name), ['montreal', 'montreal again', 'quebec', 'toronto', 'nowhere', 'elsewhere']);
    assert.equal(sorted[0].meters, 0);
    assert.equal(sorted[4].meters, null);
    assert.deepEqual(sortByDistance([{ at: null }, { at: montreal }], quebec, (place) => place.at).map(({ meters }) => meters === null), [false, true]);
});

test('a distance is written for people', () => {
    assert.equal(formatDistance(849.6, 'metric', 'en'), '850 m');
    assert.equal(formatDistance(999.4, 'metric', 'en'), '999 m');
    assert.equal(formatDistance(1000, 'metric', 'en'), '1 km');
    assert.equal(formatDistance(12_345, 'metric', 'fr'), '12,3 km');
    assert.equal(formatDistance(1609.344 / 2, 'imperial', 'en'), '0.5 mi');
    assert.equal(formatDistance(500, undefined, 'en'), '500 m');
    assert.equal(formatDistance(999.6, 'metric', 'en'), '1 km');
    assert.equal(formatDistance(999.5, 'metric', 'en'), '1 km');
    assert.equal(formatDistance(12_345, 'metric', 'fr_CA'), '12,3 km');
    assert.equal(formatDistance(1609.344 / 2, 'imperial', 'en_US'), '0.5 mi');
    assert.equal(formatDistance(500, 'metric', ''), formatDistance(500, 'metric'));
});

test('a position is read from the data attributes', () => {
    assert.deepEqual(positionOf({ dataset: { latitude: '45.5', longitude: '-73.5' } }), { latitude: 45.5, longitude: -73.5 });
    assert.equal(positionOf({ dataset: { latitude: '45.5' } }), null);
    assert.equal(positionOf({ dataset: { longitude: '-73.5' } }), null);
    assert.equal(positionOf({ dataset: { latitude: 'north', longitude: '-73.5' } }), null);
    assert.equal(positionOf({ dataset: {} }), null);
});

const withNavigator = (value, run) => {
    const original = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    Object.defineProperty(globalThis, 'navigator', { value, configurable: true });

    try {
        run();
    } finally {
        if (original) {
            Object.defineProperty(globalThis, 'navigator', original);
        } else {
            delete globalThis.navigator;
        }
    }
};

const element = (name, at) => {
    const label = { textContent: 'old' };

    return {
        name,
        dataset: at ? { latitude: String(at.latitude), longitude: String(at.longitude) } : {},
        label,
        querySelector: (selector) => (selector === '[data-distance]' ? label : null),
    };
};

const parent = () => {
    const children = [];

    return {
        children,
        appendChild(child) {
            const index = children.indexOf(child);

            if (index !== -1) {
                children.splice(index, 1);
            }

            children.push(child);
            child.parentNode = this;
        },
    };
};

const controllerFor = (items, values = {}) => {
    const list = parent();
    items.forEach((item) => list.appendChild(item));
    const controller = new DistanceSortController();
    const dispatched = [];
    controller.itemTargets = items;
    controller.dispatch = (name, options = {}) => dispatched.push([name, options.detail]);
    controller.hasLatitudeValue = 'latitude' in values;
    controller.hasLongitudeValue = 'longitude' in values;
    controller.latitudeValue = values.latitude ?? 0;
    controller.longitudeValue = values.longitude ?? 0;
    controller.locateValue = values.locate ?? false;
    controller.systemValue = values.system ?? 'metric';
    controller.hasLocaleValue = 'locale' in values;
    controller.localeValue = values.locale ?? '';

    return { controller, list, dispatched };
};

test('the controller sorts from a given origin and writes each distance', () => {
    assert.deepEqual(DistanceSortController.targets, ['item']);
    assert.deepEqual(DistanceSortController.values, {
        latitude: Number,
        longitude: Number,
        locate: { type: Boolean, default: false },
        system: { type: String, default: 'metric' },
        locale: String,
    });

    const items = [element('toronto', toronto), element('nowhere', null), element('montreal', montreal)];
    const noLabel = { name: 'quebec', dataset: { latitude: String(quebec.latitude), longitude: String(quebec.longitude) }, querySelector: () => null };
    items.push(noLabel);
    const { controller, list, dispatched } = controllerFor(items, { ...montreal, locale: 'fr', locate: true });

    controller.connect();

    assert.deepEqual(list.children.map((item) => item.name), ['montreal', 'quebec', 'toronto', 'nowhere']);
    assert.equal(items[2].label.textContent, '0 m');
    assert.match(items[0].label.textContent, /^50\d,\d km$/);
    assert.equal(items[1].label.textContent, '');
    assert.deepEqual(dispatched, [['sorted', { origin: montreal }]]);
});

test('the controller asks the browser for the position when told to', () => {
    const asked = [];
    withNavigator({ geolocation: { getCurrentPosition: (success, failure, options) => asked.push({ success, failure, options }) } }, () => {
    const items = [element('toronto', toronto), element('quebec', quebec)];
    const { controller, list, dispatched } = controllerFor(items, { locate: true, system: 'imperial', locale: 'en' });

    controller.connect();
    assert.equal(asked.length, 1);
    assert.deepEqual(asked[0].options, { maximumAge: 600000, timeout: 10000 });

    asked[0].success({ coords: montreal });
    assert.deepEqual(list.children.map((item) => item.name), ['quebec', 'toronto']);
    assert.match(items[1].label.textContent, / mi$/);

    asked[0].failure();
    assert.deepEqual(dispatched.map(([name]) => name), ['sorted', 'denied']);
    });
});

test('the controller waits for an action without an origin, and says when no position comes', () => {
    const items = [element('quebec', quebec)];
    const { controller, dispatched } = controllerFor(items);

    controller.connect();
    assert.deepEqual(dispatched, []);

    withNavigator(undefined, () => controller.locate());
    assert.deepEqual(dispatched, [['denied', undefined]]);

    withNavigator({}, () => controller.locate());
    assert.equal(dispatched.length, 2);

    const onlyLatitude = controllerFor([element('x', quebec)], { latitude: 1 });
    onlyLatitude.controller.connect();
    assert.deepEqual(onlyLatitude.dispatched, []);
});

test('the controller takes the Symfony locale of the request as it is', () => {
    const items = [element('toronto', toronto), element('montreal', montreal)];
    const { controller, list, dispatched } = controllerFor(items, { ...montreal, locale: 'fr_CA' });

    controller.connect();

    assert.deepEqual(list.children.map((item) => item.name), ['montreal', 'toronto']);
    assert.match(items[0].label.textContent, /^50\d,\d km$/);
    assert.deepEqual(dispatched, [['sorted', { origin: montreal }]]);
});
