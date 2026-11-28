import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { test } from 'node:test';
import { CHROMIUM_ARGS, createLimiter, isJson, mermaidConfig, readBody, reason, svgId, withTimeout } from '../../docker/mermaid/lib.mjs';

test('configures Mermaid strictly, with keys a directive cannot change', () => {
    const config = mermaidConfig(20000);

    assert.equal(config.securityLevel, 'strict');
    assert.equal(config.htmlLabels, false);
    assert.deepEqual(config.flowchart, { htmlLabels: false });
    assert.deepEqual(config.class, { htmlLabels: false });
    assert.equal(config.maxTextSize, 20000);
    assert.equal(config.maxEdges, 500);
    assert.equal(config.startOnLoad, false);

    for (const key of ['secure', 'securityLevel', 'htmlLabels', 'flowchart', 'class', 'themeCSS', 'maxTextSize', 'maxEdges', 'dompurifyConfig', 'startOnLoad']) {
        assert.ok(config.secure.includes(key), key);
    }
});

test('closes every way out of Chromium', () => {
    for (const argument of ['--no-sandbox', '--proxy-server=127.0.0.1:9', '--host-resolver-rules=MAP * ~NOTFOUND', '--disable-extensions', '--disable-background-networking', '--js-flags=--jitless']) {
        assert.ok(CHROMIUM_ARGS.includes(argument), argument);
    }
});

test('runs at most so many tasks at once and lets so many wait', async () => {
    const limiter = createLimiter(1, 1);
    let release;
    const first = limiter.run(() => new Promise((resolve) => {
        release = resolve;
    }));
    assert.equal(limiter.running, 1);

    const second = limiter.run(async () => 'second');
    assert.equal(limiter.waiting, 1);
    await assert.rejects(limiter.run(async () => 'third'), /busy/);

    release('first');
    assert.equal(await first, 'first');
    assert.equal(await second, 'second');
    assert.equal(limiter.running, 0);
    assert.equal(limiter.waiting, 0);
});

test('frees its place when a task fails', async () => {
    const limiter = createLimiter(1, 0);

    await assert.rejects(limiter.run(async () => {
        throw new Error('failed');
    }), /failed/);
    assert.equal(await limiter.run(async () => 'next'), 'next');
});

test('gives up after its deadline', async () => {
    await assert.rejects(withTimeout(new Promise(() => {}), 5), /timeout/);
    assert.equal(await withTimeout(Promise.resolve('on time'), 1000), 'on time');
});

test('reads a body within its limit', async () => {
    const request = new EventEmitter();
    request.destroy = () => {
        request.destroyed = true;
    };
    const body = readBody(request, 5);
    request.emit('data', Buffer.from('ab'));
    request.emit('data', Buffer.from('cd'));
    request.emit('end');
    assert.equal(await body, 'abcd');

    const large = new EventEmitter();
    large.destroy = () => {
        large.destroyed = true;
    };
    const refused = readBody(large, 3);
    large.emit('data', Buffer.from('abcd'));
    await assert.rejects(refused, /too large/);
    assert.equal(large.destroyed, true);

    const broken = new EventEmitter();
    const failed = readBody(broken, 3);
    broken.emit('error', new Error('reset'));
    await assert.rejects(failed, /reset/);
});

test('names each diagram after its code', () => {
    assert.match(svgId('graph TD'), /^mermaid-[0-9a-f]{16}$/);
    assert.equal(svgId('graph TD'), svgId('graph TD'));
    assert.notEqual(svgId('graph TD'), svgId('graph LR'));
});

test('gives the reason without the files of the service', () => {
    assert.equal(reason(new Error('Parse error on line 1\n    at parse (/srv/mermaid/node_modules/x.js:1:1)\nExpecting X (file:///srv/a.js)')), 'Parse error on line 1');
    assert.equal(reason('plain'), 'plain');
    assert.equal(reason(new Error('x'.repeat(600))).length, 500);
});

test('accepts JSON alone', () => {
    assert.equal(isJson('application/json'), true);
    assert.equal(isJson('Application/JSON; charset=utf-8'), true);
    assert.equal(isJson('application/jsonp'), false);
    assert.equal(isJson('text/plain'), false);
    assert.equal(isJson(undefined), false);
});
