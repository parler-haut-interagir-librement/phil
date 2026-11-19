<!-- phil-dev source-sha256=1baeac66c33dc7517137983af017fb33f4da0431dc7efd07592b1f38ea5b68db -->
# Médias

## La médiathèque

Lorsque `PhilMediaBundle` est enregistré et que `media.store` vaut `auto` ou `library`, chaque image qu'une importation
extrait — chaque image SVG une fois assainie — est téléversée dans la médiathèque par `MediaLibraryStore` :

- comme un nouveau média, nommé d'après le nom de fichier de l'image dans le document ;
- dans la collection `media.collection`, que le magasin crée, nommée d'après sa clé, la première fois qu'elle sert ;
  dans aucune collection lorsque l'option n'est pas définie ;
- avec le texte de remplacement que lui a donné son auteur comme texte de remplacement du média, coupé aux 500
  caractères qu'un média conserve ;
- à l'adresse que la fermeture `phil_document_import.media_address` donne au média.

L'adresse est, par défaut, l'URL que donne `media_url(medium)` de `phil/media-bundle`, qui exige Twig comme cette
fonction. Lorsque la médiathèque remet des URL qui expirent (`url_ttl`), ou lorsque l'application montre ses médias à
des adresses qui lui sont propres, elle remplace la fermeture :

```yaml
services:
    phil_document_import.media_address:
        class: Closure
        factory: [Closure, fromCallable]
        arguments: [['@App\Media\PermanentUrls', 'of']]
```

L'adresse doit être une adresse qu'accepte une image UJO (Universal JSON Output) : `http` ou `https` avec un hôte, ou
relative mais sans être relative au protocole.

La médiathèque sert un fichier SVG en pièce jointe plutôt qu'en ligne, afin que même une image qu'elle n'a pas reçue de
l'importateur ne puisse pas s'exécuter dans l'origine de l'application ; un `<img>` l'affiche tout de même.

## Un dossier

Sans la médiathèque, ou avec `media.store: folder`, les images passent par `MediaFolder` : l'adresse d'une image est
`media.prefix` suivi de son nom de fichier, et l'application y écrit elle-même les octets de `Imported::$media`.

## Un magasin propre à l'application

Une application qui conserve ses fichiers ailleurs fait de `Phil\DocumentImport\MediaStore` un alias vers son propre
service ; l'importateur lui donne alors chaque image, les images SVG déjà assainies :

```yaml
services:
    Phil\DocumentImport\MediaStore: '@App\Import\BucketStore'
```
