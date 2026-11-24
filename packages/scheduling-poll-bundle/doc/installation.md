# Installation

```sh
composer require phil/scheduling-poll-bundle
```

Then register the bundle in `config/bundles.php`:

```php
Phil\SchedulingPollBundle\PhilSchedulingPollBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later with `ext-intl` and `ext-mbstring`, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5, and the
Messenger, Mailer and Scheduler components. The pages need TwigBundle, `twig/intl-extra` for the dates written in the
language of the page, and the recipes of the PHIL UX kit.

## The keys and the page of the links

The links are hashed under keys of the application, 32 bytes or more each, and point to the answer page:

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
```

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # a key for SCHEDULING_POLL_LINK_KEY
```

Keep the key in a secret of Symfony, never in the repository. To rotate it, put the new key first and keep the old one
after it until every link sent under it has expired; see [Privacy](privacy.md#the-links).

## The tables

The bundle maps its entities for Doctrine — `phil_scheduling_poll`, `phil_scheduling_poll_slot` and
`phil_scheduling_poll_respondent` — and adds the two tables of the links, `phil_access_link_invitation` and
`phil_access_link`, to the schema the ORM generates. A migration writes them all:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## The routes

```yaml
# config/routes/phil_scheduling_poll.yaml
phil_scheduling_poll:
    resource: '@PhilSchedulingPollBundle/config/routes.php'
    prefix: /scheduling-poll
```

The page of the links, `links.page`, is the address of the route `phil_scheduling_poll_answer` under that prefix.

## The schedule and the worker

The deadlines are closed and the names erased by the schedule `phil_scheduling_poll`, and the mails are sent by the
handlers of the bundle's messages. Run a worker for both:

```sh
php bin/console messenger:consume scheduler_phil_scheduling_poll async
```

Route `Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate` and `SendChosenDate` to an asynchronous
transport, so that a closing does not wait for the mails.

## The recipes of the kit

The templates draw their parts with the recipes `Table`, `Field`, `Input`, `NativeSelect` and `Button` of the PHIL UX
kit, installed by the Symfony UX Toolkit:

```sh
for recipe in table field input native-select button; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

The bundle names them after the components subdirectory of the Toolkit's configuration; `kit_subdirectory` says it
where the Toolkit is not installed. The Celebration recipe is needed only to fill the `celebration` block, as
[Pages and templates](pages.md#the-celebration) shows.
