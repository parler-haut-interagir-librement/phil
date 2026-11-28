# Configuration

The bundle's configuration lives under `phil_email_templates`:

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    default_locale: en
    site: main                       # the site this application is, when phil/tenancy-bundle does not say it
    defaults_paths: ['%kernel.project_dir%/email_templates']
    from: 'Shop <shop@example.com>'
    brand: { name: 'Shop', logo: 'https://example.com/logo.png' }
    theme:
        light: { primary: '#0f766e' }
        dark: { primary: '#5eead4' }
        font: "Georgia, 'Times New Roman', serif"
    embeds: { directory: '%kernel.project_dir%/email_templates/images', max_bytes: 1048576 }
    sandbox: { filters: [], functions: [], cache: true }
    admin: { enabled: true, role: ROLE_EDITOR, layout: 'admin/email_layout.html.twig' }
    messenger: { delivery_resolver: App\Mail\Deliveries }
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
| `store` | `content` | Where the templates are kept: `content`, as contents of `phil/content-bundle`, which needs DoctrineBundle and PhilContentBundle; `memory` for tests; or the identifier of a `TemplateStore` service. |
| `entity_manager` | `doctrine.orm.entity_manager` | The service of the entity manager of the contents: the one `phil_content.entity_manager` names. |
| `clock` | `clock` | The PSR-20 clock that dates the saves of the drafts, and the publications of the `memory` store; `phil/content-bundle` dates its publications with the container's `clock`. |
| `default_locale` | `en` | The locale rendered when none is given, and the last one tried. With the `content` store, every locale a site writes templates in is one `phil_content.locales` serves. |
| `site` | `null` | The site the application is, when the tenancy does not say it; `null` for the shared scope. |
| `site_scope` | `auto` | `tenancy`, the active tenant of `phil/tenancy-bundle`; `fixed`, the `site` option; `auto`, by tenant when PhilTenancyBundle is enabled and `site` is not set. |
| `defaults_paths` | `['%kernel.project_dir%/email_templates']` | The directories of the application's default files, searched before the bundle's own. |
| `layout` | `@PhilEmailTemplates/email/layout.html.twig` | The template of the house layout, in the application's Twig. |
| `brand.name`, `brand.logo` | `null` | What the default layout shows at the top: a name, or the `https` address of a logo 40 pixels high. |
| `theme.light`, `theme.dark` | the bundle's colours | The colours replacing those of each scheme: `primary`, `primary_text`, `text`, `muted`, `background`, `surface`, `panel`, `border`, each `#rgb` or `#rrggbb`. |
| `theme.font` | a system font stack | The font of the layout and the components: letters, digits, spaces, hyphens, commas and quotes. |
| `embeds.directory` | `null` | The directory of the images the templates embed; without it, a template that embeds one does not render. |
| `embeds.max_bytes` | `1048576` | The largest image, in bytes. |
| `from` | `null` | The sender of an email composed without one: `shop@example.com` or `Shop <shop@example.com>`. |
| `max_html_bytes` | `1048576` | The largest email rendered, in bytes of HTML; a body larger than it is refused too. |
| `sandbox.filters`, `sandbox.functions` | `[]` | The filters and functions of the Twig extensions tagged `phil_email_templates.sandbox_extension` the sandbox allows. |
| `sandbox.cache` | `true` | Whether the compiled templates are cached under `%kernel.cache_dir%/phil_email_templates/twig`. |
| `inline_css` | `auto` | Whether the styles are inlined: `auto` when `twig/cssinliner-extra` is installed, `true` or `false`. |
| `admin.enabled` | `false` | Registers the administration and its preview. |
| `admin.role` | `ROLE_ADMIN` | The role every page of the administration asks. |
| `admin.layout` | `@PhilEmailTemplates/admin/layout.html.twig` | The template the pages extend, with the blocks `title` and `content`. |
| `audit.enabled`, `audit.segment` | `true`, `email_templates` | Whether each change is recorded in the trail of `phil/audit-bundle`, when PhilAuditBundle is enabled, and in which segment. |
| `messenger.delivery_resolver` | `null` | The `DeliveryResolver` service that handles `SendTemplatedEmail`; no handler without it. |
| `preview` | `null` | A `PreviewRenderer` service replacing the preview of the bundle. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are, under the components directory; the subdirectory of `ux_toolkit` when `null`. |

The bundle adds its CSRF token, `phil_email_templates_admin`, to `framework.csrf_protection.stateless_token_ids`.

## In tests

```yaml
# config/packages/test/phil_email_templates.yaml
phil_email_templates:
    store: memory
    sandbox: { cache: false }
```
