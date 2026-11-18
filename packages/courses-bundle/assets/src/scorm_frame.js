// The frame of the SCORM run time, on the content origin: it receives the state of the session from the activity page
// of the application — and from it alone —, puts the run-time API of the version on its window, then frames the SCO,
// on the same origin, which finds the API in its parent window. Each write, commit and end goes to the activity page,
// which sends them to the server.

import { ScormSession } from './lib/api.js';
import { CmiData } from './lib/cmi.js';

const parentOrigin = document.documentElement.dataset.parent;
let started = false;

function tell(message) {
    window.parent.postMessage({ phil: 'scorm', ...message }, parentOrigin);
}

function start(state) {
    if (started || 'string' !== typeof state.sco || !state.sco.startsWith('/') || state.sco.startsWith('//')) {
        return;
    }

    started = true;
    const data = new CmiData(state.model, state.values ?? {});
    const session = new ScormSession(data, {
        onSet: (element, value) => tell({ type: 'set', element, value }),
        onCommit: () => {
            tell({ type: 'commit' });

            return true;
        },
        onTerminate: () => {
            tell({ type: 'terminate' });

            return true;
        },
    });

    window[state.api] = session.api();

    const frame = document.createElement('iframe');
    frame.title = state.title ?? 'SCORM';
    frame.src = state.sco;
    frame.allow = 'fullscreen; autoplay';
    document.body.append(frame);
}

window.addEventListener('message', (event) => {
    if (event.source !== window.parent || event.origin !== parentOrigin || 'scorm' !== event.data?.phil) {
        return;
    }

    if ('init' === event.data.type) {
        start(event.data.state ?? {});
    }
});

if (parentOrigin && window.parent !== window) {
    tell({ type: 'ready' });
}
