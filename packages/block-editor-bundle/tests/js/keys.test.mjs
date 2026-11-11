// The keyboard: shortcuts and the marks typed at the start of a block.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { autoformat, isApple, shortcut } from '../../assets/src/lib/keys.js';

const key = (key, modifiers = {}) => ({ key, code: '', metaKey: false, ctrlKey: false, altKey: false, shiftKey: false, ...modifiers });

test('the command key is ⌘ on Apple systems, Ctrl elsewhere', () => {
    assert.ok(isApple('MacIntel'));
    assert.ok(isApple('iPhone'));
    assert.ok(!isApple('Win32'));
    assert.ok(!isApple(undefined));
    assert.equal(shortcut(key('b', { metaKey: true }), true), 'bold');
    assert.equal(shortcut(key('b', { ctrlKey: true }), false), 'bold');
    assert.equal(shortcut(key('b', { ctrlKey: true }), true), null);
    assert.equal(shortcut(key('b', { metaKey: true }), false), null);
    assert.equal(shortcut(key('B', { ctrlKey: true })), 'bold');
});

test('each shortcut names its action', () => {
    assert.equal(shortcut(key('x', { ctrlKey: true, shiftKey: true })), 'strikethrough');
    assert.equal(shortcut(key('ArrowUp', { ctrlKey: true, shiftKey: true })), 'moveUp');
    assert.equal(shortcut(key('ArrowDown', { ctrlKey: true, shiftKey: true })), 'moveDown');
    assert.equal(shortcut(key('z', { ctrlKey: true })), 'undo');
    assert.equal(shortcut(key('Z', { ctrlKey: true, shiftKey: true })), 'redo');
    assert.equal(shortcut(key('¡', { ctrlKey: true, altKey: true, code: 'Digit1' })), 'heading1', 'the digit is read from its key');
    assert.equal(shortcut(key('0', { ctrlKey: true, altKey: true, code: 'Digit0' })), 'paragraph');
    assert.equal(shortcut(key('Escape')), 'selectBlock');
    assert.equal(shortcut(key('b')), null);
    assert.equal(shortcut(key('q', { ctrlKey: true })), null);
    assert.equal(shortcut({ code: undefined, key: undefined }), null);
});

test('marks typed at the start of a paragraph', () => {
    assert.deepEqual(autoformat('# '), { type: 'header', options: { level: 1 } });
    assert.deepEqual(autoformat('### '), { type: 'header', options: { level: 3 } });
    assert.equal(autoformat('####### '), null);
    assert.deepEqual(autoformat('- '), { type: 'list', options: { style: 'unordered' } });
    assert.deepEqual(autoformat('* '), { type: 'list', options: { style: 'unordered' } });
    assert.deepEqual(autoformat('12) '), { type: 'list', options: { style: 'ordered' } });
    assert.deepEqual(autoformat('[] '), { type: 'list', options: { style: 'checklist', checked: false } });
    assert.deepEqual(autoformat('[ ] '), { type: 'list', options: { style: 'checklist', checked: false } });
    assert.deepEqual(autoformat('[x] '), { type: 'list', options: { style: 'checklist', checked: true } });
    assert.deepEqual(autoformat('> '), { type: 'quote', options: {} });
    assert.deepEqual(autoformat('>> '), { type: 'toggle', options: {} });
    assert.deepEqual(autoformat('! '), { type: 'alert', options: { type: 'info' } });
    assert.deepEqual(autoformat('```php '), { type: 'code', options: { language: 'php' } });
    assert.equal(autoformat('```php'), null, 'the language is still being typed');
    assert.deepEqual(autoformat('---'), { type: 'delimiter', options: {} });
    assert.equal(autoformat('text # '), null);
    assert.equal(autoformat('#'), null);
});
