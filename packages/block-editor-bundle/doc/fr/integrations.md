<!-- phil-dev source-sha256=a106810396fe0c4b5d78c8dabe93c2216ceeca281fc7d1cc4c0c617a253f9885 -->
# Intégrations

## Téléversements d'images

L'application conserve les images ; le bundle les lui transporte depuis l'éditeur. Écrivez un `ImageUploader` :

```php
use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\ImageUploader;
use Phil\BlockEditorBundle\Application\Upload\UploadedImage;

final readonly class MediaUploader implements ImageUploader
{
    public function __construct(private MediaLibrary $library, private Urls $urls) {}

    public function upload(ImageUpload $image): UploadedImage
    {
        $type = mime_content_type($image->path);

        if (!\in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new UploadRefused('Only JPEG, PNG, WebP and AVIF images are accepted.');
        }

        $media = $this->library->upload($image->path, $image->clientName);

        return new UploadedImage($this->urls->of($media), $media->width, $media->height, ['media' => $media->id]);
    }
}
```

```yaml
phil_block_editor:
    image_uploader: App\Media\MediaUploader
```

`ImageUpload` transporte le fichier temporaire et ce que le navigateur en a dit — nom, type, taille —, qui n'est jamais
une preuve : le téléverseur lit lui-même le fichier. Le message de `UploadRefused` est présenté au rédacteur.
`UploadedImage` devient le `file` du bloc : `url`, `width` et `height` si elles sont connues, et les champs
supplémentaires que l'application garde — un identifiant de sa médiathèque, un crédit. Sur `phil/media-bundle`, le
téléverseur appelle sa bibliothèque et retourne l'URL signée et l'identifiant du média.

Le point de terminaison accepte le champ `image` d'un `POST` multipart, refuse un fichier au-delà de `max_upload_bytes`,
et répond comme l'attend l'outil d'image d'Editor.js :

```json
{"success": 1, "file": {"url": "/media/7/cat.webp", "width": 1200, "height": 800, "media": 7}}
```

## Mentions et liens de page

Écrivez un `ReferenceSearch` par menu :

```php
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;

final readonly class PeopleSearch implements ReferenceSearch
{
    public function __construct(private People $people) {}

    public function search(string $query, int $limit): iterable
    {
        foreach ($this->people->startingWith($query, $limit) as $person) {
            yield new Suggestion('user:'.$person->id, $person->name, '/people/'.$person->slug, $person->handle);
        }
    }
}
```

```yaml
phil_block_editor:
    mention_search: App\Search\PeopleSearch
    link_search: App\Search\PageSearch
```

La référence d'une suggestion est `kind:identifier` — un genre en minuscules, puis jusqu'à 128 lettres, chiffres, `_`,
`.`, `/` ou `-` —, la forme que `phil/ujo` conserve dans `data-mention` et `data-ref`. Son libellé est ce que le menu
affiche et ce que le texte insère. La recherche reçoit ce qui a été tapé après le déclencheur, épuré et coupé à 100
caractères, possiblement vide, et le point de terminaison répond avec ses 8 premières suggestions :

```json
{"items": [{"reference": "user:42", "label": "Ada Lovelace", "url": "/people/ada", "hint": "@ada"}]}
```

Les recherches s'exécutent sous la session du rédacteur : une recherche retourne seulement ce que cette personne peut
voir.

## Protéger les points de terminaison

Chaque point de terminaison demande d'abord au vérificateur d'autorisations de l'application l'attribut `access`,
`IS_AUTHENTICATED` par défaut, et refuse quiconque ne l'a pas, avant d'appeler le téléverseur ou la recherche ; sans
SecurityBundle, il refuse tout le monde. Un pare-feu ne le fait pas seul : un pare-feu paresseux, ou qui laisse passer
les visiteurs anonymes, ouvre les routes à quiconque envoie depuis un script les en-têtes ci-dessous. Avec
`access: ~`, l'application doit protéger le préfixe par une entrée `access_control`
([installation.md](installation.md#les-routes)).

Les services `ImageUploader` et `ReferenceSearch` ne reçoivent pas l'utilisateur : quand les fichiers gardés, ou les
suggestions retournées, dépendent de qui demande, lisez-y l'utilisateur courant dans le service `Security` de Symfony :

```php
public function search(string $query, int $limit): iterable
{
    $member = $this->security->getUser();

    if (null === $member) {
        return [];
    }

    // only what $member may see
}
```

Chaque point de terminaison exige aussi, sous le contrôle de `EditorRequest`, pour qu'un autre site ne puisse pas
l'appeler au nom du rédacteur :

- l'en-tête propre à l'éditeur, `X-Phil-Block-Editor: 1`, qu'un formulaire ou un lien d'un autre site ne peut pas
  ajouter sans que le navigateur demande d'abord ;
- aucun `Sec-Fetch-Site: cross-site` venu du navigateur ;
- pour le téléversement d'image, un `POST`, la vérification CSRF sans état de Symfony pour le jeton `phil_block_editor`.
  Les recherches `GET` des menus `@` et `[[` ne changent rien et ne portent aucun jeton. À chaque téléversement,
  l'éditeur tire un nouveau jeton aléatoire, le place dans un témoin nommé d'après la valeur que le champ lui donne
  (`csrf-token` par défaut, `csrf_protection.cookie_name` de FrameworkBundle ; préfixé de `__Host-` et `secure` en
  HTTPS, même site seulement) et l'envoie dans un en-tête du même nom. `SameOriginCsrfTokenManager` accepte la requête
  quand `Sec-Fetch-Site`, `Origin` ou `Referer` du navigateur nomme ce site, ou quand l'en-tête et le témoin portent le
  même jeton, et la refuse quand l'un ou l'autre dit le contraire. FrameworkBundle efface le témoin dans la réponse.

Une requête refusée reçoit 403, une route sans son service 404.

## Savoir où les choses sont utilisées

`DocumentReferences` lit un document pour trouver ce vers quoi il pointe :

```php
use Phil\BlockEditorBundle\Application\Reference\DocumentReferences;

DocumentReferences::mentions($document);   // ['user:42', …]
DocumentReferences::links($document);      // ['page:7', …]
DocumentReferences::files($document);      // ['/media/7/cat.webp', …] — images, attachments, galleries
```

Avec `phil/references-bundle`, un collecteur de références propre à l'application transforme ces résultats en liens, de
sorte qu'une page liée ou une image affichée ne peut pas être supprimée tant qu'un document l'utilise. Les documents
sont lus dans la forme canonique qu'écrit `phil/ujo` : décodez-les ou normalisez-les d'abord.

## Rendu pour les lecteurs

L'éditeur ne produit pas le rendu des documents pour les lecteurs. `ujo_html` de `phil/ujo-bundle` s'en charge, avec les
classes qu'écrivent les outils en ligne de l'éditeur. La feuille de style du site les met en forme et donne sa couleur à
chaque jeton de couleur :

```css
.text-color[data-color='brand'] { color: var(--brand); }
```
