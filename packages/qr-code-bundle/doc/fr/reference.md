<!-- phil-dev source-sha256=6d9618881c097b4a2522b6f0a69dade49801ae072adccf8c2b1a2bab05e3bd30 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `PhilQrCodeBundle` | Le bundle et sa configuration `phil_qr_code`. |
| `Application\QrCodes` | Des codes encodés et dessinés avec les valeurs par défaut : SVG, PNG, URI `data:`, libellé. |
| `Application\QrCodeDefaults` | Les valeurs par défaut de la configuration. |
| `Application\Logos` | Les logos configurés, par nom. |
| `Application\ContentFactory` | Des contenus typés à partir de tableaux. |
| `Application\Fields` | Les valeurs d'un tel tableau lues selon leur type. |
| `Infrastructure\Twig\QrCodeExtension` | `qr_code()`, `qr_code_label()` et `qr_code_url()`. |
| `Infrastructure\Twig\Components\QrCodeComponent` | `<twig:PhilQrCode:QrCode>`. |
| `Infrastructure\Http\QrCodeController` | La route qui sert les codes à des adresses signées. |
| `Infrastructure\Http\QrCodeUrls` | Les adresses signées de la route. |
| `Infrastructure\Ujo\QrCodeBlockRenderer` | Les blocs `qrCode` des documents UJO, pour phil/svg-blocks-bundle. |
| `Infrastructure\PagedMedia\PdfQrCodes` | Des codes dessinés dans les documents PDF de phil/paged-media. |
| `Infrastructure\DependencyInjection\QrCodeServices` | Les services de la configuration. |
| `Infrastructure\DependencyInjection\SvgBlocksPass` | Le moteur `qrCode` de phil/svg-blocks-bundle remplacé. |
