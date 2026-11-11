<!-- phil-dev source-sha256=9dad53f3cc0d0c2ae9dd9bab73ab3a776afee7262fdae3be5b17004b3637ea1b -->
# WebFinger

`/.well-known/webfinger?resource=acct:alice@example.com&rel=self` répond par le JSON Resource Descriptor de la
ressource (RFC 7033) : son sujet, ses alias, ses propriétés et ses liens.

## La requête

La chaîne de requête est lue telle qu'elle est envoyée, pour que chaque `rel` répété compte ; les paramètres analysés
par PHP ne gardent que le dernier. Les autres paramètres sont ignorés.

| Requête | Réponse |
|---|---|
| Aucun `resource`, deux, ou un qui n'est pas une URI absolue | `400`, vide |
| Plus longue que 4 096 octets, ou plus de 32 `rel` | `400`, vide |
| Une ressource qu'aucun résolveur ne connaît, quel que soit son schéma | `404`, vide |
| Une ressource qu'un résolveur connaît | `200`, le descripteur, avec seulement les liens des `rel` demandés |

Chaque réponse porte `Access-Control-Allow-Origin: *`, comme le demande la RFC 7033.

## Ressources

| Schéma | Lu comme |
|---|---|
| `acct:` (RFC 7565) | une partie utilisateur faite de caractères non réservés, de sous-délimiteurs et d'octets encodés en pourcentage, `@`, un hôte et un port facultatif ; le schéma et l'hôte en minuscules, la partie utilisateur telle qu'envoyée |
| `https:` | `//`, un hôte sans identifiants de connexion et un port facultatif, puis n'importe quel chemin, chaîne de requête et fragment ; le schéma et l'hôte en minuscules |
| un autre schéma | en minuscules, et connu d'aucun résolveur du bundle |

Aucune ressource ne contient d'espace ni de caractère de contrôle, et aucune ne dépasse 2 048 octets. L'URI telle que
lue, `WebFingerResource::$uri`, est la clé par laquelle les résolveurs cherchent la ressource ; `$user` est la partie
utilisateur, décodée des pourcentages.

## Résolveurs

Le `StaticWebFingerResolver` de `webfinger.resources` répond en premier, puis chaque service qui implémente
`WebFingerResolver`, autoconfiguré, dans l'ordre de leurs étiquettes. Chacun retourne un `WebFingerDescriptor` ou
`null`. Les descripteurs sont fusionnés : le sujet et les propriétés du premier, les alias de tous sans répétition, les
liens de tous dans l'ordre.

```php
final readonly class AccountResolver implements WebFingerResolver
{
    public function __construct(private AccountRepository $accounts) {}

    public function resolve(WebFingerResource $resource): ?WebFingerDescriptor
    {
        if (!$resource->isAccount() || 'example.com' !== $resource->host) {
            return null;
        }

        $account = $this->accounts->findPublicByName((string) $resource->user);

        return null === $account ? null : new WebFingerDescriptor($resource->uri, links: [
            new WebFingerLink('self', 'application/activity+json', $account->actorUrl()),
        ]);
    }
}
```

## Ce que WebFinger révèle

Une ressource qu'aucun résolveur ne connaît et une ressource d'un schéma que le bundle ne lit pas reçoivent le même
`404` vide : la réponse n'en dit pas plus sur les comptes du site que ce que décrivent ses résolveurs. Un résolveur qui
ne décrit que les comptes qui ont choisi d'être trouvés garde les autres privés.
