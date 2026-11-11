// `node --import ./tests/js/register.mjs --test tests/js`: resolves `@hotwired/stimulus` to the tests' stub, the
// bundle installing no npm package of its own.
import { registerHooks } from 'node:module';

const stub = new URL('./stimulus.stub.mjs', import.meta.url).href;

registerHooks({
    resolve: (specifier, context, next) => ('@hotwired/stimulus' === specifier ? { url: stub, shortCircuit: true } : next(specifier, context)),
});
