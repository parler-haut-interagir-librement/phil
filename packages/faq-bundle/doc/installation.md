# Installation

## Requirements

- PHP 8.5 or later, with `ext-intl`, `ext-mbstring` and `ext-sodium`.
- Symfony 8.2 or later, with FrameworkBundle's CSRF protection on: the votes and the counted searches are checked by
  its stateless token `phil_faq`, which needs neither a session nor a cookie.
- Doctrine DBAL 4.5 and ORM 3.7, through DoctrineBundle.
- Twig and Symfony UX Twig Component 3.7 or later.
- Optional: AssetMapper and StimulusBundle for the controller; Symfony Security for the administration; the console
  and the YAML component for the commands; EasyAdmin for the links of a dashboard.

## The bundle

```sh
composer require phil/faq-bundle
```

Symfony Flex registers the bundle, copies `config/packages/phil_faq.yaml` and `config/routes/phil_faq.yaml`, and
prints the steps below. Without Flex:

```php
// config/bundles.php
Phil\FaqBundle\PhilFaqBundle::class => ['all' => true],
```

## The tables

The bundle maps its entities for DoctrineBundle. Create the tables with a migration:

```sh
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

| Table | What it holds |
|---|---|
| `phil_faq_section` | A section: its key, its place |
| `phil_faq_section_translation` | The title of a section per language |
| `phil_faq_question` | A question: its section, its anchor, its place, its totals of useful and not useful answers |
| `phil_faq_question_translation` | Per language: the wording, the answer as written and as HTML, the publication, the moment it changed |
| `phil_faq_unanswered_search` | A search without answer: its language, its words, its total, its latest day |
| `phil_faq_spent_vote` | The hash of a vote token used for a question, until the token expires |

The moments are kept in UTC (`datetime_utc_immutable`), whatever the time zone of the server or of the connection.

## The routes

```yaml
# config/routes/phil_faq.yaml
phil_faq:
    resource: '@PhilFaqBundle/config/routes.php'
    prefix: /faq
```

| Route | Path | What it does |
|---|---|---|
| `phil_faq_page` | `GET /` | The page of questions, filtered by its `q` |
| `phil_faq_vote` | `POST /vote/{anchor}` | A vote, from a plain form or in JSON |
| `phil_faq_unanswered` | `POST /unanswered` | The count of a search without answer, from the controller |

The administration has routes of its own, mounted behind the firewall:

```yaml
phil_faq_admin:
    resource: '@PhilFaqBundle/config/admin_routes.php'
    prefix: /admin/faq
```

## The recipes of the kit

The component draws three recipes of the PHIL UX kit, which the Symfony UX Toolkit copies into the application:

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install copy-button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The names follow the components subdirectory of the Toolkit: with `ui`, the component calls `ui:Button`. An application
without the Toolkit's configuration sets `phil_faq.kit_subdirectory`.

## The Stimulus controller

With AssetMapper and StimulusBundle, Flex enables the controller in `assets/controllers.json`; it is fetched lazily,
on the first page that holds questions. Without it, the page works the same: the search goes to the server, the votes
come back to the page.
