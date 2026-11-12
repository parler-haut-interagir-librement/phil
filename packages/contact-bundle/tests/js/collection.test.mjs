// The collection controller, under Node's test runner, Stimulus resolved to a stub by `register.mjs`:
// `node --import ./tests/js/register.mjs --test tests/js`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import CollectionController from '../../assets/src/collection_controller.js';

const element = (children = []) => {
    const node = {
        children,
        removed: false,
        contains: (target) => target === node || children.includes(target),
        remove: () => {
            node.removed = true;
        },
    };

    return node;
};

const controller = (entries, values = {}) => {
    const instance = new CollectionController();
    const inserted = [];
    instance.entryTargets = entries;
    instance.entriesTarget = {
        dataset: { prototype: '<div id="card___name__">__name__</div>' },
        insertAdjacentHTML: (position, html) => inserted.push([position, html]),
    };
    instance.hasIndexValue = 'index' in values;
    instance.indexValue = values.index ?? 0;
    instance.prototypeNameValue = values.prototypeName ?? '__name__';
    instance.inserted = inserted;

    return instance;
};

test('a new entry takes the next index after those the page came with', () => {
    const instance = controller([element(), element()]);
    instance.connect();
    let prevented = 0;
    instance.add({ preventDefault: () => (prevented += 1) });
    instance.add();

    assert.deepEqual(instance.inserted, [['beforeend', '<div id="card_2">2</div>'], ['beforeend', '<div id="card_3">3</div>']]);
    assert.equal(instance.indexValue, 4);
    assert.equal(prevented, 1);
    assert.deepEqual(instance.dispatched, [['added', { index: 2 }], ['added', { index: 3 }]]);
});

test('an index or a placeholder given is kept', () => {
    const instance = controller([element()], { index: 7, prototypeName: '__card__' });
    instance.entriesTarget.dataset.prototype = '<i>__card__ __name__</i>';
    instance.connect();
    instance.add();

    assert.deepEqual(instance.inserted, [['beforeend', '<i>7 __name__</i>']]);
});

test('the entry of the button is removed, and only it', () => {
    const button = {};
    const kept = element();
    const removed = element([button]);
    const instance = controller([kept, removed]);
    let prevented = 0;

    instance.remove({ target: button, preventDefault: () => (prevented += 1) });
    instance.remove({ target: {}, preventDefault: () => (prevented += 1) });

    assert.equal(removed.removed, true);
    assert.equal(kept.removed, false);
    assert.equal(prevented, 2);
    assert.deepEqual(instance.dispatched, [['removed', undefined]]);
});
