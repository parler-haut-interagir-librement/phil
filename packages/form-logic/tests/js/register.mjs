// `node --import ./tests/js/register.mjs --test tests/js`: resolves `@hotwired/stimulus` to the tests' stub, the
// package installing no npm package of its own.
import { registerHooks } from 'node:module';

registerHooks({
    resolve(specifier, context, next) {
        return '@hotwired/stimulus' === specifier
            ? { url: new URL('./stimulus.stub.mjs', import.meta.url).href, shortCircuit: true }
            : next(specifier, context);
    },
});
