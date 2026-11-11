# The route

With `route.enabled` and `config/routes.php` imported, codes are served as images:

```twig
<img src="{{ qr_code_url('https://example.com/tickets/42') }}" alt="Ticket 42">
<img src="{{ qr_code_url({type: 'phone', number: '+15551234567'}, 'png', 'brand', true) }}" alt="Call us">
```

`qr_code_url(content, format = 'svg', logo = '', absolute = false)` writes `/qr-code.svg` or `/qr-code.png` with the
payload and the logo in the query, signed with the application's secret (`_qr_signature`). The route draws only what
the application signed: any other address, or a code that cannot be drawn, is not found — the route cannot be used
to draw codes for someone else.

Each code is drawn with the defaults of the configuration, and its answer:

- is `public`, `immutable` and kept for `cache_max_age` seconds by browsers and shared caches, since an address
  always gives the same image;
- has an `ETag` of its format, logo and payload, and answers `304 Not Modified` to it;
- is sent with `X-Content-Type-Options: nosniff` and, for SVG, `Content-Security-Policy: default-src 'none'; img-src
  data:; style-src 'unsafe-inline'`.

The payload sits in the address: keep secrets out of codes served this way — a Wi-Fi password belongs in an inline
`qr_code()`, not in a URL that logs and caches keep.
