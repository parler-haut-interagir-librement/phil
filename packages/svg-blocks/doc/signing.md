# Signing

A document is JSON that a client sends; the drawing it keeps is markup written in readers' pages without being
sanitized again. Nothing a client sends may become that markup: `Signer` makes the difference.

```php
new Signer(#[SensitiveParameter] string $secret)    // at least 32 bytes
```

`sign($type, $source, $markup)` returns `v1.` and the HMAC-SHA256 of the type, the source and the markup, separated
by NUL bytes, under the secret, in URL-safe Base64 without padding. `trusts(Block $block, $markup, $key)` compares, in
constant time, the key with the one of the block's type and current source: a forged key, a markup changed in the
database, or a source edited since the drawing all fail. The prefix names the construction, so that a later one can
read the keys already kept.

## Rendering

`phil/ujo`'s `HtmlRenderer::withStoredMarkup()` takes the check as a closure:

```php
$renderer = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...));
```

A block whose drawing is trusted is written as a `<div>` around its MathML, or a `<figure>` around its SVG with its
caption; any other falls back to the block's own rendering. EPUB and print engines that render through the same
`HtmlRenderer` get the drawing too.

## The secret

The secret is the application's — in Symfony, the kernel secret by default. Changing it makes every drawing
untrusted: readers see the blocks' own rendering until each document is saved again, or until a pass over the
documents draws them anew.
