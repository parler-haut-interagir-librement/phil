// Archived trial. These are the measures of the ProseMirror trial, which put a ProseMirror view in each rich field
// (`richEngine: "prosemirror"`) and compared it with the editor's own fields; doc/prosemirror-trial.md keeps the method
// and the numbers. The trial's engine was removed in 0.1.1-alpha, so the script now runs the same scenarios on the
// editor's own fields alone, in a headless Chrome driven over the DevTools protocol, on the page of `router.php`
// served by PHP's built-in server: typing, marks by shortcut and at a collapsed caret, Enter and Backspace across
// blocks, undo, IME composition (Japanese, a dead key) as Chrome's own IME events, paste of Word, Google Docs and web
// page HTML, the accessibility tree of a field, the bytes served and the time to draw a long document. Prints one
// JSON report.
//
//     npm ci --ignore-scripts --prefix packages/block-editor-bundle/tests/Browser
//     node packages/block-editor-bundle/tests/Browser/measure.mjs [--chrome=/path/to/chrome] [--port=9190]

import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { FIXTURES, score } from './paste-fixtures.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const option = (name, fallback) =>
    process.argv.find((argument) => argument.startsWith('--' + name + '='))?.slice(name.length + 3) ?? fallback;
const chromePath = option('chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');
const port = Number(option('port', '9190'));
const origin = 'http://127.0.0.1:' + port;
const ENGINES = ['dom'];
const CONTROLLER = 'phil--block-editor-bundle--editor';
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function startServer() {
    const server = spawn('php', ['-S', '127.0.0.1:' + port, join(here, 'router.php')], { cwd: here, stdio: 'ignore' });

    return pause(500).then(() => server);
}

function startChrome(profile) {
    const chrome = spawn(chromePath, [
        '--headless=new',
        '--remote-debugging-port=0',
        '--user-data-dir=' + profile,
        '--no-first-run',
        '--no-default-browser-check',
        '--window-size=1200,900',
        'about:blank',
    ]);

    return new Promise((resolve, reject) => {
        let seen = '';
        chrome.stderr.on('data', (chunk) => {
            seen += chunk;
            const found = /DevTools listening on (ws:\/\/\S+)/.exec(seen);

            if (null !== found) {
                resolve({ chrome, url: found[1] });
            }
        });
        chrome.on('exit', () => reject(new Error('Chrome stopped: ' + seen)));
    });
}

/** A DevTools protocol connection, with flattened sessions. */
class Cdp {
    constructor(url) {
        this.next = 1;
        this.pending = new Map();
        this.listeners = [];
        this.socket = new WebSocket(url);
        this.socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);

            if (undefined !== message.id && this.pending.has(message.id)) {
                const { resolve, reject } = this.pending.get(message.id);
                this.pending.delete(message.id);
                undefined !== message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
            } else {
                this.listeners.forEach((listener) => listener(message));
            }
        });
        this.ready = new Promise((resolve) => this.socket.addEventListener('open', resolve));
    }

    send(method, params = {}, sessionId = undefined) {
        const id = this.next++;
        this.socket.send(JSON.stringify({ id, method, params, sessionId }));

        return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
    }
}

/** A page of the editor, for one engine. */
class Page {
    constructor(cdp, sessionId, targetId) {
        this.cdp = cdp;
        this.sessionId = sessionId;
        this.targetId = targetId;
        this.responses = [];
        cdp.listeners.push((message) => {
            if (message.sessionId === sessionId && 'Network.loadingFinished' === message.method) {
                this.responses.push(message.params.encodedDataLength);
            }
        });
    }

    static async open(cdp, engine, blocks = 1) {
        const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
        const page = new Page(cdp, sessionId, targetId);
        await page.send('Network.enable');
        await page.send('Network.setCacheDisabled', { cacheDisabled: true });
        await page.send('Page.enable');
        await page.send('Runtime.enable');
        await page.send('Page.navigate', { url: origin + '/?engine=' + engine + '&blocks=' + blocks });
        await page.until('undefined !== window.mounted');
        await pause(150);

        return page;
    }

    send(method, params = {}) {
        return this.cdp.send(method, params, this.sessionId);
    }

    async eval(expression) {
        const { result, exceptionDetails } = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true,
        });

        if (undefined !== exceptionDetails) {
            throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text);
        }

        return result.value;
    }

    async until(condition, seconds = 10) {
        for (let waited = 0; waited < seconds * 1000; waited += 50) {
            if (true === (await this.eval('(() => { try { return Boolean(' + condition + '); } catch (e) { return false; } })()'))) {
                return;
            }

            await pause(50);
        }

        throw new Error('Never true: ' + condition + ' ' + JSON.stringify(await this.eval('[location.href, document.readyState, document.documentElement.dataset.started, window.__errors, document.querySelectorAll(".pbe-block").length, typeof window.mounted]')));
    }

    close() {
        return this.cdp.send('Target.closeTarget', { targetId: this.targetId });
    }

    /** Clicks at the end of a block's field, which takes the focus and the caret. */
    async focus(index = 0) {
        const box = await this.eval(
            '(() => { const f = document.querySelectorAll(".pbe-rich[contenteditable]")[' +
                index +
                ']; const r = f.getBoundingClientRect(); return [r.right - 4, r.top + r.height / 2]; })()',
        );
        await this.mouse(box[0], box[1]);
        await this.eval(
            '(() => { const f = document.querySelectorAll(".pbe-rich[contenteditable]")[' +
                index +
                ']; const r = document.createRange(); r.selectNodeContents(f); r.collapse(false); const s = getSelection(); s.removeAllRanges(); s.addRange(r); })()',
        );
        await pause(60);
    }

    async mouse(x, y) {
        for (const type of ['mousePressed', 'mouseReleased']) {
            await this.send('Input.dispatchMouseEvent', { type, x, y, button: 'left', clickCount: 1 });
        }
    }

    async key(key, { code = key, keyCode = 0, text = undefined, modifiers = 0 } = {}) {
        const base = { key, code, windowsVirtualKeyCode: keyCode, nativeVirtualKeyCode: keyCode, modifiers };
        await this.send('Input.dispatchKeyEvent', { type: undefined === text ? 'rawKeyDown' : 'keyDown', text, ...base });
        await this.send('Input.dispatchKeyEvent', { type: 'keyUp', ...base });
    }

    async type(text) {
        for (const character of text) {
            const upper = character.toUpperCase();
            const letter = /^[A-Z]$/.test(upper);
            await this.key(character, {
                code: letter ? 'Key' + upper : ' ' === character ? 'Space' : '',
                keyCode: letter ? upper.charCodeAt(0) : ' ' === character ? 32 : 0,
                text: character,
            });
        }

        await pause(30);
    }

    /** ⌘ and a letter: the controller reads ⌘ as its command key on a Mac, Ctrl elsewhere. */
    async command(letter, { shift = false } = {}) {
        const mac = await this.eval('/mac/i.test(navigator.platform)');
        const modifiers = (mac ? 4 : 2) | (shift ? 8 : 0);
        const special = { '.': ['Period', 190], ',': ['Comma', 188] }[letter];
        await this.key(shift ? letter.toUpperCase() : letter, {
            code: special?.[0] ?? 'Key' + letter.toUpperCase(),
            keyCode: special?.[1] ?? letter.toUpperCase().charCodeAt(0),
            modifiers,
        });
        await pause(30);
    }

    /** Selects characters of a field's text, from `start` to `end`. */
    async select(index, start, end) {
        await this.eval(
            '(() => { const f = document.querySelectorAll(".pbe-rich[contenteditable]")[' +
                index +
                ']; const w = document.createTreeWalker(f, NodeFilter.SHOW_TEXT); const r = document.createRange(); let left = ' +
                start +
                ', right = ' +
                end +
                ', s = false; for (let n = w.nextNode(); n; n = w.nextNode()) { if (!s && n.length >= left) { r.setStart(n, left); s = true; } if (s && n.length >= right) { r.setEnd(n, right); break; } left -= n.length; right -= n.length; } const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r); })()',
        );
        await pause(60);
    }

    /** The blocks the editor writes into its input, saved at once. */
    async blocks() {
        await pause(30);

        return this.eval(
            '(() => { const e = document.querySelector("[data-controller]"); application.getControllerForElementAndIdentifier(e, "' +
                CONTROLLER +
                '").save(true); return JSON.parse(document.querySelector("#body").value || "{\\"blocks\\":[]}").blocks.map((b) => b.data.text); })()',
        );
    }

    async errors() {
        return this.eval('window.__errors ?? []');
    }
}

const SHORTCUTS = [
    ['bold', 'b', false, '<b>'],
    ['italic', 'i', false, '<i>'],
    ['underline', 'u', false, '<u class="cdx-underline">'],
    ['strikethrough', 'x', true, '<s class="cdx-strikethrough">'],
    ['inlineCode', 'e', false, '<code class="inline-code">'],
    ['marker', 'h', true, '<mark class="cdx-marker">'],
    ['superscript', '.', false, '<sup>'],
    ['subscript', ',', false, '<sub>'],
];

/** Each scenario: a name, what is expected, and how to get the value on a page. */
const SCENARIOS = [
    [
        'typing',
        ['Hello world'],
        async (page) => {
            await page.focus();
            await page.type('Hello world');

            return page.blocks();
        },
    ],
    ...SHORTCUTS.map(([mark, letter, shift, tag]) => [
        'mark by shortcut: ' + mark,
        ['one ' + tag + 'two</' + /^<(\w+)/.exec(tag)[1] + '> three'],
        async (page) => {
            await page.focus();
            await page.type('one two three');
            await page.select(0, 4, 7);
            await page.command(letter, { shift });

            return page.blocks();
        },
    ]),
    [
        'mark at a collapsed caret, then typing',
        ['plain <b>bold</b>'],
        async (page) => {
            await page.focus();
            await page.type('plain ');
            await page.command('b');
            await page.type('bold');

            return page.blocks();
        },
    ],
    [
        'mark at the caret of an empty field, then typing',
        ['<i>first</i> words'],
        async (page) => {
            await page.focus();
            await page.command('i');
            await page.type('first');
            await page.command('i');
            await page.type(' words');

            return page.blocks();
        },
    ],
    [
        'mark at a collapsed caret, turned off before typing',
        ['plain text'],
        async (page) => {
            await page.focus();
            await page.type('plain ');
            await page.command('b');
            await page.command('b');
            await page.type('text');

            return page.blocks();
        },
    ],
    [
        'mark removed by its shortcut again',
        ['one two three'],
        async (page) => {
            await page.focus();
            await page.type('one two three');
            await page.select(0, 4, 7);
            await page.command('b');
            await page.select(0, 4, 7);
            await page.command('b');

            return page.blocks();
        },
    ],
    [
        'Enter splits the block at the caret',
        ['abc', 'def'],
        async (page) => {
            await page.focus();
            await page.type('abcdef');
            await page.select(0, 3, 3);
            await page.key('Enter', { keyCode: 13, text: '\r' });

            return page.blocks();
        },
    ],
    [
        'Enter then typing goes to the new block',
        ['abc', 'xyz'],
        async (page) => {
            await page.focus();
            await page.type('abc');
            await page.key('Enter', { keyCode: 13, text: '\r' });
            await pause(60);
            await page.type('xyz');

            return page.blocks();
        },
    ],
    [
        'Backspace at the start merges with the block before',
        ['abcdef'],
        async (page) => {
            await page.focus();
            await page.type('abcdef');
            await page.select(0, 3, 3);
            await page.key('Enter', { keyCode: 13, text: '\r' });
            await pause(60);
            await page.select(1, 0, 0);
            await page.key('Backspace', { keyCode: 8 });

            return page.blocks();
        },
    ],
    [
        'Shift+Enter writes a line break',
        ['one<br>two'],
        async (page) => {
            await page.focus();
            await page.type('one');
            await page.key('Enter', { keyCode: 13, text: '\r', modifiers: 8 });
            await page.type('two');

            return page.blocks();
        },
    ],
    [
        'undo after a pause takes back the second burst only',
        ['first'],
        async (page) => {
            await page.focus();
            await page.type('first');
            await pause(900);
            await page.type(' second');
            await page.command('z');
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'redo gives the second burst back',
        ['first second'],
        async (page) => {
            await page.focus();
            await page.type('first');
            await pause(900);
            await page.type(' second');
            await page.command('z');
            await pause(60);
            await page.command('z', { shift: true });
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'undo of a split block',
        ['abcdef'],
        async (page) => {
            await page.focus();
            await page.type('abcdef');
            await pause(900);
            await page.select(0, 3, 3);
            await page.key('Enter', { keyCode: 13, text: '\r' });
            await pause(60);
            await page.command('z');
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'IME: Japanese composed then committed',
        ['日本語です'],
        async (page) => {
            await page.focus();

            for (const step of ['に', 'にほ', 'にほん', 'にほんご', '日本語']) {
                await page.send('Input.imeSetComposition', {
                    text: step,
                    selectionStart: step.length,
                    selectionEnd: step.length,
                });
                await pause(20);
            }

            await page.send('Input.insertText', { text: '日本語' });
            await page.send('Input.imeSetComposition', { text: 'です', selectionStart: 2, selectionEnd: 2 });
            await page.send('Input.insertText', { text: 'です' });
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'IME: composition inside bold text',
        ['<b>ab日本c</b>'],
        async (page) => {
            await page.focus();
            await page.type('abc');
            await page.select(0, 0, 3);
            await page.command('b');
            await page.select(0, 2, 2);

            for (const step of ['に', 'にほ', 'にほん', '日本']) {
                await page.send('Input.imeSetComposition', { text: step, selectionStart: step.length, selectionEnd: step.length });
                await pause(20);
            }

            await page.send('Input.insertText', { text: '日本' });
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'IME: composition starting with a Markdown mark is not turned into a heading mid-way',
        ['＃ 見出し'],
        async (page) => {
            await page.focus();

            for (const step of ['＃', '＃ ', '＃ み', '＃ みだし', '＃ 見出し']) {
                await page.send('Input.imeSetComposition', { text: step, selectionStart: step.length, selectionEnd: step.length });
                await pause(20);
            }

            await page.send('Input.insertText', { text: '＃ 見出し' });
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'dead key: ´ then e gives é',
        ['café'],
        async (page) => {
            await page.focus();
            await page.type('caf');
            await page.send('Input.imeSetComposition', { text: '´', selectionStart: 1, selectionEnd: 1 });
            await pause(20);
            await page.send('Input.insertText', { text: 'é' });
            await pause(60);

            return page.blocks();
        },
    ],
    [
        'emoji and astral characters',
        ['a😀b𝔸c'],
        async (page) => {
            await page.focus();
            await page.send('Input.insertText', { text: 'a😀b𝔸c' });
            await pause(60);

            return page.blocks();
        },
    ],
    ...Object.entries(FIXTURES).map(([name, fixture]) => [
        'paste: ' + name,
        (actual) => score(fixture, actual.join('\n')),
        async (page) => {
            await page.focus();
            await page.type('x');
            await page.eval(
                '(() => { const f = document.querySelector(".pbe-rich[contenteditable]"); const d = new DataTransfer(); d.setData("text/html", ' +
                    JSON.stringify(fixture.html) +
                    '); d.setData("text/plain", ' +
                    JSON.stringify(fixture.text) +
                    '); f.dispatchEvent(new ClipboardEvent("paste", { clipboardData: d, bubbles: true, cancelable: true })); })()',
            );
            await pause(80);

            return page.blocks();
        },
    ]),
];

/** The accessibility node of the first field, as a screen reader is given it. */
async function accessibility(page) {
    await page.send('Accessibility.enable');
    const { nodeId } = await page.send('DOM.getDocument').then(({ root }) =>
        page.send('DOM.querySelector', { nodeId: root.nodeId, selector: '.pbe-rich[contenteditable]' }),
    );
    const { nodes } = await page.send('Accessibility.getPartialAXTree', { nodeId, fetchRelatives: false });
    const node = nodes[0];

    return {
        role: node.role?.value,
        name: node.name?.value ?? '',
        properties: Object.fromEntries((node.properties ?? []).map((property) => [property.name, property.value.value])),
        attributes: await page.eval(
            '(() => { const f = document.querySelector(".pbe-rich[contenteditable]"); return Object.fromEntries([...f.attributes].map((a) => [a.name, a.value])); })()',
        ),
    };
}

async function run() {
    const profile = mkdtempSync(join(process.env.TMPDIR ?? tmpdir(), 'pbe-measure-'));
    const server = await startServer();
    const { chrome, url } = await startChrome(profile);
    const cdp = new Cdp(url);
    await cdp.ready;
    const report = { chrome: (await cdp.send('Browser.getVersion')).product, scenarios: [], weight: {}, timing: {} };

    try {
        for (const [name, expected, scenario] of SCENARIOS) {
            const row = { name, ...('function' === typeof expected ? {} : { expected }) };

            for (const engine of ENGINES) {
                const page = await Page.open(cdp, engine);

                try {
                    const actual = await scenario(page);
                    const checked =
                        'function' === typeof expected
                            ? expected(actual)
                            : { pass: JSON.stringify(actual) === JSON.stringify(expected) };
                    row[engine] = { actual, ...checked };
                } catch (error) {
                    row[engine] = { error: String(error.message ?? error), pass: false };
                }

                await page.close();
            }

            report.scenarios.push(row);
        }

        for (const engine of ENGINES) {
            const page = await Page.open(cdp, engine);
            report.weight[engine] = {
                requests: page.responses.length,
                bytes: page.responses.reduce((sum, bytes) => sum + bytes, 0),
            };
            report.accessibility ??= {};
            report.accessibility[engine] = await accessibility(page);

            await page.close();
        }

        for (const blocks of [50, 500]) {
            for (const engine of ENGINES) {
                const runs = [];

                for (let attempt = 0; attempt < 5; attempt++) {
                    const page = await Page.open(cdp, engine, blocks);
                    runs.push(await page.eval('window.mounted - window.started'));
                    await page.close();
                }

                runs.sort((a, b) => a - b);
                report.timing[engine + ' ' + blocks + ' blocks, ms to editable (median of 5)'] = Math.round(runs[2]);
                const page = await Page.open(cdp, engine, blocks);
                report.timing[engine + ' ' + blocks + ' blocks, ms to draw again (median of 9)'] = await page.eval(
                    '(() => { const c = application.getControllerForElementAndIdentifier(document.querySelector("[data-controller]"), "' +
                        CONTROLLER +
                        '"); const runs = []; for (let i = 0; i < 9; i++) { const t = performance.now(); c.render(); runs.push(performance.now() - t); } runs.sort((a, b) => a - b); return Math.round(runs[4] * 10) / 10; })()',
                );
                await page.close();
            }
        }
    } finally {
        cdp.socket.close();
        chrome.kill();
        server.kill();
        await pause(300);
        rmSync(profile, { recursive: true, force: true });
    }

    report.summary = Object.fromEntries(
        ENGINES.map((engine) => [
            engine,
            report.scenarios.filter((row) => row[engine].pass).length + ' / ' + report.scenarios.length,
        ]),
    );
    process.stdout.write(JSON.stringify(report, null, 2) + '\n');
}

await run();
