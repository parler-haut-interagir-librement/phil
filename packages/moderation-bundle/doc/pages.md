# Pages

## The back office

The pages are for moderators: a member who is no moderator meets the firewall, as with any denied page. Every form
is posted with a CSRF token, and every page is `no-store`.

| Page | Route | What it does |
|---|---|---|
| The queue | `phil_moderation_queue` | The single queue, a page at a time; with `phil/incident-mode-bundle`, the link to its page and the proposal of an incident when reports surge ([Incident mode](incident-mode.md)) |
| A target | `phil_moderation_entry`, `phil_moderation_decide` | What its package says of it, its open reports, the decision form with the grounds of a statement of reasons, its history |
| A member | `phil_moderation_member`, `phil_moderation_note`, `phil_moderation_note_remove` | The decisions on their content and the notes of the moderation on them |

The pages extend the layout of `admin.layout`, which defines the blocks `title` and `main`.

## The statements of reasons

The member a statement of reasons is addressed to reads it on `phil_moderation_statement`, and all of theirs on
`phil_moderation_statements` ([Statements of reasons](statements-of-reasons.md#where-the-member-reads-it)).

## Notes on a member

The notes of the moderation on a member are written and read by moderators only. The member never sees them; they are
distinct from the private notes a member keeps for themselves, which belong to another package. A note has up to
5,000 characters.

## In an EasyAdmin back office

When EasyAdminBundle is registered, the bundle grafts the same controller on every dashboard through `#[AdminRoute]`:
the same pages, roles and forms, through the same services, rendered in the dashboard's layout, under routes named
after the dashboard's — `admin_moderation_queue` under a dashboard whose route is `admin`. The dashboard's menu links
to them:

```php
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;

public function configureMenuItems(): iterable
{
    yield ModerationAdmin::menuItem('admin');
}
```

## The report form

A page includes the report form of a content:

```twig
{{ include('@PhilModeration/_report_form.html.twig', {type: 'forum.post', id: post.id}) }}
```

The member picks a reason and may add a comment; a trusted member also sees the button that hides the content. Each
form goes back to the current page — a path of the same site, never another host.

The templates of the site also have `phil_moderation_reasons()`, `phil_moderation_report_token()`,
`phil_moderation_may_hide()` and `phil_moderation_kit('Button')`; whether an incident suspends something now is
`phil_incident_suspended()`, of `phil/incident-mode-bundle` ([Incident mode](incident-mode.md#in-the-pages-of-the-site)).

## The public journal

`phil_moderation_journal` lists the public decisions, the latest first: the date, the action, the type of content,
the role and the public reason. **It never shows the moderator, the author or the note.** It is the same for everyone,
so a shared cache keeps it a minute.

## The recipes of the kit

| Template | Recipes |
|---|---|
| `admin/queue.html.twig` | `Alert`, `Badge`, `Table` and its parts |
| `admin/entry.html.twig` | `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Textarea` |
| `admin/member.html.twig` | `Alert`, `Badge`, `Button`, `Field`, `Textarea` |
| `journal.html.twig` | `Badge`, `Table` and its parts |
| `statements.html.twig` | `Badge`, `Table` and its parts |
| `statement.html.twig` | `Alert`, `Badge` |
| `_report_form.html.twig` | `Button`, `Field`, `NativeSelect`, `Textarea` |

The labels are translated in the domain `phil_moderation`; the bundle has English and French. A role, a reason or a
target type of the application without a translation shows its code.
