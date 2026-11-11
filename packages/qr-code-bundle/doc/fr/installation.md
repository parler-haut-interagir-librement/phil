<!-- phil-dev source-sha256=b4eae58bce221ece924af0605f54dd8f942ef40c9b5a536c334218c25550fc40 -->
# Installation

```sh
composer require phil/qr-code-bundle
```

Le bundle exige PHP 8.5 ou plus récent avec `ext-sodium`, Symfony 8.2 ou plus récent, Twig 3.21 ou plus récent et
phil/qr-code. Activez-le, quand Symfony Flex ne le fait pas :

```php
// config/bundles.php
return [
    // …
    Phil\QrCodeBundle\PhilQrCodeBundle::class => ['all' => true],
];
```

Le reste est facultatif : chaque paquet ci-dessous est suggéré, pas exigé, et ce qu'il apporte n'est enregistré que
s'il est installé. Sans lui, le bundle n'enregistre rien à sa place et fonctionne comme avant.

| Paquet | Apporte |
|---|---|
| `symfony/ux-twig-component` | `<twig:PhilQrCode:QrCode>` |
| `phil/svg-blocks` | `QrCodeBlockRenderer`, le moteur des blocs `qrCode` des documents UJO |
| `phil/svg-blocks-bundle` | Les blocs `qrCode` des documents UJO dessinés avec les valeurs par défaut de ce bundle |
| `phil/paged-media` | `PdfQrCodes`, des codes QR dessinés dans les documents PDF |

La route qui sert les codes en images est désactivée tant que `route.enabled` n'est pas vrai et que les routes ne sont
pas importées :

```yaml
# config/routes/phil_qr_code.yaml
phil_qr_code:
    resource: '@PhilQrCodeBundle/config/routes.php'
```
