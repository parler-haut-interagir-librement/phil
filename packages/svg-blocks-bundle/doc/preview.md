# Preview

## The endpoint

`POST /_svg-blocks/preview` (`phil_svg_blocks_preview`) draws one block without keeping it:

```json
{"type": "math", "data": {"latex": "x^2"}}
```

| Answer | When |
|---|---|
| `200 {"markup": text}` | the drawing |
| `422 {"error": text}` | the source cannot be drawn: the reason, for the author |
| `404` | the type is not drawn |
| `400` | the body is not `{"type": text, "data": object}` |
| `413` | the body is larger than `preview.max_bytes` |
| `403` | the request is not from a page of this site |

Answers are `no-store`. A request is trusted when it carries the header `X-Phil-Svg-Blocks: 1`, the browser does not
say it comes from another site, and it passes Symfony's stateless CSRF check of the token `phil_svg_blocks` — a
token in a header and a cookie, or `Sec-Fetch-Site`, `Origin` or `Referer` naming the site. The route stays behind
the application's firewall: the check only keeps another site from calling it in a writer's name. A diagram's preview
calls the Mermaid service at each pause of the author; the service draws a few at a time and refuses the rest.

## The controller

```twig
<div data-controller="phil--svg-blocks-bundle--preview"
     data-phil--svg-blocks-bundle--preview-url-value="{{ path('phil_svg_blocks_preview') }}"
     data-phil--svg-blocks-bundle--preview-type-value="math"
     data-phil--svg-blocks-bundle--preview-csrf-cookie-value="{{ csrf_token('phil_svg_blocks') }}">
    <textarea data-field="latex"
              data-phil--svg-blocks-bundle--preview-target="field"
              data-action="phil--svg-blocks-bundle--preview#update"></textarea>
    <div data-phil--svg-blocks-bundle--preview-target="output"></div>
    <p data-phil--svg-blocks-bundle--preview-target="error" role="alert" hidden></p>
</div>
```

| Value | Role |
|---|---|
| `url` | the endpoint |
| `type` | `math`, `mermaid`, `chart` or `qrCode` |
| `csrfCookie` | the cookie name `csrf_token('phil_svg_blocks')` gives |
| `delay` | milliseconds of pause before asking, 400 by default |

Each `field` target gives a property of the block's data under its `data-field` or its `name`; a field with
`data-kind="json"` holds an object whose properties are merged in — a chart's whole configuration. After each pause
the controller asks for the drawing, drops answers to older requests, writes the markup into `output` and the reason
into `error`, sets `aria-busy` while it waits, and dispatches `phil--svg-blocks-bundle--preview:rendered` with the
result. The markup is the endpoint's, drawn and sanitized on the server.

Its pure modules, `lib/preview.js` and `lib/csrf.js`, are tested under Node.
