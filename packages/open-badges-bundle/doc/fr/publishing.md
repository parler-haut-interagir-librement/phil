<!-- phil-dev source-sha256=09f206a7c1443e72c7200de144c92c3cc0ee182a5b0b9b78980aa29844296eb9 -->
# Publication

Les routes de `config/routes.php` servent ce dont tout le monde a besoin pour vérifier les attestations de l'émetteur.
Chaque réponse est du JSON ou un jeton, ouverte à toutes les origines (`Access-Control-Allow-Origin: *`), et peut être
mise en cache par les navigateurs et les caches partagés.

| Route | Chemin sous le préfixe | Réponse |
|---|---|---|
| `phil_open_badges_issuer` | `/issuer` | Le document de profil de l'émetteur, `application/ld+json` : son `Profile`, et sa clé Ed25519 comme méthode de vérification `Multikey` avec laquelle il affirme. |
| `phil_open_badges_issuer_keys` | `/issuer/keys` | Son JSON Web Key Set, `application/jwk-set+json`, le `kid` de la clé étant sa méthode de vérification. |
| `phil_open_badges_status_list` | `/status/{list}` | La `BitstringStatusListCredential` de la liste, sécurisée selon la configuration. |

Le profil et les clés peuvent être gardés `max_age` secondes, portent un `ETag`, et répondent `304` à une requête qui
les a déjà. Une clé RSA n'a pas de Multikey : le profil ne liste alors aucune méthode de vérification, et les clés se
trouvent dans le jeu de clés.

## Listes d'état

L'application nomme chaque liste — `awards`, `certificates-2026` — avec des lettres, des chiffres, `_` et `-`, en
64 caractères au plus. Son contenu vient des `StatusLists` de l'application :

```php
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;

final readonly class AwardStatusLists implements StatusLists
{
    public function find(string $list): ?StatusListContent
    {
        return 'awards' === $list ? new StatusListContent($this->awards->revokedStatusIndexes()) : null;
    }
}
```

Une liste que l'application n'a pas donne un `404`. Chaque réponse est construite à partir du contenu du moment : la
liste de `status_lists.size` bits, son objet (`revocation` par défaut, ou `suspension`), valide dès maintenant pour
`status_lists.max_age` secondes et pouvant être mise en cache aussi longtemps. Les vérificateurs voient donc une
révocation dans ce délai. La liste est sécurisée comme les attestations de l'émetteur : un jeton (`application/vc+jwt`)
ou un document avec sa preuve (`application/vc`).

`StatusListUrls::listOf()` répond à laquelle des listes de l'application pointe une entrée d'état : il lit l'URL de
l'entrée avec `phil/url`, comme la lit un navigateur, prend le dernier segment de son chemin comme nom de la liste, et ne
garde ce nom que si la route donne exactement cette URL. Un autre hôte, une autre graphie du chemin, une chaîne de
requête ou un fragment ne sont pas une liste de l'application. `allow_localhost` permet à ces URL de nommer `localhost`,
comme il le permet aux adresses de l'émetteur.
