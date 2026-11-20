# Installation

## Requirements

- PHP 8.5 or later.
- Symfony 8.2 with FrameworkBundle, its translator and a `clock` service.
- Symfony AI Platform 0.15 and the bridge of each provider the application calls.
- Doctrine ORM 3.7 and DoctrineBundle, for the journal; a journal in memory serves tests.
- `phil/usage-meter`, installed with the bundle; the application builds the `UsageMeter` service it counts with.
- Optionally `phil/user-secrets-bundle`, for the person's own keys; `phil/privacy-bundle`, for privacy requests; and
  `symfony/http-client` with FrameworkBundle's `http_client`, for the Replicate image adapter.

## Register the bundle

```sh
composer require phil/ai-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\AiBundle\PhilAiBundle::class => ['all' => true],
];
```

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_ai.yaml` with the currency of the
costs; the platforms and the tasks are the application's to declare, as below. The recipe lives in the recipes
repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without it, register
the bundle by hand.

## Declare a platform

A platform is any service implementing Symfony AI's `PlatformInterface`, usually built by the `PlatformFactory` of a
provider's bridge, with the application's key and HTTP client:

```yaml
# config/services.yaml
services:
    app.ai_platform:
        class: Symfony\AI\Platform\PlatformInterface
        factory: ['Symfony\AI\Platform\Bridge\TheProvider\PlatformFactory', 'create']
        arguments: ['%env(AI_API_KEY)%', '@http_client']
```

```yaml
# config/packages/phil_ai.yaml
phil_ai:
    platforms:
        main: { platform: 'app.ai_platform' }
    tasks:
        draft.rewrite: { platform: main, model: 'the-model' }
```

Each task also needs its prompts in the translation domain `phil_ai` — see [Tasks and suggestions](tasks.md).

## Create the table

The bundle maps `Phil\AiBundle\Domain\Model\JournalEntry` for Doctrine, on the table `phil_ai_journal`. Generate the
migration as for any entity:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## Next steps

- [Configuration](configuration.md) for every option.
- [Keys and quotas](keys-and-quotas.md) to let people bring their own key, and to count tokens.
