<!-- phil-dev source-sha256=9d6c15666898fd9fb096c5c7f9077b03f1d07aef4b678897b2f20112be96ef4a -->
# Médias

## Les images

Chaque image qu'une importation tire du fichier est un `Media` :

| Propriété ou méthode | Contenu |
|---|---|
| `path` | Son chemin dans l'archive (`word/media/image1.png`, `Pictures/1000.png`), ou `image-N.ext` pour un collage HTML |
| `contents` | Ses octets |
| `description` | Le texte de remplacement que lui a donné son auteur |
| `mediaType` | Lu d'après les octets : PNG, JPEG, GIF, WebP, AVIF, BMP, SVG, TIFF, EMF, WMF, ou `application/octet-stream` |
| `isWebImage()` | Si un navigateur l'affiche : PNG, JPEG, GIF, WebP, AVIF, SVG ou BMP |
| `filename()` | Le dernier segment du chemin, où tout caractère autre qu'une lettre, un chiffre, `.`, `_` ou `-` est remplacé par `-`, et qui se termine par une extension du type que montrent ses octets : `chart.html` qui contient une image PNG se nomme `chart.html.png` |

Seules les images qu'un navigateur affiche deviennent des blocs d'image ; les autres sont signalées. La même image
utilisée deux fois forme une seule entrée d'`Imported::$media` et deux blocs.

## Les images SVG

Une image SVG peut porter des scripts, des gestionnaires d'événements et des références à d'autres fichiers. Avant
d'être remise au magasin de médias, chaque image SVG passe par le `SvgSanitizer` de `phil/svg-sanitizer` : le `Media`
que reçoit le magasin, et que contient `Imported::$media`, n'a que ce que l'assainisseur garde. Chaque partie retirée
est signalée comme `Omitted::SvgContent`, avec la raison de son retrait comme détail. Une image que l'assainisseur
refuse en entier (mal formée, trop grande, qui déclare des entités) n'est pas stockée, et elle est signalée comme une
image qu'un navigateur n'affiche pas. Toute
image SVG y passe, où que le document la contienne : dans le corps, une cellule de tableau, une note de bas de page ou
de fin, un élément de liste, dans une archive DOCX ou ODT, incorporée dans un ODT plat ou un RTF, ou dans une adresse
`data:` de HTML ou de Markdown.

L'importateur prend l'assainisseur, et donc sa politique :

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$importer = new DocumentImporter(svg: new SvgSanitizer(new Policy(maxBytes: 1_048_576)->withDataImages(false)));
```

## Le magasin de médias

Un `MediaStore` répond l'adresse qu'affiche un bloc d'image :

```php
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaStore;

final readonly class UploadStore implements MediaStore
{
    public function __construct(private Storage $storage) {}

    public function store(Media $media): string
    {
        return $this->storage->put('imports/'.hash('sha256', $media->contents).'.'.pathinfo($media->filename(), PATHINFO_EXTENSION), $media->contents);
    }
}
```

L'adresse doit être une adresse qu'accepte une image UJO : `http` ou `https` avec un hôte, ou relative et non relative
au protocole. Toute autre adresse fait échouer la vérification du document avec `Phil\Ujo\Exception\InvalidDocument`.

`MediaFolder`, le magasin par défaut, ne garde rien : il répond son préfixe (`media/` si aucun n'est donné) suivi du
nom du fichier, et l'application y écrit elle-même `Imported::$media` :

```php
foreach ($imported->media as $address => $media) {
    file_put_contents($publicDirectory.'/'.$address, $media->contents);
}
```

Deux images différentes d'un même document qui ont le même nom de fichier (dans des dossiers différents d'une
archive, ou incorporées sous un même nom) recevraient la même adresse de `MediaFolder`. L'importateur les distingue :
quand l'adresse que répond le magasin contient déjà d'autres octets, il la redemande pour l'image sous son chemin
numéroté (`media/image1-2.png`), jusqu'à mille fois, et signale comme `same name` une image qu'il ne peut toujours pas
placer. La même image rencontrée deux fois garde une seule adresse.

## Des images là où il n'y a que du texte

Une cellule de tableau, un élément de liste et une note ne contiennent que du texte. Une image rencontrée là est tout de
même tirée et donnée au magasin, et son bloc d'image vient après le tableau, après la liste, ou après le paragraphe qui
renvoie à la note.
