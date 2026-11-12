<!-- phil-dev source-sha256=0367c5163588d26f9c76827cdaa8d94c84f0ecfc4cc4ad90f25f5abaa5a1d26c -->
# Contenu d'usager

Un fichier téléversé par quelqu'un (un avatar, une pièce jointe, un document) est servi depuis l'origine même du site,
donc tout ce que le navigateur en fait s'exécute avec les cookies du site. Une page HTML renommée `.png`, un SVG avec un
script, un fichier servi sans type et deviné comme du HTML : chacun devient une page du site. La politique du contenu
d'usager sert un tel fichier avec des en-têtes qui le gardent inerte, et s'applique par route, par contrôleur ou par
réponse.

## Les en-têtes

| En-tête | Valeur | Pourquoi |
|---|---|---|
| `Content-Security-Policy` | `default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox` | Ouvert directement, le fichier ne charge rien, n'exécute aucun script, n'envoie aucun formulaire et n'est encadré nulle part ; `sandbox` lui donne une origine opaque, loin des cookies et du stockage du site. |
| `Content-Security-Policy-Report-Only` | retiré | La politique report-only du site concerne ses pages, pas ses fichiers. |
| `X-Content-Type-Options` | `nosniff` | Le navigateur s'en tient au type déclaré. |
| `Content-Type` | gardé ; `application/octet-stream` quand la réponse n'en a pas | Symfony enverrait sinon `text/html`. |
| `Content-Disposition` | `inline` pour un type de la liste des types affichés, `attachment` sinon | Seul un type qui ne peut pas s'exécuter est montré dans le navigateur. |
| `Cross-Origin-Resource-Policy` | `same-origin` | Un autre site ne peut pas intégrer le fichier. |
| `Referrer-Policy` | `no-referrer` | Un lien dans un document ne laisse fuir aucune adresse du site. |
| `X-Permitted-Cross-Domain-Policies` | `none` | Aucun module d'extension n'y lit de fichier de politique. |
| `X-Frame-Options` | `DENY`, tant que `frame-ancestors` vaut `'none'` | Pour les navigateurs qui ignorent `frame-ancestors`. |

Ces en-têtes remplacent ceux que porte la réponse. Les écouteurs de la politique et des autres en-têtes les gardent
ensuite, puisqu'ils n'écrasent jamais un en-tête qu'une réponse porte déjà ; une valeur par défaut de route ou un
attribut de [surcharge](overrides.md) qui en nomme un l'emporte toujours.

### La disposition

Les types affichés sont, par défaut, les images matricielles (`image/avif`, `image/gif`, `image/jpeg`, `image/png`,
`image/webp`), l'audio et la vidéo des navigateurs (`audio/mpeg`, `audio/ogg`, `audio/wav`, `audio/webm`, `video/mp4`,
`video/ogg`, `video/webm`) et `text/plain`. Le type est lu dans `Content-Type` sans ses paramètres, en minuscules. Tout
autre type est une pièce jointe, de même que toute réponse qui dit déjà `attachment`.

Les types qui peuvent s'exécuter comme une page ou un script ne sont jamais affichés, quoi que dise la configuration :
`text/html`, `application/xhtml+xml`, `image/svg+xml`, `text/xml`, `application/xml`, `text/xsl`, et les types
JavaScript. La construction échoue quand la liste des types affichés en nomme un. Un SVG se montre avec `<img>`, où il
n'exécute rien, pas en l'ouvrant.

Le nom de fichier du `Content-Disposition` de la réponse est gardé. Un nom donné à `apply()` le remplace, débarrassé de
tout chemin, caractère de contrôle et guillemet, et écrit avec `filename*` en UTF-8 à côté d'un `filename` ASCII, comme
l'écrit `HeaderUtils::makeDisposition()`.

## L'appliquer

### Par route

```yaml
# config/routes.yaml
upload:
    path: /uploads/{id}
    controller: App\Controller\UploadController
    defaults:
        _user_content: true
```

La valeur par défaut est `true` ou `false` ; toute autre valeur lève `InvalidPolicy` quand la réponse est écrite.

### Par contrôleur

```php
use Phil\BrowserSecurityBundle\Application\Attribute\UserContent;

final class UploadController
{
    #[UserContent]
    public function __invoke(Upload $upload): BinaryFileResponse
    {
        return new BinaryFileResponse($upload->path());
    }
}
```

L'attribut va sur la classe ou sur une méthode. Les deux ne se lisent que sur la requête principale, et les en-têtes
sont écrits sur `kernel.response` à la priorité 8 : après une redirection qui a remplacé la réponse, avant les autres
en-têtes.

### Par réponse

```php
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;

final readonly class AvatarController
{
    public function __construct(private UserContentPolicy $userContent)
    {
    }

    public function __invoke(Avatar $avatar): Response
    {
        $response = new BinaryFileResponse($avatar->path());
        $this->userContent->apply($response, $avatar->originalName());

        return $response;
    }
}
```

`apply()` fonctionne sur n'importe quelle réponse, dans un contrôleur, un écouteur ou une sous-requête ; `isInline()` dit
comment un type est servi.

## Configuration

```yaml
phil_browser_security:
    user_content:
        directives:                   # the policy of the files, as a named policy declares it
            default-src: ["'none'"]
            base-uri: ["'none'"]
            form-action: ["'none'"]
            frame-ancestors: ["'none'"]
            sandbox: true
        inline_types: [image/avif, image/gif, image/jpeg, image/png, image/webp, audio/mpeg, audio/ogg, audio/wav, audio/webm, video/mp4, video/ogg, video/webm, text/plain]
        cross_origin_resource_policy: same-origin # same-site, cross-origin
```

`directives` remplace la politique entière : un site qui affiche ses fichiers PDF ajoute `application/pdf` aux types
affichés, et retire `sandbox`, que la plupart des lecteurs PDF refusent. Chaque directive et chaque valeur est vérifiée
comme celles d'une politique nommée, et chaque type affiché doit être un `type/subtype` en minuscules, sans paramètres
ni joker.

## D'où les servir

La politique rend un fichier inerte dans le navigateur ; elle ne lui donne pas une autre origine. Servir les
téléversements depuis un hôte à eux, sans les cookies du site, ajoute un second mur, et la politique s'y applique
toujours.
