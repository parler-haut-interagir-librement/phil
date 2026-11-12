<!-- phil-dev source-sha256=0cd788a8f7ece6ac5a1f78bd0d0b35bab08ea5d82e3ec219f5b18fa84eb04488 -->
# L'explorateur de fichiers

## Routes

```yaml
# config/routes/phil_media_admin.yaml
phil_media_admin:
    resource: '@PhilMediaBundle/config/admin_routes.php'
    prefix: /admin/media
```

L'application place le préfixe derrière son pare-feu. L'explorateur demande `attribute` à la sécurité de l'application,
`ROLE_ADMIN` par défaut, avant chaque liste, sélecteur compris, et avant chaque changement : sur la collection listée ou
dans laquelle on téléverse, sur le média renommé, déplacé ou supprimé et sur la collection où il est déplacé (`null`, la racine, s'il n'y en a
pas), sur rien pour une nouvelle collection. Un rôle,
ou l'attribut d'un *voter* qui décide selon le sujet, l'ouvre ; tout le reste répond `403`, comme chaque requête quand
la sécurité de Symfony n'est pas installée.

```yaml
phil_media:
    admin:
        attribute: MEDIA_MANAGE   # a voter's attribute; ROLE_ADMIN by default
```

| Route | Méthode et chemin | |
|---|---|---|
| `phil_media_admin` | `GET /` | La liste : `collection`, `view` (`grid` ou `list`), `page` ; `picker=1` pour le sélecteur |
| `phil_media_admin_upload` | `POST /upload` | `files[]`, plusieurs à la fois, dans `collection` |
| `phil_media_admin_collection` | `POST /collections` | Une collection : `key` et `name` |
| `phil_media_admin_rename` | `POST /{id}/rename` | `filename` |
| `phil_media_admin_move` | `POST /{id}/move` | `collection`, ou `''` pour aucune |
| `phil_media_admin_delete` | `POST /{id}/delete` | Le média, comme `MediaLibrary::delete()` |

## La liste

`MediaAdminController` liste les médias d'une collection, ou hors de toute collection, les plus récents d'abord,
`page_size` à la fois, en grille de vignettes ou en liste ; chaque média a son nom, son type de média, sa taille en
pixels et en kibioctets, et ses formulaires pour le renommer, le déplacer et le supprimer. La vignette d'une image est le
format `admin_thumbnail`, 240 × 240 ajusté, que le bundle déclare sauf si l'application déclare un format de ce nom ; un
autre fichier montre son extension.

Chaque formulaire fonctionne sans JavaScript : il envoie, et le navigateur est renvoyé par un `303` vers la liste, qui
dit ce qui a été fait ou ce qui a été refusé : un fichier trop gros, un nom refusé, une collection qui existe, un média
utilisé. Interrogées en JSON, les mêmes routes répondent en JSON, `422` en cas de refus.

Un formulaire envoyé depuis un autre site est refusé par un `403`. `ExplorerAccess` exige deux choses :

- le `Sec-Fetch-Site` du navigateur dit `same-origin`, ou, quand il n'en envoie pas, son `Origin` est celui de ce site ;
- le formulaire passe la vérification CSRF sans état de Symfony pour le jeton `phil_media_explorer`. Chaque formulaire
  porte le champ `_csrf_token`, rendu avec le nom du témoin du jeton (`csrf-token` par défaut, le
  `csrf_protection.cookie_name` de FrameworkBundle). Au moment où un formulaire est envoyé, les contrôleurs `explorer`
  et `upload` le remplacent par un nouveau jeton aléatoire et le placent dans un témoin de ce nom (préfixé `__Host-` et
  `secure` en HTTPS, même site seulement), pour que le champ et le témoin portent le même jeton ; sans JavaScript, le
  champ garde le nom et l'origine seule décide. `SameOriginCsrfTokenManager` refuse un jeton qui n'est ni l'un ni
  l'autre, et le témoin d'un autre jeton quand les en-têtes du navigateur ne nomment pas ce site ; FrameworkBundle
  efface le témoin dans la réponse.

Le bundle ajoute le jeton aux `csrf_protection.stateless_token_ids` de FrameworkBundle, ce qui active
`framework.csrf_protection` : laissez-le activé. Avec `csrf_protection: false`, le conteneur s'arrête sur une
`LogicException` qui le dit.

```yaml
phil_media:
    admin:
        layout: '@PhilMedia/admin/layout.html.twig'   # a template with a "content" block
        page_size: 48
        max_upload_bytes: 33554432                      # 32 MiB; PHP's upload_max_filesize must allow it
        attribute: ROLE_ADMIN                           # the role or voter attribute that opens the explorer
```

La mise en page est celle de l'application : ses feuilles de style et l'application Stimulus y vont. Les gabarits du
bundle sont du HTML simple avec des classes `phil-media-*`, et l'application peut redéfinir n'importe lequel sous
`templates/bundles/PhilMediaBundle/`.

## Stimulus

Avec AssetMapper, le bundle mappe ses contrôleurs sous `@phil/media-bundle` ; StimulusBundle les enregistre à partir
d'`assets/package.json` :

| Contrôleur | Identifiant | Ce qu'il fait |
|---|---|---|
| `explorer` | `phil--media-bundle--explorer` | Bascule entre grille et liste sans recharger, se souvient du choix dans le navigateur, demande confirmation avant une suppression, donne à chaque formulaire un nouveau jeton CSRF au moment de l'envoi |
| `upload` | `phil--media-bundle--upload` | Envoie chaque fichier choisi ou déposé dans sa propre requête avec un nouveau jeton CSRF, avec une barre de progression, refuse un fichier trop gros avant de l'envoyer, puis recharge la liste |
| `picker` | `phil--media-bundle--picker` | Ouvre le sélecteur dans un `<dialog>`, y suit ses collections et ses pages, et remplit le champ avec le média choisi |
| `placeholder` | `phil--media-bundle--placeholder` | Peint l'aperçu minuscule d'un média derrière son image pendant qu'elle se charge, pour les composants `Img` et `Picture` ; voir [Images adaptatives](images.md#aperçus) |

## Le sélecteur

```php
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;

$builder->add('cover', MediaPickerType::class, ['collection' => 'covers']);
```

Avec `symfony/form`, `MediaPickerType` est un champ caché qui contient l'identifiant du média, ses données étant un
`MediaId`. Il est montré avec le nom et la vignette du fichier choisi, un bouton qui ouvre le sélecteur (la liste sans
la mise en page, chaque média étant un bouton), et un bouton qui vide le champ. `collection` ouvre le sélecteur sur une
collection ; une valeur qui n'est pas un identifiant est un échec de transformation, `This is not a medium.`. Le bundle
ajoute son thème de formulaire, `@PhilMedia/form/media_picker.html.twig`, à ceux de Twig. Associez le champ à la
contrainte `MediaFile` pour limiter ce qui peut être choisi : le sélecteur montre toutes les collections.
