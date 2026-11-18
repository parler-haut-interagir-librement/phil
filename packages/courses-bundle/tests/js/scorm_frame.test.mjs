import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const model = JSON.parse(readFileSync(new URL('./fixture/model-1.2.json', import.meta.url), 'utf8'));

test('the frame puts the API of the session on its window and frames the SCO, for the application alone', async () => {
    const told = [];
    const frames = [];
    let listener;
    const parent = { postMessage: (message, origin) => told.push([message, origin]) };
    globalThis.window = {
        parent,
        addEventListener: (_name, callback) => {
            listener = callback;
        },
    };
    globalThis.document = {
        documentElement: { dataset: { parent: 'https://www.example.org' } },
        createElement: () => ({}),
        body: { append: (frame) => frames.push(frame) },
    };

    await import('../../assets/src/scorm_frame.js');
    assert.deepEqual(told[0], [{ phil: 'scorm', type: 'ready' }, 'https://www.example.org']);

    const state = {
        api: 'API',
        sco: '/content/c/a.html',
        title: 'Saws',
        model,
        values: { 'cmi.core.student_id': 'ada' },
    };
    listener({ source: {}, origin: 'https://www.example.org', data: { phil: 'scorm', type: 'init', state } });
    listener({ source: parent, origin: 'https://evil.example.net', data: { phil: 'scorm', type: 'init', state } });
    listener({ source: parent, origin: 'https://www.example.org', data: { type: 'init', state } });
    listener({
        source: parent,
        origin: 'https://www.example.org',
        data: { phil: 'scorm', type: 'init', state: { ...state, sco: '//evil.example.net/a.html' } },
    });
    listener({ source: parent, origin: 'https://www.example.org', data: { phil: 'scorm', type: 'other', state } });
    assert.equal(window.API, undefined);
    assert.equal(frames.length, 0);

    listener({ source: parent, origin: 'https://www.example.org', data: { phil: 'scorm', type: 'init', state } });
    listener({
        source: parent,
        origin: 'https://www.example.org',
        data: { phil: 'scorm', type: 'init', state: { ...state, sco: '/other.html' } },
    });
    assert.equal(frames.length, 1);
    assert.deepEqual(
        [frames[0].src, frames[0].title, frames[0].allow],
        ['/content/c/a.html', 'Saws', 'fullscreen; autoplay'],
    );

    const api = window.API;
    assert.equal(api.LMSInitialize(''), 'true');
    assert.equal(api.LMSGetValue('cmi.core.student_id'), 'ada');
    assert.equal(api.LMSSetValue('cmi.core.lesson_status', 'completed'), 'true');
    assert.equal(api.LMSCommit(''), 'true');
    assert.equal(api.LMSFinish(''), 'true');
    assert.deepEqual(told.slice(1), [
        [
            { phil: 'scorm', type: 'set', element: 'cmi.core.lesson_status', value: 'completed' },
            'https://www.example.org',
        ],
        [{ phil: 'scorm', type: 'commit' }, 'https://www.example.org'],
        [{ phil: 'scorm', type: 'terminate' }, 'https://www.example.org'],
    ]);
});
