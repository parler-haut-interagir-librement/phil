# User content

A file someone uploaded — an avatar, an attachment, a document — is served from the site's own origin, so whatever the
browser makes of it runs with the site's cookies. An HTML page renamed `.png`, an SVG with a script, a file served
without a type and sniffed as HTML: each becomes a page of the site. The policy of user content serves such a file with
headers that keep it inert, and is applied by route, by controller or by response.

## The headers

| Header | Value | Why |
|---|---|---|
| `Content-Security-Policy` | `default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox` | Opened directly, the file loads nothing, runs no script, posts no form and is framed nowhere; `sandbox` gives it an opaque origin, away from the site's cookies and storage. |
| `Content-Security-Policy-Report-Only` | removed | The site's report-only policy is about its pages, not its files. |
| `X-Content-Type-Options` | `nosniff` | The browser keeps to the declared type. |
| `Content-Type` | kept; `application/octet-stream` when the response has none | Symfony would otherwise send `text/html`. |
| `Content-Disposition` | `inline` for a type of the inline list, `attachment` otherwise | Only a type that cannot run is shown in the browser. |
| `Cross-Origin-Resource-Policy` | `same-origin` | Another site cannot embed the file. |
| `Referrer-Policy` | `no-referrer` | A link in a document leaks no address of the site. |
| `X-Permitted-Cross-Domain-Policies` | `none` | No plug-in reads a policy file from it. |
| `X-Frame-Options` | `DENY`, while `frame-ancestors` is `'none'` | For the browsers that ignore `frame-ancestors`. |

These headers replace those the response carries. The listeners of the policy and of the other headers then keep them,
since they never overwrite a header a response already carries; a route default or an attribute of
[overrides](overrides.md) that names one of them still wins.

### The disposition

The inline types are, by default, the raster images — `image/avif`, `image/gif`, `image/jpeg`, `image/png`,
`image/webp` —, the audio and video of the browsers — `audio/mpeg`, `audio/ogg`, `audio/wav`, `audio/webm`,
`video/mp4`, `video/ogg`, `video/webm` — and `text/plain`. The type is read from `Content-Type` without its parameters,
in lowercase. Every other type is an attachment, and so is any response that already says `attachment`.

The types that can run as a page or a script are never inline, whatever the configuration says: `text/html`,
`application/xhtml+xml`, `image/svg+xml`, `text/xml`, `application/xml`, `text/xsl`, and the JavaScript types. The build
fails when the inline list names one of them. An SVG is shown with `<img>`, where it runs nothing, not by opening it.

The file name of the response's `Content-Disposition` is kept. A name given to `apply()` replaces it, cleaned of any path,
control character and quote, and written with `filename*` in UTF-8 beside an ASCII `filename`, as
`HeaderUtils::makeDisposition()` writes it.

## Applying it

### By route

```yaml
# config/routes.yaml
upload:
    path: /uploads/{id}
    controller: App\Controller\UploadController
    defaults:
        _user_content: true
```

The default is `true` or `false`; any other value throws `InvalidPolicy` when the response is written.

### By controller

```php
use Phil\BrowserSecurityBundle\Application\Attribute\UserContent;

final class UploadController
{
    #[UserContent]
    public function __invoke(Upload $upload): BinaryFileResponse
    {
        return new BinaryFileResponse($upload->path());
    }
}
```

The attribute goes on the class or on a method. Both read on the main request only, and the headers are written on
`kernel.response` at priority 8: after a redirect that replaced the response, before the other headers.

### By response

```php
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;

final readonly class AvatarController
{
    public function __construct(private UserContentPolicy $userContent)
    {
    }

    public function __invoke(Avatar $avatar): Response
    {
        $response = new BinaryFileResponse($avatar->path());
        $this->userContent->apply($response, $avatar->originalName());

        return $response;
    }
}
```

`apply()` works on any response, in a controller, a listener or a sub-request; `isInline()` says how a type is served.

## Configuration

```yaml
phil_browser_security:
    user_content:
        directives:                   # the policy of the files, as a named policy declares it
            default-src: ["'none'"]
            base-uri: ["'none'"]
            form-action: ["'none'"]
            frame-ancestors: ["'none'"]
            sandbox: true
        inline_types: [image/avif, image/gif, image/jpeg, image/png, image/webp, audio/mpeg, audio/ogg, audio/wav, audio/webm, video/mp4, video/ogg, video/webm, text/plain]
        cross_origin_resource_policy: same-origin # same-site, cross-origin
```

`directives` replaces the whole policy: a site that shows its PDF files inline lists `application/pdf` among the inline
types, and drops `sandbox`, which most PDF viewers refuse. Every directive and value is checked like those of a named
policy, and each inline type must be a lowercase `type/subtype` without parameters or wildcard.

## Where to serve them from

The policy makes a file inert in the browser; it does not give it another origin. Serving the uploads from a host of
their own, without the site's cookies, adds a second wall, and the policy still applies there.
