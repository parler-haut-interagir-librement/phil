# Administration

With `admin.enabled`, the routes of `config/routes/admin.php`:

| Route | Page |
|---|---|
| `phil_terms_admin_list` | The policies, whether each is required and renewed, and their versions with their moments, material flag and locales |
| `phil_terms_admin_publish` | The form that publishes the next version of a policy |

The form asks for the text in each of `locales` — a locale left empty is left out —, the format, the moment it takes
effect in `timezone` — empty or past, it takes effect when published — and whether it is material, unticked by default.
A published version is never changed: a correction is the next version.

The moments are kept in UTC; the list shows them in `timezone`, which its tables name in their caption.

It needs Twig, `security.authorization_checker` and `security.csrf.token_manager`; the translator gives its words in
the user's language.

## The recipes of the kit

The pages draw their parts with the recipes of the kit, from the PHIL UX kit or the Shadcn kit
([Installation](installation.md#install-the-recipes-of-the-kit)): an `Alert` for a version published or a refusal, a
`Badge` for whether a policy is required, a `Button` that leads to the form, a `Table` of the versions, and, on the
form, a `Field` with its `Field:Label` around each control — `NativeSelect` for the format, `Textarea` for each text,
`Input` for the moment and the licence, `Checkbox` for the material flag — and its `Field:Description`. Only the props
both kits share are passed, and no template writes a word of its own.

## Role and token

Every page checks `admin.role`; a user without it meets the firewall, as on any denied page. The form carries a CSRF
token of id `phil_terms`; a form without a valid one is refused with a `400`. A refusal — no text, a document that is
not UJO, a moment that does not read, a number taken meanwhile — is shown on the form with a `422`, the draft kept.

## Layout

The pages extend `admin.layout`, a template that defines the `title` and `content` blocks. The default is a bare HTML
page that asks robots not to index it; an application points it to a template of its back office.

## EasyAdmin

```php
use Phil\TermsBundle\Infrastructure\EasyAdmin\TermsAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Legal');
    yield from TermsAdmin::menuItems();
}
```
