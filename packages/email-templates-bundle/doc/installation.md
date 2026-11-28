# Installation

```sh
composer require phil/email-templates-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_email_templates.yaml` and the routes
of the administration, and creates `email_templates/`, the directory of the application's default files. Without it,
register the bundle in `config/bundles.php`:

```php
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later, Symfony 8.2 or later and Twig 3.31 or later, with the `dom` and `mbstring`
extensions. FrameworkBundle gives it the `clock` service; TwigBundle renders the house layout, which the application
then overrides as any template of a bundle.

## The contents

The templates the sites write are contents of `phil/content-bundle`, which the package requires. Register DoctrineBundle
and PhilContentBundle beside it, and declare under `phil_content.locales` every locale the sites write templates in:

```php
// config/bundles.php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales:
        en: []
        fr: []
        fr_CA: [fr]
```

When the kernel has both bundles, the package adds the mapping of its two entities to the `doctrine` configuration and
their root, `EmailTemplateContent`, to `phil_content.roots`; nothing else is configured. The tables —
`phil_email_template_content` and `phil_email_template_dimension`, beside those of `phil/content-bundle` — come with the
next migration:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

An entity manager other than the default one is named in `entity_manager`, the same `phil_content.entity_manager`
names. An application without a database sets `store: memory`, or the service of a `TemplateStore` of its own, and needs
neither bundle. The contents are described in [Sites, drafts and versions](sites-and-versions.md#the-contents).

## Templates kept in a table of versions

An application whose templates are in a table of numbered versions — one row per site, key, locale and version, as the
columns `site`, `template_key`, `locale`, `version`, `subject`, `preheader`, `format`, `body`, `text_part`,
`variables`, `segments`, `embeds`, `author` and `saved_at` of a table `phil_email_template` — copies them into the
contents once, then drops the table. Each version is saved then published in order, so the published versions keep their
order and the latest is sent:

```php
use Doctrine\DBAL\Connection;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;

final readonly class CopyTheTemplates
{
    public function __construct(private Connection $connection, private EmailTemplates $templates)
    {
    }

    public function __invoke(): void
    {
        $rows = $this->connection->iterateAssociative(
            'SELECT * FROM phil_email_template ORDER BY site, template_key, locale, version',
        );

        foreach ($rows as $row) {
            $site = Site::fromString($row['site']);
            $definition = TemplateDefinition::fromArray([
                'subject' => $row['subject'],
                'preheader' => $row['preheader'],
                'format' => $row['format'],
                'body' => $row['body'],
                'text' => $row['text_part'],
                'variables' => json_decode($row['variables'], true, flags: \JSON_THROW_ON_ERROR),
                'segments' => json_decode($row['segments'], true, flags: \JSON_THROW_ON_ERROR),
                'embeds' => json_decode($row['embeds'], true, flags: \JSON_THROW_ON_ERROR),
            ]);
            $revision = $this->templates->draft($row['template_key'], $row['locale'], $site)->revision ?? 0;

            $this->templates->save($row['template_key'], $row['locale'], $definition, $revision, $site, $row['author']);
            $this->templates->publish($row['template_key'], $row['locale'], $site, $row['author']);
        }
    }
}
```

Run it from a command or a Doctrine migration once the tables of the contents exist — a migration that writes contents
needs them created by an earlier one —, check the templates with `phil:email-templates:list`, then drop the table in the
next migration. Each copy is checked as a save is: a version that no longer passes the checks stops the copy with the
reasons, to be fixed in the table first, or skipped by catching `InvalidTemplate`. The moments of the copies are those of
the copy, not `saved_at`; keep the table, or an export of it, as long as the original dates are wanted.

## The optional packages

| Package | Brings |
|---|---|
| `twig/cssinliner-extra` | The styles of the layout inlined into each element |
| `twig/intl-extra` | `format_date`, `format_number`, `format_currency` in the templates |
| `symfony/mailer`, `symfony/messenger` | `SendTemplatedEmail`, sent when the message is handled |
| `symfony/console` | `phil:email-templates:list`, `check` and `render` |
| `symfony/security-bundle`, `symfony/security-csrf`, `symfony/ux-twig-component` | The administration and its preview |
| `phil/tenancy-bundle` | The active tenant as the current site |
| `phil/audit-bundle` | Every change recorded in the audit trail |

## The recipes of the kit

The pages of the administration draw their parts with recipes of the PHIL UX kit, installed in the application by the
Symfony UX Toolkit; nothing is rendered from `vendor/`:

```sh
for recipe in alert badge button field input native-select table textarea; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

A site on Tailwind installs the same recipes from the Shadcn kit of the Toolkit instead, with `label`, which its
`Field:Label` calls, and Symfony UX Icons, which its select draws; the pages give the recipes only what both kits read
the same way. They are found under the components subdirectory of the `ux_toolkit` configuration; in an environment
where the Toolkit is not enabled, as production often is, repeat it in `kit_subdirectory`.
