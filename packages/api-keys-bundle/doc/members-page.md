# The members' page

A page where a signed-in member lists their tokens, creates one — its text shown once, on the answer to the form —
and revokes one; and the same page for the tokens of another owner, an organisation or an application, for the members
the application allows.

## Enabling it

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

| Route | Path | What it does |
|---|---|---|
| `phil_api_keys` | `/` (GET, POST) | The list and the creation form |
| `phil_api_keys_revoke` | `/{id}/revoke` (POST) | Revokes one of the member's tokens, then redirects to the list with `?revoked=1` |
| `phil_api_keys_owner` | `/owners/{ownerType}/{owner}` (GET, POST) | The list and the creation form of another owner's tokens |
| `phil_api_keys_owner_revoke` | `/owners/{ownerType}/{owner}/{id}/revoke` (POST) | Revokes one of that owner's tokens, then redirects to its list with `?revoked=1` |

The routes sit behind the firewall of signed-in members, not the stateless API firewall: the page is reached by a
session. Without a user, the controller throws `AccessDeniedException` and the firewall asks the member to sign in,
through its entry point; a firewall whose sign-in is a page of the application, without an authenticator of its own,
names that page in Symfony's `entry_point_path`:

```yaml
security:
    firewalls:
        main:
            lazy: true
            entry_point_path: /login
```

The owner of a token created on the member's own page is the member, by their user identifier.

## Another owner's tokens

`/owners/organization/acme` shows the tokens of the organisation `acme`, and creates and revokes them, to the members
the application's voters grant `TokenPageController::MANAGE` (`PHIL_API_KEYS_MANAGE`) on that `TokenOwner`. Without
such a voter, nobody: the page answers `403`. A type `owner_types` does not declare, or an owner out of shape, answers
`404`. The form offers only the scopes that type may hold.

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

## What protects it

- The creation form carries a CSRF token, id `phil_api_keys_create`; each revocation form carries its own, id
  `phil_api_keys_revoke_<token id>`, checked before anything is revoked.
- Every answer is `Cache-Control: no-store`, so that the page with a new token is not kept by a cache or the browser's
  history.
- Another member's token is not found: `404`, never `403`; so is the token of another owner, or of the same
  identifier under another type.
- The new token is rendered in the answer to the form, not after a redirection: it is never in a URL, a session or a
  flash message.

## The form

`CreateTokenType` asks for a name (1 to 100 characters), the scopes — checkboxes labelled by the scopes' translated
labels, their descriptions as `title` — and a lifetime among `lifetime_choices`, `default_lifetime` chosen first.
Its data is an array of `name`, `scopes` and `lifetime`, in days.

## The templates

| Template | What it renders |
|---|---|
| `@PhilApiKeys/tokens.html.twig` | A whole page, standing alone |
| `@PhilApiKeys/_tokens.html.twig` | The new token, the list with a revoke button per active token, and the form |

An application renders its own page, extending its layout, and includes the partial:

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

The template is given `tokens`, `owner` (the `TokenOwner` whose tokens they are), `typed_owner` (whether the page is
another owner's rather than the member's own), `form`, `issued` (the `IssuedToken` just created, or null), `revoked`,
`now`, `scope_labels` (the label key of each scope) and `scope_domain`. The HTML is classless — tables, forms, buttons,
`<time>` — with the `isStack`, `isNarrow`, `isMuted` and `isVisuallyHidden` classes of phil-ux; the status messages
have `role="status"`, and each revoke button names its token for screen readers.

## The translations

The domain `phil_api_keys` is given in English and French. The scope labels are translation keys of
`scope_translation_domain`, `phil_api_keys` by default: an application adds them to its own
`translations/phil_api_keys.<locale>.yaml`:

```yaml
# translations/phil_api_keys.fr.yaml
scope.projects_read: Lire les projets
scope.projects_write: Créer et modifier les projets
```
