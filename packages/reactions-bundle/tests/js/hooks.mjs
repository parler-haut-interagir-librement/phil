// Resolves the peer dependencies to the tests' stubs, the bundle installing no npm package of its own.
const stubs = { '@hotwired/stimulus': './stimulus.stub.mjs', '@symfony/ux-live-component': './live.stub.mjs' };

export function resolve(specifier, context, next) {
    return stubs[specifier]
        ? { url: new URL(stubs[specifier], import.meta.url).href, shortCircuit: true }
        : next(specifier, context);
}
