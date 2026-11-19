<!-- phil-dev source-sha256=9f435709eb8f642e0d6328ff15a810860b3b748a174dd9a16838828d6511e0d6 -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise.

## Un stockage

Tout service qui implémente `Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository`, nommé dans `store`, remplace
celui du bundle : des jetons sur une autre API de base de données, derrière un cache. Il tient les promesses que
vérifient les tests de contrat du bundle : `find()` retourne les plus récents d'abord, puis par identifiant, dans la
limite; `recordUse()` écrit seulement la dernière utilisation, sans rien vider d'autre; `purge()` supprime ce qui a
expiré ou a été révoqué strictement avant l'instant donné et retourne leur nombre.

```yaml
phil_api_keys:
    store: App\Security\CachedTokenRepository
```

## Les types de propriétaires

`owner_types` déclare les propriétaires pour qui un jeton peut agir en plus des membres : pour chacun, le fournisseur
d'utilisateurs (tout service `UserProviderInterface`) qui charge un propriétaire par son identifiant quand son jeton
s'authentifie, et les portées que ses jetons peuvent détenir. Le fournisseur donne au jeton de sécurité l'objet
utilisateur propre à l'application pour une organisation ou une application. `Phil\ApiKeysBundle\Application\OwnerTypes`,
un service public, les énumère.

## Qui gère les jetons d'un autre propriétaire

La page des jetons d'un autre propriétaire demande aux *voters* de l'application `TokenPageController::MANAGE`
(`PHIL_API_KEYS_MANAGE`) sur le `TokenOwner` ; personne ne l'obtient sans un *voter* de l'application. Voir
[La page des membres](members-page.md#les-jetons-dun-autre-propriétaire).

## Un anonymiseur d'adresse

Un `Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer`, nommé dans `last_used_address`, indique ce que
la ligne garde de l'adresse du client, 100 octets au plus, ou `null` pour ne rien garder :

```php
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;

final readonly class CountryOnly implements ClientAddressAnonymizer
{
    public function __construct(private CountryLookup $countries)
    {
    }

    public function anonymize(string $address): ?string
    {
        return $this->countries->countryOf($address);
    }
}
```

`Infrastructure\IpAnonymizer\AnonymizedClientAddress` enveloppe n'importe quelle stratégie de `phil/ip-anonymizer`.

## Les réponses à un jeton refusé

Le `401` est celui de Symfony : l'authentificateur `access_token` y répond, en texte simple, ou le `entry_point` du
pare-feu et le `failure_handler` de l'authentificateur quand ils sont nommés. `phil_api_keys.problem_responder` est
l'un et l'autre, sous forme de détails de problème, avec `phil/api-contract-bundle`. Une application qui répond dans
son propre format y nomme sa propre `AuthenticationEntryPointInterface` et sa propre
`AuthenticationFailureHandlerInterface`, en gardant le défi `WWW-Authenticate` de la RFC 6750 et sans jamais dire
pourquoi un jeton a été refusé.

## Les événements

`TokenCreated`, `TokenRevoked`, `TokenUsed` et `TokenLeaked`, répartis par le répartiteur PSR-14, portent chacun
l'identifiant du jeton : voir [Intégrations](integrations.md#les-événements-pour-une-piste-daudit).

## Les clés de l'analyse de secrets de GitHub

`http_client` et `cache` nomment les services avec lesquels `GitHubPublicKeys` va chercher et garde les clés de GitHub :
un client avec un mandataire, un bassin sur Redis partagé par chaque serveur. Voir
[Analyse de secrets](secret-scanning.md).

## Les portées

Les portées relèvent de la configuration. Une application les lit dans `Phil\ApiKeysBundle\Application\ScopeCatalogue`,
un service public, pour documenter son API ou construire son propre formulaire; `select()` vérifie une liste par
rapport à elles.

## La page des membres

Le gabarit relève de la configuration, et le gabarit partiel `@PhilApiKeys/_tokens.html.twig` s'insère dans la mise en
page de l'application : voir [La page des membres](members-page.md). Le remplacement habituel de Twig fonctionne aussi,
avec `templates/bundles/PhilApiKeysBundle/_tokens.html.twig`.

## Ce que l'application utilise

| Service | Rôle |
|---|---|
| `Phil\ApiKeysBundle\Application\ApiTokens` | Public : crée, trouve, obtient, révoque, révoque tous ceux d'un propriétaire, renouvelle, purge et vérifie des jetons |
| `Phil\ApiKeysBundle\Application\ScopeCatalogue` | Public : les portées déclarées |
| `Phil\ApiKeysBundle\Application\OwnerTypes` | Public : les types de propriétaires et les portées que chacun peut détenir |
| `phil_api_keys.owner_providers` | Les fournisseurs d'utilisateurs des types de propriétaires, par type, avec lesquels le gestionnaire de jetons charge les propriétaires |
| `Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository` | Alias public du stockage |
| `phil_api_keys.token_handler` | Le gestionnaire de jetons qu'un pare-feu nomme dans `access_token.token_handler` |
| `phil_api_keys.token_extractor` | L'extracteur de l'en-tête configuré, nommé dans `access_token.token_extractors` |
| `phil_api_keys.problem_responder` | Le `401` sous forme de détails de problème, nommé comme `entry_point` et `access_token.failure_handler`, quand `phil/api-contract-bundle` est installé |
| `phil_api_keys.rate_limit_key` | La stratégie de clé de `phil/rate-limit-bundle` par jeton, quand il est installé |

Les attributs de requête `_api_token_id` et `_api_token_scopes`, les attributs du jeton de sécurité `_api_token_id` et
`oauth2_scope`, et les rôles `ROLE_API_SCOPE_*` font partie du contrat du bundle.
