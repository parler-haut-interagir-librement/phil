# Mermaid service

Mermaid lays out its diagrams in a browser engine; PHP cannot. The library defines a port and an adapter, as a
Gotenberg client does:

```php
interface MermaidService
{
    /** @throws RenderingFailed */
    public function toSvg(string $code): string;
}
```

`HttpMermaidService` calls the service that `phil/svg-blocks-bundle` ships as a container image:

```php
new HttpMermaidService(HttpClientInterface $client, string $endpoint, float $timeout = 10.0, int $maxBytes = 1_048_576)
```

| Request | Answer |
|---|---|
| `POST {endpoint}/render`, `{"code": text}` | `200 image/svg+xml`: the diagram |
| | `422 {"error": text}`: Mermaid refuses the diagram; the reason reaches the author |
| | any other status or type: a failure |

The call has a deadline, follows no redirect and reads at most `$maxBytes`; a transport error, a timeout or a larger
answer is a `RenderingFailed` with its reason. The address must be `http://` or `https://`, without query.

## The answer is sanitized

`MermaidRenderer` passes what the service returns through `phil/svg-sanitizer` before it is kept, under the default
policy with `<style>` elements kept, `new Policy(styleElements: true)`: scripts, event handlers, `foreignObject` and
external references are removed, `style` attributes go through `phil/css-sanitizer`, and the diagram is made safe to
write inline. Its ids, with the references to them, are prefixed with `svg-`. Its `<style>` element, where Mermaid
writes the theme's colours and fonts, and where a diagram's code can carry CSS of its own (`themeCSS`), is kept with
every rule placed under the diagram's root, whose id is written from the diagram's content: Mermaid's rules, written
under the diagram's id, keep their meaning, two diagrams on one page never share a rule though Mermaid names their
roots alike, and no rule reaches the rest of the page — see `phil/svg-sanitizer`'s policy. The service draws labels as SVG text, never
as HTML in a `foreignObject`, so they survive. An answer the sanitizer refuses whole is a failure. A renderer given
another `SvgSanitizer` uses its policy instead.
