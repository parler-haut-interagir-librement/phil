// Mermaid diagrams drawn as SVG over HTTP, for phil/svg-blocks-bundle.
//
//   POST /render  {"code": "graph TD; A-->B"}  ->  200 image/svg+xml
//                                               ->  422 {"error": "..."} when Mermaid refuses the diagram
//   GET  /health                                ->  200 ok
//
// One browser is started and reused; at most CONCURRENCY diagrams are drawn at a time and MAX_QUEUE wait, each
// within RENDER_TIMEOUT_MS. A diagram that overruns its deadline takes the browser down with it: the browser is closed,
// so nothing keeps drawing unseen, and the next request starts another. A body larger than MAX_BODY_BYTES is refused
// before it is read whole, an SVG larger than MAX_SVG_BYTES is not sent. Mermaid runs with its strict security level
// and labels drawn as SVG text, never as HTML in a foreignObject, so that the SVG sanitizer of the PHP side keeps them.
// The code of a diagram is never logged.

import { createServer } from 'node:http';
import { renderMermaid } from '@mermaid-js/mermaid-cli';
import puppeteer from 'puppeteer';
import { CHROMIUM_ARGS, createLimiter, isJson, mermaidConfig, readBody, reason, svgId, withTimeout } from './lib.mjs';

const PORT = Number(process.env.PORT ?? 3000);
const MAX_BODY_BYTES = Number(process.env.MAX_BODY_BYTES ?? 65536);
const MAX_CODE_LENGTH = Number(process.env.MAX_CODE_LENGTH ?? 20000);
const MAX_SVG_BYTES = Number(process.env.MAX_SVG_BYTES ?? 2097152);
const RENDER_TIMEOUT_MS = Number(process.env.RENDER_TIMEOUT_MS ?? 8000);
const CONCURRENCY = Math.max(1, Number(process.env.CONCURRENCY ?? 2));
const MAX_QUEUE = Math.max(0, Number(process.env.MAX_QUEUE ?? 16));

const MERMAID_CONFIG = mermaidConfig(MAX_CODE_LENGTH);
const limiter = createLimiter(CONCURRENCY, MAX_QUEUE);
let browser = null;

/** The browser, started on first use and again after it was closed or crashed. */
function currentBrowser() {
    browser ??= puppeteer
        .launch({ headless: true, executablePath: process.env.PUPPETEER_EXECUTABLE_PATH, args: CHROMIUM_ARGS })
        .then((started) => {
            started.on('disconnected', () => {
                browser = null;
            });

            return started;
        })
        .catch((failure) => {
            browser = null;

            throw failure;
        });

    return browser;
}

/** Closes the browser, and every page it still draws. */
async function restartBrowser() {
    const closing = browser;
    browser = null;
    const started = await closing?.catch(() => null);
    await started?.close().catch(() => {});
}

function send(response, status, type, body) {
    response.writeHead(status, {
        'Content-Type': type,
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
    });
    response.end(body);
}

function error(response, status, message) {
    send(response, status, 'application/json', JSON.stringify({ error: message }));
}

/** The SVG of a diagram, within its deadline; past it, the browser is closed with the page still drawing. */
function draw(code) {
    return withTimeout(
        currentBrowser().then((started) => renderMermaid(started, code, 'svg', { mermaidConfig: MERMAID_CONFIG, backgroundColor: 'white', svgId: svgId(code) })),
        RENDER_TIMEOUT_MS,
    ).catch(async (failure) => {
        if ('timeout' === failure.message) {
            await restartBrowser();
        }

        throw failure;
    });
}

async function render(request, response) {
    if (!isJson(request.headers['content-type'])) {
        return error(response, 415, 'The body is JSON: {"code": text}.');
    }

    let code;

    try {
        code = JSON.parse(await readBody(request, MAX_BODY_BYTES))?.code;
    } catch (failure) {
        return error(response, 'too large' === failure.message ? 413 : 400, 'The body is {"code": text}, at most ' + MAX_BODY_BYTES + ' bytes.');
    }

    if ('string' !== typeof code || '' === code.trim() || code.length > MAX_CODE_LENGTH) {
        return error(response, 400, 'The code is a text of 1 to ' + MAX_CODE_LENGTH + ' characters.');
    }

    try {
        const { data } = await limiter.run(() => draw(code));

        if (data.length > MAX_SVG_BYTES) {
            return error(response, 422, 'The diagram is larger than ' + MAX_SVG_BYTES + ' bytes once drawn.');
        }

        return send(response, 200, 'image/svg+xml; charset=utf-8', Buffer.from(data));
    } catch (failure) {
        if ('busy' === failure.message) {
            return error(response, 503, 'Too many diagrams are waiting; try again later.');
        }

        if ('timeout' === failure.message) {
            return error(response, 503, 'The diagram took too long to draw.');
        }

        return error(response, 422, reason(failure));
    }
}

const server = createServer((request, response) => {
    if ('GET' === request.method && '/health' === request.url) {
        return send(response, 200, 'text/plain', 'ok');
    }

    if ('POST' === request.method && '/render' === request.url) {
        return void render(request, response);
    }

    error(response, 404, 'POST /render or GET /health.');
});

server.requestTimeout = RENDER_TIMEOUT_MS + 2000;
server.headersTimeout = 5000;
server.keepAliveTimeout = 5000;
server.maxHeadersCount = 50;
server.listen(PORT, () => console.log(`Mermaid service listening on ${PORT}`));

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, async () => {
        server.close();
        await restartBrowser();
        process.exit(0);
    });
}
