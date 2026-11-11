# Installation

```sh
composer require phil/qr-code-bundle
```

The bundle needs PHP 8.5 or later with `ext-sodium`, Symfony 8.2 or later, Twig 3.21 or later and phil/qr-code.
Enable it, when Symfony Flex does not:

```php
// config/bundles.php
return [
    // …
    Phil\QrCodeBundle\PhilQrCodeBundle::class => ['all' => true],
];
```

The rest is optional: each package below is suggested, not required, and what it brings is registered only when it
is installed. Without it, the bundle registers nothing in its place and works as before.

| Package | Brings |
|---|---|
| `symfony/ux-twig-component` | `<twig:PhilQrCode:QrCode>` |
| `phil/svg-blocks` | `QrCodeBlockRenderer`, the renderer of the `qrCode` blocks of UJO documents |
| `phil/svg-blocks-bundle` | The `qrCode` blocks of UJO documents drawn with the defaults of this bundle |
| `phil/paged-media` | `PdfQrCodes`, QR codes drawn in PDF documents |

The route that serves codes as images is off until `route.enabled` is true and the routes are imported:

```yaml
# config/routes/phil_qr_code.yaml
phil_qr_code:
    resource: '@PhilQrCodeBundle/config/routes.php'
```
