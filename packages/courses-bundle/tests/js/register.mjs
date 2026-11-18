// Resolves `@hotwired/stimulus` to the tests' stub, the bundle installing no npm package of its own:
// `node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'`.
import { registerHooks } from 'node:module';

const stub = new URL('./stimulus.stub.mjs', import.meta.url).href;

registerHooks({
    resolve(specifier, context, next) {
        return '@hotwired/stimulus' === specifier ? { url: stub, shortCircuit: true } : next(specifier, context);
    },
});
