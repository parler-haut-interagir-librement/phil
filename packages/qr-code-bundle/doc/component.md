# The component

With Symfony UX Twig Component, `<twig:PhilQrCode:QrCode>` draws an accessible code:

```twig
<twig:PhilQrCode:QrCode content="https://example.com/tickets/42" />
<twig:PhilQrCode:QrCode :content="{type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}" alt="Join the café's network" size="{{ 200 }}" />
<twig:PhilQrCode:QrCode content="{{ code }}" :caption="false" logo="brand" class="ticket__code" />
```

```html
<figure class="phil-qr-code">
    <svg aria-hidden="true" …>…</svg>
    <figcaption class="phil-qr-code__caption"><a href="https://example.com/tickets/42" rel="noopener">https://example.com/tickets/42</a></figcaption>
</figure>
```

| Prop | Default | |
|---|---|---|
| `content` | `''` | A text, or the array of a typed content (see [Twig](twig.md#typed-contents)) |
| `alt` | `''` | The name of the image for screen readers |
| `caption` | `true` | Whether what the code holds is written beside it |
| `size` | the configuration's | The width and height of the image in pixels |
| `level` | the configuration's | `L`, `M`, `Q` or `H` |
| `logo` | `''` | The name of a configured logo |

Other attributes go on the `<figure>`, beside its class `phil-qr-code`.

## Accessibility

A QR code says nothing to a reader who cannot scan it. The component writes what the code holds — the content's
label — in the caption, as a link when it is a web address, so the address can be read, followed or copied. Then:

- without `alt`, the caption names the figure and the image is hidden from screen readers, so its content is read
  once;
- with `alt`, the image is named by it — "Join the café's network" — and the caption follows;
- with neither caption nor `alt`, the image is named by the content's label.

The label never holds a Wi-Fi password: write it beside the code when readers need it.

The component only gives structure and classes; the look is the application's, or its design system's.
