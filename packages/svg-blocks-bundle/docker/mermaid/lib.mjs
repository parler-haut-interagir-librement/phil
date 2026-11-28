// The parts of the Mermaid service that need neither Chromium nor Mermaid: the limiter, the deadline, the body read
// within its limit, the identifier of a diagram's SVG, the reason given for a refusal, and the configuration of
// Mermaid and of Chromium. Tested under Node without the browser.

import { createHash } from 'node:crypto';

/** Mermaid's configuration: strict security, labels as SVG text, and the keys a diagram's directive cannot change. */
export function mermaidConfig(maxCodeLength) {
    return {
        startOnLoad: false,
        securityLevel: 'strict',
        htmlLabels: false,
        flowchart: { htmlLabels: false },
        class: { htmlLabels: false },
        theme: 'default',
        maxTextSize: maxCodeLength,
        maxEdges: 500,
        // A `%%{init: …}%%` directive in a diagram may not change these: neither the security level, nor HTML labels,
        // nor the CSS a theme adds.
        secure: ['secure', 'securityLevel', 'startOnLoad', 'maxTextSize', 'maxEdges', 'htmlLabels', 'flowchart', 'class', 'themeCSS', 'dompurifyConfig'],
    };
}

/**
 * Chromium's arguments. Its own sandbox needs privileges a container does not give, so it is off; everything a page
 * could reach outside is closed instead: no proxy but a closed port, no name resolved, no extension, no background
 * service, no JIT.
 */
export const CHROMIUM_ARGS = [
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--disable-extensions',
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-default-apps',
    '--disable-sync',
    '--no-first-run',
    '--no-default-browser-check',
    '--proxy-server=127.0.0.1:9',
    '--proxy-bypass-list=<-loopback>',
    '--host-resolver-rules=MAP * ~NOTFOUND',
    '--js-flags=--jitless',
];

/**
 * A limiter: at most `concurrency` tasks run at once, at most `queue` wait; beyond, `run` rejects with `busy`.
 */
export function createLimiter(concurrency, queue) {
    let running = 0;
    const waiting = [];

    return {
        get running() {
            return running;
        },
        get waiting() {
            return waiting.length;
        },
        async run(task) {
            if (running >= concurrency) {
                if (waiting.length >= queue) {
                    throw new Error('busy');
                }

                await new Promise((resolve) => waiting.push(resolve));
            }

            running++;

            try {
                return await task();
            } finally {
                running--;
                waiting.shift()?.();
            }
        },
    };
}

/** The promise, or a rejection with `timeout` after `ms`. */
export function withTimeout(promise, ms) {
    let timer;

    return Promise.race([
        promise,
        new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error('timeout')), ms);
        }),
    ]).finally(() => clearTimeout(timer));
}

/** The body of a request as text, refused with `too large` as soon as it passes `maxBytes`. */
export function readBody(request, maxBytes) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;
        request.on('data', (chunk) => {
            size += chunk.length;

            if (size > maxBytes) {
                reject(new Error('too large'));
                request.destroy();

                return;
            }

            chunks.push(chunk);
        });
        request.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
        request.on('error', reject);
    });
}

/**
 * The identifier of a diagram's SVG, from its code: two diagrams of a page never share the identifiers their styles
 * and markers point to.
 */
export function svgId(code) {
    return 'mermaid-' + createHash('sha256').update(code).digest('hex').slice(0, 16);
}

/** Mermaid's reason for refusing a diagram, without the stack frames that name files of the service. */
export function reason(error) {
    return String(error?.message ?? error)
        .split('\n')
        .filter((line) => !/^\s+at |\((?:file:|\/)/.test(line))
        .join('\n')
        .trim()
        .slice(0, 500);
}

/** Whether a request's content type is JSON, with or without its charset. */
export function isJson(contentType) {
    return /^application\/json\s*(?:;|$)/i.test(String(contentType ?? ''));
}
