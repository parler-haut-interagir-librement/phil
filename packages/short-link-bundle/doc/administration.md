# Administration

The administration lists the links, creates, edits, enables, disables and removes them, and draws their QR codes.

| Route | Path under the prefix | What it does |
|---|---|---|
| `phil_short_link_admin_list` | `/` | The links, newest first, 50 a page, with a search on the code, the label and the target; each with its visits of people over its maximum, and its visits of robots apart |
| `phil_short_link_admin_create` | `/new` | The form of a new link |
| `phil_short_link_admin_edit` | `/{id}/edit` | The form of a link, its address and its QR code |
| `phil_short_link_admin_toggle` | `/{id}/toggle` | Enables or disables a link (`POST`) |
| `phil_short_link_admin_delete` | `/{id}/delete` | Removes a link (`POST`) |
| `phil_short_link_admin_qr_code` | `/{id}/qr-code.svg`, `/{id}/qr-code.png` | The QR code of a link's address |

## The visits

The list shows two counts for each link. **Visits** are the visits of people, which the maximum counts, written
`12 / 500` when the link has one. **Robots** are the visits of machines — crawlers, scripts, monitors, prefetches, link
previews of messaging services —, redirected but never counted towards the maximum. A link posted in a busy chat often
has more robots than visits in its first minutes. See [Resolution](resolution.md#people-and-machines).

## The role and the tokens

Every page needs `admin.role`, `ROLE_ADMIN` by default: a user without it meets the firewall, as on any denied page.
The buttons post a CSRF token; the form carries the token of Symfony's forms when they have one.

## The form

`Infrastructure\Form\ShortLinkType` edits a `ShortLinkDraft`: the target, the code — left empty to draw one, and
shown but not editable once the link exists —, a label, the locale when some domain has one, the redirect status, whether
the query string of the visit is kept, whether the link is enabled, its start and end, written in `admin.timezone`, and
its maximum number of visits. `ShortLinks` checks the draft when it is saved; its refusals are shown on the field they
concern. The form type is registered for the application's own forms too.

## The pages

The pages extend `admin.layout`, whose `content` block holds them. They use the class names of the PHIL UX kit —
`isTable`, `isField`, `isButton`, `isAlert`, `isPagination`… —, which its stylesheet draws, without requiring the kit's
components. Their words are the messages of the `phil_short_link` domain, in English and French. The templates under
`@PhilShortLink/admin/` can be overridden in `templates/bundles/PhilShortLinkBundle/admin/`.

## QR codes

The QR code holds the link's address on the domain of its locale, drawn by phil/qr-code with the options of `qr_code`:
an SVG named for screen readers by the address, or a PNG to print. `Application\ShortLinkQrCodes` draws them from
code too.
