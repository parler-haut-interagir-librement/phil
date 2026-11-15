# Administration

With `admin.enabled`, the bundle serves an administration page that publishes the next version of the statement. Import
its routes under the firewall of the application's administration:

```yaml
# config/packages/phil_accessibility_statement.yaml
phil_accessibility_statement:
    source: doctrine
    admin: { enabled: true, role: ROLE_ADMIN }

# config/routes/phil_accessibility_statement_admin.yaml
phil_accessibility_statement_admin:
    resource: '@PhilAccessibilityStatementBundle/config/admin_routes.php'
    prefix: /admin
```

| Route | Path | Method |
|---|---|---|
| `phil_accessibility_statement_admin` | `/accessibility-statement` | `GET`, `HEAD` |
| `phil_accessibility_statement_admin_publish` | `/accessibility-statement/publish` | `POST` |

## Who opens it

The page asks Symfony Security whether the person is granted `admin.role`, `ROLE_ADMIN` by default: a visitor is asked
to sign in by the firewall, a person without the role is refused. The form carries a CSRF token of Symfony's
protection, `phil_accessibility_statement_publish`. Enabling the page without `symfony/security-core` and
`symfony/security-csrf` fails the build; a container without the authorization checker or the token manager refuses
the page rather than opens it. Every answer is `Cache-Control: no-store`.

## What it shows

1. The versions published, newest first, the version in force marked.
2. The next version, as a document in JSON in a text area: the version the latest audit gives when `audit` is
   enabled and a report is there, otherwise the version in force with the next number and today's date.
3. A button that publishes the document.

The document reads as the configuration's statement does, with the keys of `StatementReader` of
`phil/accessibility-statement`. It is published only when it reads, is at most 1 MiB, and carries the next number: a
version is never changed once published. A document refused comes back in the text area with the reason, `422`; a
version published redirects to the page with `?published={n}`, which says so.

With the `config` source, versions are written in the configuration: the page shows the next version to copy there
and no form.

## The templates

`admin.html.twig` extends the layout of the `layout` option and draws its parts with the recipes `Alert`, `Field`,
`Textarea`, `Button`, `Table` and `Badge` of the kit ([The page](page.md#the-recipes-of-the-kit)). It receives
`latest`, `versions`, `writable`, `draft`, `error`, `published`, `csrf_token` and `layout`, and is replaced like the
other templates of the bundle.
