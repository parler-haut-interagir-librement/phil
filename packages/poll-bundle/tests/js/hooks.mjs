// Resolves the peer dependency to the tests' stub, the bundle installing no npm package of its own.
const stubs = { '@hotwired/stimulus': './stimulus.stub.mjs' };

export async function resolve(specifier, context, next) {
    return stubs[specifier]
        ? { url: new URL(stubs[specifier], import.meta.url).href, shortCircuit: true }
        : next(specifier, context);
}
