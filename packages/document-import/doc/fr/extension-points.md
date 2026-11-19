<!-- phil-dev source-sha256=c3d14db46bb354feccc345e9573f822be6ac7391c9236b1bf19602450cecc6c4 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## `MediaStore`

`Phil\DocumentImport\MediaStore` : là où vont les images d'un document importé. Voir [Médias](media.md).

| Méthode | Contrat |
|---|---|
| `store(Media $media): string` | garde l'image et répond l'adresse qu'affiche son bloc d'image : `http` ou `https` avec un hôte, ou relative et non relative au protocole |

Une image SVG arrive au magasin déjà assainie.

## `Contract\MediaSource`

`Phil\DocumentImport\Contract\MediaSource` : là où l'exportateur trouve les images qu'un document montre. Voir
[Exporter](export.md).

| Méthode | Contrat |
|---|---|
| `fetch(string $url): ?string` | les octets de l'image à une adresse, ou `null` quand l'application ne la détient pas ; jamais une requête vers l'adresse |

`MediaMap` est la source sur des images en mémoire : les `Media` d'une importation, ou des octets, par adresse.

## La table des styles

`StyleMap`, donnée au `DocumentImporter`, nomme ce que deviennent les styles de paragraphe de l'application : voir
[Table des styles](styles.md).

## Lecteurs et normaliseur

Les lecteurs de Markdown et de HTML sont n'importe quel `Phil\Markup\Reader\MarkupReader`, et le normaliseur des
documents DOCX et ODT n'importe quel `Phil\Ujo\Normalizer`, donnés au `DocumentImporter` : voir
[Markdown et HTML](markup.md).

## L'assainisseur SVG

Le `Phil\SvgSanitizer\SvgSanitizer` donné au `DocumentImporter` décide de ce que garde une image SVG, par sa
politique : voir [Médias](media.md).

## Limites

`Limits`, donné au `DocumentImporter`, borne ce qu'un document peut contenir (entrées, fichiers et flux une fois
décodés, imbrication, objets PDF) : voir [Importer](importing.md).

## Le rapport comme bloc

`Report::toBlock()` et `Imported::documentWithReport()` prennent le titre du bloc : l'application le donne dans la
langue de son lecteur, et traduit les phrases d'après les valeurs d'`Omitted` quand elle les rend elle-même.
