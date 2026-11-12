# Administration and working copies

The administration edits and publishes the contents from a back office. Its pages go through the bundle's application
services — `ContentManager`, `WorkingCopies`, `PublicationSchedule` —, so they validate, refuse, dispatch the page
events and write exactly as the console and PHP do. An EasyAdmin dashboard links to them.

## Enabling it

```yaml
# config/packages/phil_content.yaml
phil_content:
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/content_layout.html.twig   # a template of the back office, with "title" and "content" blocks
        timezone: America/Toronto

# config/routes/phil_content_admin.yaml
phil_content_admin:
    resource: '@PhilContentBundle/config/routes_admin.php'
    prefix: /admin/contents
```

The routes go behind the application's firewall; every page asks `admin.role` again. The administration needs
SecurityBundle with the CSRF protection (`framework.csrf_protection`), and `symfony/translation` for its labels, in the
`phil_content` domain, in English and in French.

In the EasyAdmin dashboard:

```php
use Phil\ContentBundle\Infrastructure\EasyAdmin\ContentAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield from ContentAdmin::menuItems();
}
```

`ContentAdmin::menuItems()` needs `easycorp/easyadmin-bundle`; the pages themselves do not.

## The pages

| Route | Path under the prefix | Does |
|---|---|---|
| `phil_content_admin_list` | `/` | Each kind of `phil_content.roots`, its contents and, per locale, the place of the draft, its version and the version readers are served |
| `phil_content_admin_create` | `/new/{resourceKey}` | Creates a content of the kind in a locale, with its slug, then opens its editing page |
| `phil_content_admin_edit` | `/{reference}/{locale}` | The editing page of one content in one locale |
| `phil_content_admin_autosave` | `/{reference}/{locale}/autosave` | Keeps the editing form as the author's autosave; answers 204, or 422 with the reason |

The editing page shows the fields the draft's capabilities give — template, slug, title, summary, SEO title,
description and canonical URL, the text's format and the text, and the three SEO flags — and its buttons:

| Button | Does |
|---|---|
| Save the draft | `ContentManager::edit()`; the author's autosave is discarded |
| Publish | saves, then `ContentManager::publish()`. A body the conversion into UJO does not keep whole is refused: the page lists what would be lost, and publishes once « publish anyway » is ticked |
| Take offline | `ContentManager::unpublish()` |
| Discard the changes | `ContentManager::removeDraft()` |
| Schedule, cancel what is scheduled | `PublicationSchedule::schedule()` and `cancel()`, at a moment of `admin.timezone`; shown only when a schedule is installed |
| Save as a named draft, use it, delete | `WorkingCopies::saveNamed()`, `applyNamed()`, `deleteNamed()` |
| Restore them, discard them | `WorkingCopies::applyAutosave()` and `discardAutosave()` on the author's autosave |

- **A refusal shows its reason** and keeps what was typed, answered 422: a slug taken, a value refused, a publication
  that loses content.
- **Every form carries a CSRF token** of the id `phil_content_admin`; a form without it is answered 400.
- **Every page is answered `no-store`.**
- **The author** is the user identifier of the security token; a request without a signed-in user is refused.

## Working copies

A working copy is submitted data kept beside the draft of a locale, written into it only when an editor applies it. It
is stored in the table `phil_content_working_copy` ([storage.md](storage.md#working-copies)).

| Kind | Belongs to | Kept |
|---|---|---|
| Autosave | one author, read by nobody else | as the author types, replacing their previous one; removed once applied, or when the author saves or publishes the draft |
| Named draft | the content, read by every editor | under a name of 1 to 100 characters, replaced when saved again under the same name; stays once applied |

`Application\Editing\WorkingCopies`:

| Method | Does |
|---|---|
| `autosave(ContentReference, $locale, string $author, array $data): WorkingCopy` | keeps the author's autosave |
| `autosaved(ContentReference, $locale, string $author): ?WorkingCopy` | the author's autosave |
| `discardAutosave(ContentReference, $locale, string $author)` | removes it, if any |
| `applyAutosave(ContentReference, $locale, string $author)` | writes it into the draft through `ContentManager::edit()`, then removes it |
| `saveNamed(ContentReference, $locale, string $name, string $author, array $data): WorkingCopy` | keeps a named draft |
| `named(ContentReference, $locale): list<WorkingCopy>` | the named drafts, by name |
| `applyNamed(ContentReference, $locale, string $name)` | writes a named draft into the draft |
| `deleteNamed(ContentReference, $locale, string $name)` | removes it |

- The data is the submitted data of the pipeline ([pipeline.md](pipeline.md#submitted-data)), 256 KiB of JSON at most;
  it is validated when applied, as any edit.
- A content that does not exist, or a locale not served, is refused before anything is kept. A missing autosave or named
  draft is a `ContentNotFound`.
- `ContentManager::delete()` removes the working copies of the content with it.

## The autosave controller

The editing form carries the Stimulus controller `phil--content-bundle--autosave`, served from `assets/` by the
Symfony UX conventions (AssetMapper or Webpack Encore). Once the typing stops for `delay` milliseconds — 2000 by
default —, it posts the form, its fields and its CSRF token, to the autosave route; one save runs at a time, and a change
made meanwhile is saved after it. The form's `data-autosave-state` attribute says `saving`, `saved` or `failed`, for a
style or a live region. Nothing is kept in the browser.
