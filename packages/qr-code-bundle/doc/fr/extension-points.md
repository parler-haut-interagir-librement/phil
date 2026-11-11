<!-- phil-dev source-sha256=69cc7925854361cb41c25c3c44a7474dd4a98af0629f8bcc5b0e7a4b1c4ee7ff -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dessiner des codes en PHP avec les valeurs par défaut | Injectez `QrCodes` : `svg()`, `png()`, `dataUri()`, `encode()`, `label()` |
| Dessiner un contenu typé pour lequel le bundle n'a pas de tableau | Passez votre propre `Phil\QrCode\Contract\Content` à `QrCodes` ou à `qr_code()` |
| Changer l'apparence du composant | Stylez `.phil-qr-code` et `.phil-qr-code__caption`, ou remplacez `@PhilQrCode/components/QrCode.html.twig` |
| Servir les codes ailleurs | Appelez `QrCodeUrls::url()`, ou écrivez votre propre contrôleur sur `QrCodes` |
| Dessiner des codes dans un autre format de document | Lisez `QrCodes::encode()` et `drawing()`, comme le fait `PdfQrCodes` |
