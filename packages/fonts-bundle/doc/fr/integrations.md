<!-- phil-dev source-sha256=c3dbbb3a65c98801761a5581711f2c38cd5d7465c922e3f9b46e285f711fd9ce -->
# Intégrations

## Le générateur de PDF natif

Quand `phil/paged-media` est installé, `PdfFonts` donne les faces TrueType du catalogue à son générateur natif :

```php
$document->text(72, 720, 'Receipt', 18, $pdfFonts->get('Example Sans', 700));
```

`get(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal)` cherche la face parmi les seuls fichiers
TrueType, puisque le générateur intègre des contours TrueType, et lance `FontNotFound` si aucun ne la contient. Le
générateur applique ensuite les permissions d'intégration de la police : il refuse une police dont la licence interdit
l'intégration, et intègre en entier une police qui interdit le sous-ensemble.

## La médiathèque

Quand `phil/media-bundle` est installé et son bundle activé, `MediaFonts` lit une police téléversée dans la
médiathèque :

```php
$file = $mediaFonts->read($media);

if (!$file->face()->embedding->usage->allowsEmbedding()) {
    // refuse it for the PDF, show the licence to the editor…
}
```

`read(Media $media)` lit le fichier courant du média avec `FontFiles`, donc WOFF2 suit la même règle, et indique le nom
de fichier du média dans `FontUnreadable`. Quand le paquet est installé mais que le bundle n'est pas activé,
`RequiredServicesPass` retire le service.
