# Browser signals

The browser code is a Stimulus controller, `verdict` (`assets/src/verdict_controller.js`), and its detectors
(`assets/src/detectors.js`), written from scratch. It lets one word out of the browser: `human` or `robot`.

| Piece | State |
|---|---|
| The browser detectors | Implemented, tested with Node; no third-party code included |
| The wire format (`human` or `robot`) | Decided, and read by `Verdict::fromSubmitted()` on the server |
| The server side (verdict, friction, counter) | Implemented and tested |

## Placing the controller

With StimulusBundle, the controller is found from the bundle's `assets/package.json`. On the root element, it
leaves the verdict as `data-phil-bot-verdict`, where `phil/analytics-bundle`'s collection script reads it; on a hidden
field, it also writes the verdict into the field, for the form to submit:

```twig
<html data-controller="phil--bot-detection-bundle--verdict">
…
<input type="hidden" name="verdict" data-controller="phil--bot-detection-bundle--verdict">
```

## Signals, not a fingerprint

The browser code evaluates **independent automation signals**, each answering one question ("is this browser
driven by automation?"), and combines them into a single verdict **on the spot**. A fingerprint answers another
question ("which browser is this?") and needs a stable combination of many attributes; nothing here is designed
to answer it, and nothing that would answer it is computed, hashed or sent.

## The detectors

Each detector answers `true` (automation), `false`, or `undefined` when it cannot tell; one `true` makes the verdict
`robot`. The detectors written are:

| Detector | Reads | Automation when |
|---|---|---|
| `webdriver` | `navigator.webdriver` | it is `true` |
| `automationTraces` | the window's and the document's properties | a Selenium, PhantomJS, Nightmare or WebDriver global is present, or a ChromeDriver `cdc_` property |
| `nodeRuntime` | `window.process.versions.node` | the page runs in Node.js |
| `headlessChrome` | the user agent and the user agent hints' brands | either names `HeadlessChrome` |
| `noLanguages` | `navigator.languages` | it is an empty list |
| `zeroSizeWindow` | `window.outerWidth`, `window.outerHeight` | both are 0 |
| `platformMismatch` | `navigator.userAgentData.platform` against `navigator.platform` | Windows, macOS and Linux disagree |
| `automationStack` | the stack of an `Error` | a Puppeteer, PhantomJS, Selenium, Playwright or WebDriver frame |
| `softwareRenderer` | the WebGL renderer, when the browser tells it | SwiftShader, llvmpipe, softpipe or a software rasterizer |

The signals of the design not written yet — rewritten native functions, the window against a worker, permissions,
speech voices, WebRTC, hardware and screen consistency — are listed below as the full design.

| Signal | What it detects |
|---|---|
| `navigator.webdriver` | A browser under WebDriver control |
| Globals specific to automation engines (Selenium, PhantomJS, Nightmare) | Properties those engines inject into the page |
| Automation traces in stack traces | Frames that only automation frameworks produce |
| An exposed `process` object | A page running inside a Node.js-based runtime |
| Inconsistencies between the user agent and the platform | A user agent that claims what the platform contradicts (`platform` against `userAgentData`) |
| Inconsistencies of languages, plugins, MIME types and permissions | Values a real browser would not combine |
| Rewritten or proxied native functions | Native APIs patched to hide automation |
| Comparing the window with a worker | Values spoofed in the window but not in a worker |
| Headless Chrome hints | Traits specific to headless mode |
| A software WebGL renderer, WebGL vendor and renderer | Rendering without a GPU, typical of headless servers |
| A zero-size window | A window no one looks at |
| Secondary consistency signals: permissions, speech-synthesis voices, WebRTC, hardware (`hardwareConcurrency`), screen | Inputs to consistency checks only |

**Each signal is read locally and dropped.** Only the verdict derived from them leaves the browser, with the
request it decides.

## What is deliberately never collected or sent

- **No visitor identifier and no fingerprint**: every hash and visitor-id computation found in the design
  references is left out.
- **No raw signal and no detector detail**: the server receives `human` or `robot`, nothing else. A longer payload
  is read as `Unknown` ([verdict.md](verdict.md)).
- **No call to a third party**: none of the "monitoring" or sampling calls the references make on their own (to
  a vendor endpoint, or loading a remote script) is reproduced. The script is served by the application, from its
  own origin.
- **No storage**: no cookie, no local storage, no persistent state in the browser.

## A browser that resists fingerprinting is not a robot

Privacy-focused browsers randomize or blank some of the very attributes listed above. **A detector must not
treat randomized or blanked signals as automation.** This is a requirement on each detector, not on the server
policy: by the time a verdict reaches the server, it is already robot or human.

## Design references

The detectors are **written from scratch**. The projects below are read detector by detector to understand what a
signal measures, and only what serves a robot-or-human verdict inspires a detector. **No line of their code is
copied.** A source that is not open source is not read at all: rewriting code under a licence that forbids
redistribution would make a derivative work. Licences are those of the files read on 2026-10-30.

| Reference | Licence read | Version read | What inspires a detector | Left out |
|---|---|---|---|---|
| BotD (`fingerprintjs/BotD`) | MIT | v2.0.0 | **The main reference**: a `{ bot, botKind }` verdict without an identifier, and independent detectors with their tests (webdriver, automation globals, stack traces, language/plugin/MIME/permission inconsistencies, exposed `process`, software WebGL renderer, zero-size window) | `monitor()`, which calls a vendor endpoint; telling good bots from bad, which is done server-side with a crawler list |
| CreepJS (`abrahamjuliot/creepjs`) | MIT; its name is a trademark, so it is never used as the name or brand of this bundle (a factual mention of origin is allowed) | No release; last commit read 2026-06-11 | What BotD covers poorly: rewritten or proxied native functions, window versus worker comparison, headless Chrome hints | The fingerprinting part and the `prediction` module |
| ThumbmarkJS (`thumbmarkjs/thumbmarkjs`) | MIT | v1.12.0 | Secondary consistency signals: permissions, speech-synthesis voices, WebRTC, hardware | Hashing, sampled logging to a vendor endpoint, and the remote script, whose licence is not read |
| FingerprintJS (`fingerprintjs/fingerprintjs`) | MIT up to v3.4.2 and from v5.0.0; Business Source License 1.1 from v4.0.0 to v4.6.2 | v5.3.0 | Robust collection techniques as consistency inputs: `platform` against `userAgentData`, plugins, WebGL vendor and renderer, `hardwareConcurrency`, screen | **All v4 code**, which is not open source and is not read; the `visitorId` and all hashing; the monitoring call |

**Never read**: Benny the Doorman (npm package `doorman-benny`), under a "Source-Available Non-Redistribution
License" that forbids redistribution, even modified.

The detectors were written on 2026-11-04 from what each signal measures, after reading these references.

## Server-side counterpart

Bots that declare themselves (crawlers announcing their name in the user agent) are not a browser matter: they are
recognized on the server from the user agent, with a crawler list such as `jaybizzle/crawler-detect`, outside this
bundle.
