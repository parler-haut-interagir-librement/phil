<!-- phil-dev source-sha256=45d16c875e515c2f188d8eb4abd88fa31be4f617c1a923c091cd891c5f03deae -->
# La page des membres

Une page où un membre connecté liste ses jetons, en crée un (son texte montré une fois, dans la réponse au formulaire)
et en révoque un ; et la même page pour les jetons d'un autre propriétaire, une organisation ou une application, pour les
membres que l'application autorise.

## L'activer

```sh
composer require symfony/form symfony/security-csrf symfony/twig-bundle
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    member_page: true
```

```yaml
# config/routes/phil_api_keys.yaml
phil_api_keys:
    resource: '@PhilApiKeysBundle/config/routes.php'
    prefix: /account/api-tokens
```

| Route | Chemin | Ce qu'elle fait |
|---|---|---|
| `phil_api_keys` | `/` (GET, POST) | La liste et le formulaire de création |
| `phil_api_keys_revoke` | `/{id}/revoke` (POST) | Révoque l'un des jetons du membre, puis redirige vers la liste avec `?revoked=1` |
| `phil_api_keys_owner` | `/owners/{ownerType}/{owner}` (GET, POST) | La liste et le formulaire de création des jetons d'un autre propriétaire |
| `phil_api_keys_owner_revoke` | `/owners/{ownerType}/{owner}/{id}/revoke` (POST) | Révoque l'un des jetons de ce propriétaire, puis redirige vers sa liste avec `?revoked=1` |

Les routes se trouvent derrière le pare-feu des membres connectés, pas derrière le pare-feu sans état de l'API : la
page est atteinte par une session. Sans utilisateur, le contrôleur lève `AccessDeniedException` et le pare-feu demande
au membre de se connecter, par son point d'entrée; un pare-feu dont la connexion est une page de l'application, sans
authentificateur propre, nomme cette page dans le `entry_point_path` de Symfony :

```yaml
security:
    firewalls:
        main:
            lazy: true
            entry_point_path: /login
```

Le propriétaire d'un jeton créé sur la page du membre est le membre, par son identifiant d'utilisateur.

## Les jetons d'un autre propriétaire

`/owners/organization/acme` montre les jetons de l'organisation `acme`, les crée et les révoque, pour les membres à qui
les *voters* de l'application accordent `TokenPageController::MANAGE` (`PHIL_API_KEYS_MANAGE`) sur ce `TokenOwner`. Sans
*voter* de ce genre, personne : la page répond `403`. Un type que `owner_types` ne déclare pas, ou un propriétaire mal
formé, reçoit `404`. Le formulaire n'offre que les portées que ce type peut détenir.

```php
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Http\TokenPageController;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, TokenOwner> */
final class OrganizationTokensVoter extends Voter
{
    public function __construct(private Memberships $memberships) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return TokenPageController::MANAGE === $attribute && $subject instanceof TokenOwner && 'organization' === $subject->type;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->memberships->isAdministrator($token->getUserIdentifier(), $subject->id);
    }
}
```

## Ce qui la protège

- Le formulaire de création porte un jeton CSRF, d'identifiant `phil_api_keys_create`. Chaque formulaire de révocation
  porte le sien, d'identifiant `phil_api_keys_revoke_<token id>`, vérifié avant toute révocation.
- Chaque réponse porte `Cache-Control: no-store`, pour que la page qui montre un nouveau jeton ne soit gardée ni par un
  cache ni par l'historique du navigateur.
- Le jeton d'un autre membre est introuvable : `404`, jamais `403` ; de même pour le jeton d'un autre propriétaire, ou du
  même identifiant sous un autre type.
- Le nouveau jeton est rendu dans la réponse au formulaire, pas après une redirection : il ne se trouve jamais dans une
  URL, une session ou un message flash.

## Le formulaire

`CreateTokenType` demande un nom (de 1 à 100 caractères), les portées (des cases à cocher étiquetées par les libellés
traduits des portées, avec leurs descriptions comme `title`) et une durée de vie parmi `lifetime_choices`, avec
`default_lifetime` choisi d'avance. Ses données sont un tableau de `name`, `scopes` et `lifetime`, en jours.

## Les gabarits

| Gabarit | Ce qu'il rend |
|---|---|
| `@PhilApiKeys/tokens.html.twig` | Une page entière, autonome |
| `@PhilApiKeys/_tokens.html.twig` | Le nouveau jeton, la liste avec un bouton de révocation par jeton actif, et le formulaire |

Une application rend sa propre page, qui étend sa mise en page, et y inclut le gabarit partiel :

```twig
{# templates/account/api_tokens.html.twig #}
{% extends 'account/layout.html.twig' %}

{% block content %}
    <h1>{{ 'api_keys.title'|trans({}, 'phil_api_keys') }}</h1>
    {% include '@PhilApiKeys/_tokens.html.twig' %}
{% endblock %}
```

```yaml
phil_api_keys:
    member_page:
        template: account/api_tokens.html.twig
```

Le gabarit reçoit `tokens`, `owner` (le `TokenOwner` dont ce sont les jetons), `typed_owner` (si la page est celle d'un
autre propriétaire plutôt que celle du membre), `form`, `issued` (l'`IssuedToken` qui vient d'être créé, ou null), `revoked`, `now`,
`scope_labels` (la clé de libellé de chaque portée) et `scope_domain`. Le HTML est sans classe (tableaux, formulaires,
boutons, `<time>`), avec les classes `isStack`, `isNarrow`, `isMuted` et `isVisuallyHidden` de phil-ux. Les messages
d'état portent `role="status"`, et chaque bouton de révocation nomme son jeton pour les lecteurs d'écran.

## Les traductions

Le domaine `phil_api_keys` est fourni en anglais et en français. Les libellés des portées sont des clés de traduction
de `scope_translation_domain`, `phil_api_keys` par défaut : une application les ajoute à son propre
`translations/phil_api_keys.<locale>.yaml` :

```yaml
# translations/phil_api_keys.fr.yaml
scope.projects_read: Lire les projets
scope.projects_write: Créer et modifier les projets
```
