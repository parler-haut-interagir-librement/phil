<!-- phil-dev source-sha256=49cbba1148a964de572dce5717e4828db7c7484716238a6a2f86077cb34df791 -->
# Authentification et portées

## Le pare-feu

Le pare-feu de l'API utilise l'authentificateur `access_token` de Symfony avec le gestionnaire de jetons du bundle, le
service `phil_api_keys.token_handler`, et son extracteur de jetons, le service `phil_api_keys.token_extractor` :

```yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            provider: app_users
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
```

L'authentificateur de Symfony fournit le reste : c'est le point d'entrée du pare-feu, il répond `401` à une requête sans
jeton et à un jeton refusé, avec le défi de la RFC 6750 et un corps `text/plain`, et il charge l'utilisateur par le
fournisseur du pare-feu. Donnez à `access_token.realm` la valeur du `realm` du bundle, pour que chaque défi nomme le
même domaine.

| `header` | L'extracteur lit |
|---|---|
| `Authorization` (par défaut), dans n'importe quelle casse | `Bearer <token>`, le schéma écrit `Bearer`, avec une ou plusieurs espaces |
| un autre, comme `X-Api-Key` | la valeur entière, sans espaces |

Une requête sans jeton est laissée aux autres authentificateurs du pare-feu, ou au point d'entrée. Le gestionnaire
vérifie le jeton par `ApiTokens::verify()`, avec l'adresse de la requête principale pour sa dernière utilisation; un
jeton refusé fait échouer l'authentification avec `BadCredentialsException`, qui ne dit jamais pourquoi. L'utilisateur
est chargé à partir du propriétaire du jeton par le fournisseur d'utilisateurs du pare-feu; un utilisateur que le
fournisseur ne connaît plus fait échouer l'authentification comme un jeton refusé.

## Organisations et applications

Un jeton d'un autre type de propriétaire est chargé par le fournisseur d'utilisateurs que `owner_types` nomme pour ce
type, au lieu de celui du pare-feu : le jeton de sécurité contient alors l'objet utilisateur de l'organisation ou de
l'application, avec ses rôles. Un jeton d'un type qu'aucun fournisseur ne charge — un type retiré de la configuration —
n'ouvre rien.

```yaml
security:
    providers:
        members: { entity: { class: App\Entity\Member, property: email } }
        organizations: { entity: { class: App\Entity\Organization, property: slug } }

phil_api_keys:
    owner_types:
        organization:
            provider: security.user.provider.concrete.organizations
            scopes: [projects:read, billing]
```

Un contrôleur les distingue par le type de propriétaire que porte la requête, ou par la classe de l'utilisateur.

## Ce que porte une requête authentifiée par un jeton

| Où | Quoi |
|---|---|
| Rôles | `ROLE_API_SCOPE_` suivi de chaque portée en majuscules, chaque caractère autre qu'une lettre ou un chiffre remplacé par un trait de soulignement : `projects:write` donne `ROLE_API_SCOPE_PROJECTS_WRITE`; et les rôles du propriétaire, à moins de `grant_user_roles: false` |
| Attributs du jeton de sécurité | `_api_token_id`, l'identifiant du jeton, `_api_token_owner_type`, le type de son propriétaire, et `oauth2_scope`, ses portées, l'attribut où l'authentificateur de Symfony garde les portées |
| Attributs de la requête | `_api_token_id`, `_api_token_owner_type` et `_api_token_scopes` |

`TokenAuthenticationListener` ajoute les rôles et l'identifiant, sur les `AuthenticationTokenCreatedEvent` et
`LoginSuccessEvent` de Symfony; il laisse telle quelle toute authentification que le gestionnaire du bundle n'a pas
faite.

Avec `grant_user_roles: false`, un jeton n'a que les rôles de ses portées : le jeton d'un administrateur n'est pas un
administrateur.

## Exiger une portée

```php
use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;

#[RequiresScope('projects:read')]
final class ProjectController
{
    #[RequiresScope('projects:write')]
    public function update(): Response
    {
        // ...
    }
}
```

`#[RequiresScope]` se place sur une classe ou une action et peut se répéter; chaque portée nommée est exigée.
L'écouteur les vérifie avant l'exécution du contrôleur : une requête sur laquelle personne n'est authentifié reçoit la
réponse `401`, même sur une route que `access_control` laisse ouverte; sinon, chaque portée passe par le *voter*. Sans
la sécurité de Symfony, personne ne peut être authentifié, et un tel contrôleur reçoit la réponse `401`.

Ailleurs, le *voter* répond à `API_SCOPE` sur un nom de portée :

```php
use Phil\ApiKeysBundle\Infrastructure\Security\ScopeVoter;

$this->denyAccessUnlessGranted(ScopeVoter::ATTRIBUTE, 'projects:write');
```

```twig
{% if is_granted('API_SCOPE', 'billing') %}…{% endif %}
```

Une requête authentifiée autrement que par un jeton personnel (une session, un jeton d'accès d'un autre gestionnaire)
est refusée, à moins que `other_authentications: true` laisse passer tout utilisateur connecté. Une requête sans
personne de connecté est toujours refusée.

Comme les portées sont dans `oauth2_scope`, l'attribut `OAUTH2_SCOPE(projects:write)` de Symfony fonctionne aussi, avec
`#[IsGranted]`; son refus reçoit du pare-feu la réponse avec le défi `insufficient_scope`.

## Les réponses

| Quand | Statut | `WWW-Authenticate` |
|---|---|---|
| Aucun jeton sur une route qui exige un utilisateur | `401` | `Bearer realm="api"` |
| Un jeton refusé : mal formé, inconnu, révoqué, expiré | `401` | `Bearer realm="api",error="invalid_token",error_description="Invalid credentials."` |
| Aucune authentification sur un contrôleur `#[RequiresScope]` | `401` | `Bearer realm="api"` |
| Une portée manquante | `403` | `Bearer realm="api",error="insufficient_scope",scope="projects:write"` |

Le `error_description` est le message de Symfony pour `BadCredentialsException`, traduit dans la langue de la requête;
il est le même pour chaque refus. Le corps d'un `401` du pare-feu est ce texte simple. Avec `phil/api-contract-bundle`
installé, le service `phil_api_keys.problem_responder` y répond plutôt par des détails de problème RFC 9457, nommé comme
point d'entrée du pare-feu et comme gestionnaire d'échec de l'authentificateur :

```yaml
security:
    firewalls:
        api:
            # ...
            entry_point: phil_api_keys.problem_responder
            access_token:
                # ...
                failure_handler: phil_api_keys.problem_responder
```

Son défi d'un jeton refusé est `Bearer realm="api",error="invalid_token"`. Le `401` et le `403` de `#[RequiresScope]`
sont une `UnauthorizedHttpException` et une `AccessDeniedHttpException`, auxquelles répond la gestion d'erreurs de
l'application : des détails de problème sous les chemins de `phil/api-contract-bundle`. Aucune réponse ne dit pourquoi
un jeton a été refusé.
