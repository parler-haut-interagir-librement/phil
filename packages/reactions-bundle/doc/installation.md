# Installation

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later, Symfony UX Live Component 3.8.1 and Twig Component 3.8 or later,
Doctrine DBAL 4.5 and ORM 3.7, and twig/intl-extra, whose `format_list` writes the names of those who reacted in the
language of the page. The bundle registers the extension of twig/intl-extra itself, unless TwigExtraBundle is there to
register it. FrameworkBundle's CSRF protection has to be on: the container stops with a message that says so
otherwise. The token is stateless and needs no session.

## The Flex recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_reactions.yaml`, which
names `App\Reactions\CurrentMember` as the member, copies that class to `src/Reactions/CurrentMember.php`, and writes
the route of the plain form in `config/routes/phil_reactions.yaml`. Until the application makes the class read the
signed-in account, it gives nobody: every visitor sees what is counted, and the application boots with Symfony
Security too. The recipe lives in the recipes repository of PHIL, read once the application lists that repository in
`extra.symfony.endpoint`; without it, register the bundle and write the configuration by hand, as below.

## The bundle

```php
// config/bundles.php
Phil\ReactionsBundle\PhilReactionsBundle::class => ['all' => true],
```

## The table

The bundle maps the table `phil_reaction` for Doctrine: generate a migration, or let the schema tools create it. The
moment of a reaction, `reacted_at`, is a `datetime_utc_immutable` column: written and read back in UTC, whatever the
time zone of the server or of the connection.

```sh
php bin/console doctrine:migrations:diff
```

## The routes

The plain form posts to one route, which the component needs to write its address:

```yaml
# config/routes/phil_reactions.yaml
phil_reactions:
    resource: '@PhilReactionsBundle/config/routes.php'
    prefix: /_reactions
```

## The recipes

The templates of the bundle draw the buttons with two recipes of the kit, installed in the application by the Symfony
UX Toolkit and nothing rendered from `vendor/`:

```sh
php bin/console ux:install reaction-button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install reaction-picker --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The kit `github.com/parler-haut-interagir-librement/ux-kit-shadcn` has the same recipes for Tailwind. The components
are found under the components subdirectory of the `ux_toolkit` configuration. In an environment where the Toolkit is
not enabled, as production often is, repeat it in the option `kit_subdirectory`.

## The Stimulus controllers and the stylesheet

With AssetMapperBundle registered, the bundle adds its controllers and its stylesheet to the paths of the
`asset_mapper` extension. Symfony Flex enables the controllers `reactions` and `picker` of `@phil/reactions-bundle` in
`assets/controllers.json` from the bundle's `package.json`, fetched lazily: StimulusBundle loads each the first time an
element names it. Import the stylesheet:

```js
import 'phil-reactions/reactions.css';
```

Without any of this the reactions still work, through their plain forms.

## The member

The site names the service that gives the stable identifier of a member, never an email address:

```yaml
# config/packages/phil_reactions.yaml
phil_reactions:
    current_member: App\Reactions\AccountMember
```

With Symfony Security, the container is not built until `current_member` is set; `TokenCurrentMember` serves a site
whose user identifiers are stable. An identifier containing `@` is refused: see [reactions](reactions.md#the-member).

## The voter

Every reaction is refused until a voter answers the attribute `phil_reactions.react`: see
[reactions](reactions.md#who-may-react). Without Symfony Security, name a `ReactionAccess` and a `CurrentMember`.
