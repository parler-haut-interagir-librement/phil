// placement() of each controller that places a panel: the same function, copied with each recipe.

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { placement as dropdown } from '../../dropdown-menu/assets/controllers/dropdown_menu_controller.js';
import { placement as sub } from '../../dropdown-menu/assets/controllers/dropdown_menu_sub_controller.js';
import { placement as date } from '../../date-picker/assets/controllers/date_picker_controller.js';
import { placement as popover } from '../../popover/assets/controllers/popover_controller.js';
import { placement as select } from '../../select/assets/controllers/select_controller.js';
import { placement as tooltip } from '../../tooltip/assets/controllers/tooltip_controller.js';

const viewport = { width: 1000, height: 800 };
const box = { width: 200, height: 100 };
const trigger = { top: 300, bottom: 340, left: 400, right: 500 };

for (const [name, placement] of Object.entries({ popover, tooltip, select, dropdown, sub, date })) {
    test(`${name}: below and centred by default`, () => {
        assert.deepEqual(placement(trigger, box, viewport), { top: 346, left: 350, side: 'bottom' });
    });

    test(`${name}: start and end, mirrored right to left`, () => {
        assert.equal(placement(trigger, box, viewport, { align: 'start' }).left, 400);
        assert.equal(placement(trigger, box, viewport, { align: 'end' }).left, 300);
        assert.equal(placement(trigger, box, viewport, { align: 'start', rtl: true }).left, 300);
        assert.equal(placement(trigger, box, viewport, { align: 'end', rtl: true }).left, 400);
        assert.equal(placement(trigger, box, viewport, { align: 'center', rtl: true }).left, 350);
    });

    test(`${name}: the sides`, () => {
        assert.deepEqual(placement(trigger, box, viewport, { side: 'top' }), { top: 194, left: 350, side: 'top' });
        assert.deepEqual(placement(trigger, box, viewport, { side: 'left', gap: 4 }), {
            top: 270,
            left: 196,
            side: 'left',
        });
        assert.deepEqual(placement(trigger, box, viewport, { side: 'right', gap: 4, align: 'start' }), {
            top: 300,
            left: 504,
            side: 'right',
        });
    });

    test(`${name}: flipped when there is no room and more on the other side`, () => {
        assert.equal(placement({ ...trigger, top: 750, bottom: 790 }, box, viewport).side, 'top');
        assert.equal(placement({ ...trigger, top: 10, bottom: 50 }, box, viewport, { side: 'top' }).side, 'bottom');
        assert.equal(placement({ ...trigger, left: 900, right: 990 }, box, viewport, { side: 'right' }).side, 'left');
        assert.equal(placement({ ...trigger, left: 10, right: 60 }, box, viewport, { side: 'left' }).side, 'right');
    });

    test(`${name}: kept on its side when the other has less room`, () => {
        assert.equal(placement({ ...trigger, top: 10, bottom: 780 }, box, viewport).side, 'bottom');
    });

    test(`${name}: pushed back inside the viewport`, () => {
        assert.equal(placement({ ...trigger, left: 5, right: 25 }, box, viewport).left, 0);
        assert.equal(placement({ ...trigger, left: 960, right: 990 }, box, viewport).left, 800);
        assert.equal(placement(trigger, { width: 2000, height: 10 }, viewport).left, 0);
    });
}
