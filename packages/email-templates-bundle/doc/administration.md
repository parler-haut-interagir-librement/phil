# Administration and preview

## Enabling it

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/email_layout.html.twig   # a template of the back office, with "title" and "content" blocks

# config/routes/phil_email_templates_admin.yaml
phil_email_templates_admin:
    resource: '@PhilEmailTemplatesBundle/config/routes_admin.php'
    prefix: /admin/email-templates
```

The routes go behind the application's firewall; every page asks `admin.role` again. The administration needs
SecurityBundle, FrameworkBundle's CSRF protection — the container stops with a message when it is off —, Symfony UX
Twig Component with [the recipes of the kit](installation.md#the-recipes-of-the-kit), and `symfony/translation` for its
labels, in the `phil_email_templates` domain, in English and in French.

## The pages

| Route | Path under the prefix | Does |
|---|---|---|
| `phil_email_templates_admin_index` | `/` | Every template the site sends or keeps a draft of, by key and locale: where what it sends comes from — default, shared, the site's, or another locale —, the version sent, and where the site's draft stands |
| `phil_email_templates_admin_edit` | `/{key}/{locale}` | The page of a template: the form of its draft, its preview, its published versions; `POST` saves, publishes, reverts, discards or resets |
| `phil_email_templates_admin_frame` | `/{key}/{locale}/frame` | One variant rendered with its sample values, as the site sends it, as its draft with `?draft=1`, or at a published version with `?version=2`; `?segments=vip,new` |

A page acts for the current site; `?scope=shared` acts for the shared scope, and the list links from one to the other.

The page of a template shows where what the site sends comes from and its version, where the site's draft stands — not
published, changes not published, or published — with its version and revision, its other locales, and a form of the
draft's fields, or of what the site sends while it keeps no draft — subject, preheader, format, body, plain-text part,
segments separated by commas, the variable schema and the embedded images as JSON. **Save the draft** saves it;
**Publish** sends it, once a draft holds something not yet sent; **Abandon the changes** puts the published version back
into the draft; **Take offline** sends the shared or the default template again, keeping the draft. Each published
version but the one sent can be copied into the draft. A refusal is answered 422 with every reason in an alert, what was
typed kept; a revision saved meanwhile is answered 409; publishing without a draft is answered 404; a change done
redirects to the page with its message.

## The preview

The preview draws each variant of the template — no segment, each segment, all of them — with its subject, its
preheader, a warning when Gmail would clip it, its plain-text part, and the email in an `<iframe sandbox="">`: no script,
no form, no navigation of the page, and an origin of its own. The frame itself is served with:

```http
Content-Security-Policy: default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
Cache-Control: no-store
```

The embedded images are shown as `data:` addresses. A variant that does not render shows why, answered 422.

The preview goes through the `PreviewRenderer` port: `SamplePreviews` renders with the sample values of the schema;
`preview` names another service, as a dedicated preview package or data of the application's own.

## Security of the forms

Every form carries the CSRF token `phil_email_templates_admin`, which the bundle declares stateless: Symfony checks the
origin of the request, or a double-submitted cookie, and no session is needed. A form without it, or posted from another
origin, is answered 400. Every response is `no-store` and `noindex`.

## The kit

The pages draw with the recipes `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table` and `Textarea`,
named through `phil_email_templates_kit()` after the components subdirectory, and give them only what the PHIL UX kit and
the Shadcn kit read the same way. The message of a change done is a polite live region, `role="status"`, around the
alert; a refusal is the alert itself.
