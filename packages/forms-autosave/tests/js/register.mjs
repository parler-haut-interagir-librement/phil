// `node --import ./tests/js/register.mjs --test tests/js`: `@hotwired/stimulus` resolved to the stub of the tests, as
// the bundle installs no npm package of its own.
import { registerHooks } from 'node:module';

const stub = new URL('./stimulus.stub.mjs', import.meta.url).href;

registerHooks({
    resolve: (specifier, context, next) => ('@hotwired/stimulus' === specifier ? { url: stub, shortCircuit: true } : next(specifier, context)),
});
