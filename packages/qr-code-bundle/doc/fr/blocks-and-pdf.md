<!-- phil-dev source-sha256=f7ac1a2b99f6bd354cfedac7c8b34c859dfa3c0dd913855b211374cdef13824d -->
# Blocs UJO et documents PDF

Les deux intégrations sont facultatives. phil/svg-blocks, phil/svg-blocks-bundle et phil/paged-media sont suggérés
par le bundle, pas exigés : `QrCodeBlockRenderer` n'est enregistré que si phil/svg-blocks est installé, le moteur
`qrCode` de phil/svg-blocks-bundle n'est remplacé que si ce bundle est activé, et `PdfQrCodes` n'est enregistré que si
phil/paged-media est installé.

## Les blocs `qrCode`

phil/svg-blocks-bundle dessine les blocs `qrCode` des documents UJO (Universal JSON Output) quand ils sont
enregistrés, garde le SVG avec le bloc et le signe. Avec ce bundle installé, son moteur des blocs `qrCode` est remplacé
par `QrCodeBlockRenderer`, qui dessine les `data` du bloc avec les valeurs par défaut de cette configuration — niveau,
zone de silence, couleurs, taille, ECI, `max_bytes` —, de sorte que les blocs ressemblent aux autres codes de
l'application. Un bloc sans texte, ou que le code ne peut pas contenir, est signalé à son auteur par
phil/svg-blocks-bundle.

## Documents PDF

Avec phil/paged-media, `PdfQrCodes` dessine un code sur la page courante d'un `Document`, en vectoriel :

```php
$document = new Document();
$document->addPage();
$qrCodes->draw($document, 'https://example.com/tickets/42', x: 450, y: 60, side: 108, options: ['logo' => 'brand']);
```

`(x, y)` est le coin inférieur gauche du code, zone de silence comprise, et `side` sa largeur en points. Le code est
un carré clair et un rectangle par suite de modules foncés, net à toute taille d'impression. Il prend les options de
`QrCodes` ; un logo PNG ou JPEG est ajusté à sa zone, ses proportions gardées, et un logo SVG est refusé.
