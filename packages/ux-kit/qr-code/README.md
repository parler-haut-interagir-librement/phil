# QrCode

A QR code in a figure, with what it holds written beside it, a link for a web address, drawn on the server by `phil/qr-code-bundle`.

```twig {"preview":true}
<twig:QrCode content="https://example.com/tickets/42" />
```

## Installation

::: installation

## Usage

```twig
<twig:QrCode content="https://example.com/tickets/42" />
<twig:QrCode :content="{type: 'wifi', ssid: 'Cafe', password: 'tea-and-dice'}" alt="Join the network of the cafe" />
<twig:QrCode content="{{ code }}" :caption="false" size="{{ 160 }}" />
```

## Accessibility

The caption says what the code holds, for readers who cannot scan it; without `alt` the image is hidden behind it, so a screen reader reads it once.
