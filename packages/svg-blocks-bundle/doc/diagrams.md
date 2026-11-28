# Diagrams

## The service

`docker/mermaid` holds the Mermaid service: Node.js 22, Chromium from Debian, `@mermaid-js/mermaid-cli` and a small
HTTP server, `server.mjs`, with the parts that need no browser in `lib.mjs`, run as the unprivileged user 1000.

| Request | Answer |
|---|---|
| `POST /render`, `application/json`, `{"code": text}` | `200 image/svg+xml`, or `422 {"error": text}` when Mermaid refuses the diagram |
| `GET /health` | `200 ok` |

One browser is started on first use and reused. Mermaid runs at its `strict` security level, with labels drawn as SVG
text, never as HTML in a `foreignObject`; each SVG gets an identifier made from its code, so two diagrams of a page
never share the identifiers of their styles and markers. Its environment:

| Variable | Default |
|---|---|
| `PORT` | 3000 |
| `CONCURRENCY` | 2 diagrams drawn at a time |
| `MAX_QUEUE` | 16 diagrams waiting, then `503` |
| `RENDER_TIMEOUT_MS` | 8000, then `503` and the browser closed |
| `MAX_BODY_BYTES` | 65536, then `413` |
| `MAX_CODE_LENGTH` | 20000 characters, then `400` |
| `MAX_SVG_BYTES` | 2097152 bytes of SVG, then `422` |

A body that is not `application/json` gets `415`.

What the service answers is never kept as it came: `phil/svg-blocks` rewrites it with `phil/svg-sanitizer` first,
whose policy for diagrams keeps their `<style>`, its rules rewritten by `phil/css-sanitizer`.

## Running the service

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
docker compose -f vendor/phil/svg-blocks-bundle/docker/mermaid/compose.yaml up -d
```

`compose.yaml` runs it as user 1000, on a read-only file system with a `/tmp` of 128 MB in memory, without
capabilities nor new privileges, under Docker's default seccomp profile, within 768 MB of memory and no swap, one CPU,
256 processes and 1024 open files, on an internal network. Add the application's service to the `diagrams` network
and set `mermaid.url` to `http://mermaid:3000`; never publish the port.

Building the image downloads, and only then:

| What | From | Size, approximately |
|---|---|---|
| The base image `node:22.11.0-bookworm-slim` | Docker's public registry, `registry-1.docker.io/library/node` | 75 MB compressed |
| `chromium`, `chromium-common` and their libraries | Debian bookworm, `deb.debian.org` | 150 to 250 MB of packages |
| `fonts-dejavu-core`, `fonts-noto-core` | Debian bookworm, `deb.debian.org` | 40 MB |
| `@mermaid-js/mermaid-cli` 11.4.2, `puppeteer` 23.11.1 and their dependencies, Mermaid among them | npm, `registry.npmjs.org` | 60 MB unpacked |

Puppeteer downloads no browser of its own: it drives Debian's. The sizes depend on the day's packages and are measured
at the first build.

## Security review

Chromium draws the diagrams, and Chromium's own sandbox needs kernel features — user namespaces, or a setuid helper —
that a container does not give an unprivileged process. The service therefore runs it with `--no-sandbox`. This
review weighs what that leaves, and what replaces the sandbox.

### What an attacker controls

The code of a diagram: any author of a document, up to 20 000 characters. The service is reachable only from the
application, on the internal network; it has no authentication of its own, so the network is the boundary of who may
call it.

### The risks

1. **A diagram that runs script.** Mermaid at `strict` encodes the text of labels and refuses `click` callbacks;
   labels are SVG text, never HTML. A `%%{init: …}%%` directive may not change the keys listed in `secure`: the
   security level, HTML labels, the flowchart and class settings that hold them, a theme's CSS, the configuration of
   DOMPurify, the limits. A script that ran anyway would run in a page of a browser that reaches nothing (4, 5).
2. **A diagram that exhausts the service.** The body is refused past 64 KB before it is read whole, the code past
   20 000 characters, Mermaid's own `maxTextSize` and `maxEdges` stop larger graphs, two diagrams are drawn at a time
   and sixteen wait, the rest are refused. A diagram past its deadline closes the browser, so that no page keeps
   drawing after its answer; the next request starts a new one. The container's memory, CPU, processes and files are
   capped, so a runaway browser cannot starve the host.
3. **An SVG that carries script to the application's pages.** The SVG is capped at 2 MB, and the PHP side rewrites it
   with `phil/svg-sanitizer`: no script, no event handler, no `foreignObject`, no external reference, styles through
   `phil/css-sanitizer`. What the service answers is untrusted.
4. **A compromised renderer that reaches the network.** Inside the browser, every request goes to a proxy on a closed
   port and no name is resolved; extensions, background networking, component updates and synchronisation are off.
   Outside it, the container sits on an internal network: no route leaves it, whatever runs inside.
5. **A compromised renderer that escapes into the container.** Without Chromium's sandbox, a renderer exploit runs as
   the browser process. It runs as user 1000, who owns nothing in the image; the root file system is read-only and
   only `/tmp`, in memory, `nosuid` and `nodev`, is writable; every capability is dropped and no new privilege may be
   gained; Docker's default seccomp profile filters system calls; V8 runs without its JIT, which removes the class of
   exploits that write executable code. What remains is the kernel's surface under that profile.
6. **The supply chain.** The base image, Debian's Chromium, Mermaid and Puppeteer come from their registries at build
   time; npm runs no install script. Pin the base image by its digest and build from a lock file where the image is
   built for production; rebuild for each security update of Chromium.
7. **Information in errors.** Mermaid's refusal is sent without the stack frames that name files of the service, at
   most 500 characters. The code of a diagram is never logged.

### Conclusions

The sandbox's role is taken by the container: an unprivileged user, a read-only file system, no capabilities, the
default seccomp profile, an internal network, and Chromium's own ways out closed. Under those conditions the
`--no-sandbox` flag is acceptable for a service that draws diagrams written by the application's authors. The
conditions are not optional: never run the image with `--privileged`, with `seccomp=unconfined`, with a published
port, or on a network with a route out. An application that draws diagrams from anonymous visitors runs the service
on a host of its own.

## The background pass

With `mermaid.async: true`, a save leaves diagrams pending. After the document is stored, the application sends:

```php
$scheduler->schedule('article:42');   // MarkupScheduler
```

The message, `RenderDeferredMarkup`, carries the subject alone, 1 to 255 bytes; route it to a transport of its own to
keep a slow service off the requests:

```yaml
framework:
    messenger:
        routing:
            Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup: async
```

Its handler reads the document through the application's `MarkupDocuments`, draws every pending block, and saves it
when a drawing was added or dropped. A document gone since is left alone; a diagram the service refuses keeps no
drawing and shows its code.

```php
final class ArticleDocuments implements MarkupDocuments
{
    public function find(string $subject): ?Document { /* … */ }
    public function save(string $subject, Document $document): void { /* … */ }
}
```

When the text changed between the save and the pass, the drawing of the older text is dropped at the next save; an
application that wants none at all compares the document's version in `save()`.
