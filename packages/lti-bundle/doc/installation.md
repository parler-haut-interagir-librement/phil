# Installation

## Requirements

- PHP 8.5 or later;
- Symfony 8.2 FrameworkBundle, SecurityBundle and TwigBundle;
- Doctrine DBAL 4 with DoctrineBundle, for the store `dbal`;
- Symfony KeyManagement with a KMS client, for keys kept in the table;
- optional: the ORM, for the migrations; Console, for the commands; Messenger, to send scores through a bus;
  AssetMapper and StimulusBundle, for the controller of the form on a page of the application's own; the UX Twig
  components and the recipe `Button` of the kit, for its button.

## Installing

```sh
composer require phil/lti-bundle
```

The Flex recipe of the bundle registers it, writes `config/packages/phil_lti.yaml` with both sides off and keys in the
table under the master key named by `LTI_MASTER_KEY`, and `config/routes/phil_lti.yaml` with the key set route and the
routes of each side commented out. The recipe lives in the recipes repository of PHIL, read once the application lists
that repository in `extra.symfony.endpoint`; without it, register the bundle and write the configuration by hand.

## The routes

Mount the routes of the side the application plays, and the key set route once:

```yaml
# config/routes/phil_lti.yaml
phil_lti_jwks:
    resource: '@PhilLtiBundle/config/routes/jwks.php'
    prefix: /lti

phil_lti_tool:
    resource: '@PhilLtiBundle/config/routes/tool.php'
    prefix: /lti

phil_lti_platform:
    resource: '@PhilLtiBundle/config/routes/platform.php'
    prefix: /lti/platform
```

## The firewalls

The launch of the tool goes through a firewall with the bundle's authenticator; the token endpoint and the services of
the platform are stateless and outside any login form; the authorization endpoint of the platform is behind the
firewall of its members.

```yaml
# config/packages/security.yaml
security:
    providers:
        lti: { id: Phil\LtiBundle\Infrastructure\Security\LtiUserProvider }
    firewalls:
        lti_services:
            pattern: ^/lti/(jwks|platform/(token|contexts))
            security: false
        lti_launch:
            pattern: ^/lti/(login|launch)
            provider: lti
            custom_authenticators: [Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator]
        main:
            # the application's own, which covers /lti/platform/authorize
```

When the application signs launches in to its own accounts through `LaunchUsers`, the firewall of launches names the
application's provider instead, or a chain of both.

## The tables

With the ORM, the bundle adds its eight tables to the schema the ORM generates for the connection:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, `bin/console phil:lti:schema` shows the SQL of the missing tables and of the columns the tables there
lack, and `--force` runs it. An application coming from 0.1 follows the upgrade of [storage](storage.md).

## The kit and the assets

The form of a launch draws its button with the recipe `Button` of the kit, installed by the Symfony UX Toolkit, nothing
rendered from `vendor/`:

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The Shadcn kit's `Button` takes the same props. The recipe is found under the components subdirectory of the
`ux_toolkit` configuration; where the Toolkit is not enabled, as in production, repeat it in `kit_subdirectory`.

The bundle's page posts its form by an inline script under the nonce of its policy, without the application's scripts
(see the security page). With AssetMapper, the bundle also serves a Stimulus controller as `@phil/lti-bundle`, for a
template of the application that loads its own importmap; a form already sent is left alone. Enable it in
`assets/controllers.json`:

```json
{
    "controllers": {
        "@phil/lti-bundle": {
            "auto-submit": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

## Scheduled tasks

Run `phil:lti:purge` daily, to remove the expired nonces, login states, access tokens and deep linking responses and the
scores refused beyond their retention, and `phil:lti:rotate-keys --if-due` daily, to rotate the signing key once it
reaches its lifetime. A tool also runs `phil:lti:recover-scores` every few minutes, to send again the scores whose
message was never dispatched.
