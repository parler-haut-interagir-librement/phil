# Nonces

A nonce is a random value a response gives its own `<script>` and `<style>` elements, and lists in its policy: an
element injected into the page does not know it, and does not run. The bundle creates one per kind and per request,
128 random bits in base64, when a template or a service first asks for it, and adds it to the header of the response:

```http
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-iI5R8XMt31Pu+l6vYio9mg=='; style-src 'self' 'nonce-6WYimOcmbi+5703clRr3mw=='
```

A response whose templates asked for no nonce gets none in its header.

## In Twig

```twig
<script nonce="{{ csp_nonce('script') }}">document.documentElement.classList.add('js')</script>
<style nonce="{{ csp_nonce('style') }}">body { margin: 0 }</style>
```

`csp_nonce()` without argument gives the script nonce. Every element of a kind shares the nonce of the response.

A nonce goes after the sources of `script-src` or `style-src`, and also of `script-src-elem` or `style-src-elem` when
the policy declares them. When the policy declares neither the directive nor `default-src`, nothing restricts the
elements and no nonce is added; when the directive is `'none'`, the nonce is not added either: a nonce never opens what
the policy closed.

## AssetMapper's `importmap()`

`{{ importmap('app') }}` writes the script nonce on every tag it writes: the import map, the polyfill loader and the
entry points. The polyfill itself receives the nonce of the loader at run time. A template that passes its own
`nonce` attribute keeps it. The option `importmap: false` turns this off.

## Turbo

Turbo reads the `csp-nonce` meta tag to give the `<script>` elements it runs and the `<style>` of its progress bar
the nonce of the page:

```twig
<head>
    {{ csp_meta() }}
    {{ importmap('app') }}
</head>
```

`csp_meta()` writes `<meta name="csp-nonce" content="…">` with the script nonce, and the bundle allows that nonce in
`style-src` too, so that the progress bar is not blocked. When Turbo replaces the page, it takes the nonce of the new
page from its meta tag.

## Stimulus

The Stimulus controllers load through the import map, under its nonce, and connect through `data-controller`
attributes: no inline handler, nothing more to allow. Controllers that load scripts of their own need
`'strict-dynamic'` or the hosts of those scripts in `script-src`.

## `'strict-dynamic'`

```yaml
phil_browser_security:
    policies:
        default:
            strict_dynamic: true
```

adds `'strict-dynamic'` next to the nonces and hashes of `script-src`: a script allowed by its nonce may then load
others, and the browsers that understand the keyword ignore the hosts and `'self'` of the list. The keyword is added
only when the response has a script nonce or a hash, since alone it would block every script.

## Hashes

A known inline element is allowed by the SHA-256 of its content, from the configuration (see
[Policies](policies.md#hashes-of-inline-code)) or from code:

```php
$csp->allowInline('script', $code);
```

## The error page

The HTML error page of the ErrorHandler component, which FrameworkBundle shows when no template of the application
answers, carries the nonces of the response: the bundle hands `scriptNonce()` and `styleNonce()` of
`ContentSecurityPolicyContext` to `HtmlErrorRenderer::setNonce()` (Symfony 8.2), read when a page is rendered. Its
`<style>` element, and the `<script>` and `<style>` elements of the exception page and its dumps, run under a policy
without `'unsafe-inline'`. In debug, the ErrorListener of HttpKernel drops the policy of an error page, as Symfony
does without the bundle.

## A transitional policy without nonces

A site whose older templates still write inline scripts and handlers cannot take nonces everywhere at once: the
browsers that understand nonces and hashes ignore `'unsafe-inline'` as soon as a directive lists one. A policy declared
with `nonces: false` lists the hashes of the request but never its nonces, so that those pages keep a policy while the
rest of the site keeps its nonces:

```yaml
phil_browser_security:
    policies:
        default:
            directives: { default-src: ["'self'"], object-src: ["'none'"] }
            strict_dynamic: true
        transitional:
            extends: default
            directives:
                script-src: ["'self'", "'unsafe-inline'"]
                style-src: ["'self'", "'unsafe-inline'"]
            nonces: false
```

A route or a controller selects it like any other policy, see [Overrides](overrides.md):

```php
#[ContentSecurityPolicy(policy: 'transitional')]
public function archive(): Response
```

When each policy the response sends, enforced and report-only, is declared with `nonces: false`, no nonce is created:

- `csp_nonce()` gives `null`, and `csp_meta()` writes nothing;
- `importmap()` writes its tags without a `nonce` attribute, unless the template passes one;
- `ContentSecurityPolicyContext::nonce()`, `scriptNonce()`, `styleNonce()` and `sharedNonce()` return `null`, and the
  HTML error page writes its elements without a `nonce` attribute;
- the response, which lists no nonce, is not made private.

`html_attr()` of twig/html-extra leaves out an attribute whose value is `null`, so a template written for both kinds
of page writes the attribute only when there is a nonce:

```twig
<script {{ html_attr({nonce: csp_nonce('script')}) }}>document.documentElement.classList.add('js')</script>
```

Without twig/html-extra, `{% if csp_nonce('script') is not null %}` does the same; a plain `nonce="{{ csp_nonce() }}"`
writes an empty attribute, which allows nothing.

When the response also sends a policy that takes nonces — a report-only policy that tries the strict one on the same
pages, for instance — the templates get their nonces and only that policy lists them. The templates follow the
overrides known when they are rendered: an override added after that does not change what they wrote. The hashes of
`inline` and of `allowInline()` stay in a policy without nonces; a browser that understands them then ignores
`'unsafe-inline'` in that directive, so a transitional page lists none.

## Nonces and HTTP caches

A page with a nonce is a page for one response. A shared cache that stores it serves the same nonce to everyone, which
gives an attacker who reads the page a nonce that the cached policy accepts. The bundle therefore adds
`Cache-Control: private` to every response whose policy lists a nonce, written by the bundle or by the controller, and
removes `public`; the other directives stay. Pages that a shared cache keeps are served without nonce: with hashes, or
with external scripts only.

## The JSON-LD of a page

`structured_data()` of phil/structured-data-bundle prints a data block, which a policy does not execute: it asks for no
nonce, and a page whose only inline element is that block stays in shared caches. A template that wants one passes it,
`{{ structured_data(csp_nonce('script')) }}`, and the page then lists a nonce and is private, as any other.
