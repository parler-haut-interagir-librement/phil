/*
 * The detectors of phil/bot-detection-bundle, written from scratch: each asks one question — is this browser driven
 * by automation? — of what the page can read, and answers `true`, `false`, or `undefined` when it cannot tell. Each
 * signal is read here and dropped: only the verdict, `human` or `robot`, leaves the browser. A browser that blanks or
 * randomizes an attribute to resist fingerprinting answers `undefined`, never `true`: privacy is not automation.
 */

/** A browser under WebDriver control says so. */
export function webdriver({ navigator }) {
    return typeof navigator?.webdriver === 'boolean' ? navigator.webdriver : undefined;
}

const automationGlobals = [
    '_phantom',
    'callPhantom',
    '__nightmare',
    '_selenium',
    'callSelenium',
    '_Selenium_IDE_Recorder',
    '__webdriver_evaluate',
    '__selenium_evaluate',
    '__webdriver_script_function',
    '__driver_evaluate',
    '__selenium_unwrapped',
    '__fxdriver_unwrapped',
    'domAutomation',
    'domAutomationController',
];

/** Properties the automation engines leave on the window, or the document. */
export function automationTraces({ window, document }) {
    if (!window) {
        return undefined;
    }

    if (automationGlobals.some((name) => name in window)) {
        return true;
    }

    return Object.keys(document ?? {}).some((name) => /^\$?cdc_/.test(name));
}

/** A page running in a Node.js runtime, where `process` is a global. */
export function nodeRuntime({ window }) {
    return typeof window?.process?.versions?.node === 'string';
}

/** Headless Chrome names itself in its user agent and in its brands. */
export function headlessChrome({ navigator }) {
    if (typeof navigator?.userAgent !== 'string') {
        return undefined;
    }

    const brands = navigator.userAgentData?.brands ?? [];

    return /HeadlessChrome/.test(navigator.userAgent) || brands.some((brand) => /Headless/.test(brand?.brand ?? ''));
}

/** A real browser prefers at least one language; old headless engines prefer none. */
export function noLanguages({ navigator }) {
    return Array.isArray(navigator?.languages) ? navigator.languages.length === 0 : undefined;
}

/** A window that has no size is one nobody looks at. */
export function zeroSizeWindow({ window }) {
    if (typeof window?.outerWidth !== 'number' || typeof window?.outerHeight !== 'number') {
        return undefined;
    }

    return window.outerWidth === 0 && window.outerHeight === 0;
}

const families = [
    ['Windows', /^Win/],
    ['macOS', /^Mac/],
    ['Linux', /^Linux/],
];

/** A platform the user agent hints contradict: one claimed, another run. */
export function platformMismatch({ navigator }) {
    const hinted = navigator?.userAgentData?.platform;
    const platform = navigator?.platform;

    if (typeof hinted !== 'string' || typeof platform !== 'string') {
        return undefined;
    }

    const family = families.find(([name]) => name === hinted);
    const actual = families.find(([, pattern]) => pattern.test(platform));

    return family === undefined || actual === undefined ? undefined : family !== actual;
}

/** Frames only automation frameworks put on a stack. */
export function automationStack({ stack }) {
    return typeof stack === 'string' ? /puppeteer|phantomjs|__selenium|playwright|webdriver/i.test(stack) : undefined;
}

/** Rendering without a graphics card, as on the servers headless browsers run on. */
export function softwareRenderer({ renderer }) {
    return typeof renderer === 'string' && renderer !== ''
        ? /SwiftShader|llvmpipe|softpipe|Software Rasterizer/i.test(renderer)
        : undefined;
}

export const detectors = {
    webdriver,
    automationTraces,
    nodeRuntime,
    headlessChrome,
    noLanguages,
    zeroSizeWindow,
    platformMismatch,
    automationStack,
    softwareRenderer,
};

/** The verdict of every detector: `robot` when one sees automation, `human` otherwise. */
export function verdict(environment) {
    return Object.values(detectors).some((detector) => detector(environment) === true) ? 'robot' : 'human';
}

/** The WebGL renderer, when the browser tells it; `undefined` when it does not. */
export function rendererOf(document) {
    try {
        const context = document?.createElement?.('canvas')?.getContext?.('webgl');
        const info = context?.getExtension?.('WEBGL_debug_renderer_info');

        return info ? context.getParameter(info.UNMASKED_RENDERER_WEBGL) : undefined;
    } catch {
        return undefined;
    }
}

/** What the detectors read of the page. */
export function environment(scope = globalThis) {
    return {
        window: scope.window,
        navigator: scope.navigator,
        document: scope.document,
        stack: new Error().stack,
        renderer: rendererOf(scope.document),
    };
}
