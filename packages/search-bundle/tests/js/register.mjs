// `node --import ./tests/js/register.mjs --test tests/js`: resolves the peer dependency to the tests' stub, the bundle
// installing no npm package of its own.
import { registerHooks } from 'node:module';

const stubs = { '@hotwired/stimulus': './stimulus.stub.mjs' };

registerHooks({
    resolve(specifier, context, next) {
        return stubs[specifier]
            ? { url: new URL(stubs[specifier], import.meta.url).href, shortCircuit: true }
            : next(specifier, context);
    },
});
