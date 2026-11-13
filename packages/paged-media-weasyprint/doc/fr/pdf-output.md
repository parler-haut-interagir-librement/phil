<!-- phil-dev source-sha256=51ab25db06a2b05075d10785efa29140995e98b53977c6fd6b65c7ca64029002 -->
# Sortie PDF

Sans options, le moteur écrit le PDF que WeasyPrint écrit par défaut. `PdfOptions`, le quatrième argument de
`WeasyPrintEngine`, en choisit la norme, l'intention de sortie, les balises, les champs de formulaire et les images :

```php
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;

$engine = new WeasyPrintEngine(pdf: new PdfOptions(PdfVariant::PdfA2u));
```

Chaque option est un argument de la commande, vérifié à la construction de l'objet : une valeur hors limites lance
`InvalidArgumentException`, et rien du document n'atteint la ligne de commande.

| Argument | Accepté | Argument de la commande |
|---|---|---|
| `$variant` | Un `PdfVariant` | `--pdf-variant` |
| `$outputIntent` | `srgb`, `device-cmyk`, ou le nom à tirets d'un `@color-profile` du document, comme `--press` | `--output-intent` |
| `$tags` | `true` pour baliser le PDF en vue de l'accessibilité | `--pdf-tags` |
| `$forms` | `true` pour que les champs de formulaire du document soient des champs de formulaire PDF | `--pdf-forms` |
| `$maxImageDpi` | De 1 à 2400 points par pouce | `--dpi` |
| `$jpegQuality` | De 0 à 95 | `--jpeg-quality` |
| `$optimizeImages` | `true` pour alléger les images sans perte de qualité | `--optimize-images` |

## Normes

`PdfVariant` contient les normes que WeasyPrint 70 écrit, sous leurs noms : PDF/A-1a, 1b, 2a, 2b, 2u, 3a, 3b, 3u, 4e, 4f
et 4u, PDF/UA-1 et PDF/UA-2, PDF/X-1a, PDF/X-3, PDF/X-4 et PDF/X-5g. WeasyPrint écrit les métadonnées, l'identifiant et,
pour PDF/A, une intention de sortie sRGB ; le document doit tout de même contenir ce que la norme demande, comme un
titre et une langue pour PDF/UA, que `PrintableHtml` transporte.

Le moteur ne valide pas le PDF. Pour le vérifier, ou le réparer, envoyez-le au client Gotenberg de `phil/paged-media`,
dont `validate()` et l'étape `Remediate` lisent PDF/A et PDF/UA.

## PDF/X et son profil de couleur

Un fichier PDF/X nomme la condition d'impression pour laquelle il a été fait, par le profil ICC de cette condition.
WeasyPrint le lit dans une règle `@color-profile` du document, et l'intention de sortie nomme cette règle. Une variante
PDF/X sans un tel profil est refusée à la construction des options, puisque WeasyPrint écrirait le fichier sans
intention de sortie.

Le profil vient du répertoire des ressources, comme une image : `LocalAssets` prend les fichiers `.icc` et `.icm`.

```php
use Phil\PagedMedia\Css\PageRule;
use Phil\PagedMedia\Css\PrinterMarks;
use Phil\PagedMedia\Pdf\PageSize;

$engine = new WeasyPrintEngine(
    assets: new LocalAssets('/srv/print-assets'),               // holds press.icc, from the printer
    pdf: new PdfOptions(PdfVariant::PdfX4, '--press'),
);

$pdf = $engine->pdf(new PrintableHtml($body, [
    '@color-profile --press { src: url("press.icc"); components: c, m, y, k }',
    new PageRule(PageSize::ofMillimetres(148, 210), [54], 8.5, PrinterMarks::Crop)->css(),
    $css,
], 'en', 'The book'));
```

Le descripteur `components` est obligatoire : WeasyPrint 70 s'arrête avec une erreur sur une intention de sortie dont le
profil ne liste pas ses composantes. `PageRule`, de `phil/paged-media`, écrit le format rogné, le fond perdu et les
marques : WeasyPrint les enregistre comme la boîte de rognage et la boîte de fond perdu de chaque page.

## Deux sortes de PDF

Les options appartiennent au moteur, pas à un document. Une application qui imprime à la fois des documents archivés et
des fichiers pour la presse enregistre deux moteurs, l'un sous le port et l'autre sous son propre nom :

```yaml
services:
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }

    app.press_engine:
        class: Phil\PagedMediaWeasyprint\WeasyPrintEngine
        arguments:
            $assets: '@Phil\PagedMediaWeasyprint\LocalAssets'
            $pdf: !service
                class: Phil\PagedMediaWeasyprint\PdfOptions
                arguments: [!php/enum Phil\PagedMediaWeasyprint\PdfVariant::PdfX4, '--press']
```
