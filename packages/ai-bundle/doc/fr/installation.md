<!-- phil-dev source-sha256=7a0055daee731e5ac6a72f1e3062c41f1584ee669cbf466f74eb4cb290c38423 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- Symfony 8.2 avec FrameworkBundle, son traducteur et un service `clock`.
- Symfony AI Platform 0.15 et le pont de chaque fournisseur qu'appelle l'application.
- Doctrine ORM 3.7 et DoctrineBundle, pour le journal; un journal en mémoire sert aux tests.
- `phil/usage-meter`, installé avec le bundle; l'application construit le service `UsageMeter` avec lequel il compte.
- Facultativement, `phil/user-secrets-bundle`, pour les clés personnelles ; `phil/privacy-bundle`, pour les demandes de
  vie privée ; et `symfony/http-client` avec le `http_client` de FrameworkBundle, pour l'adaptateur d'images Replicate.

## Enregistrer le bundle

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

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_ai.yaml` avec la devise des coûts ;
les plateformes et les tâches sont à l'application de les déclarer, comme ci-dessous. La recette se trouve dans le
dépôt de recettes de PHIL, lu dès que l'application nomme ce dépôt dans `extra.symfony.endpoint` ; sans lui,
enregistrez le bundle à la main.

## Déclarer une plateforme

Une plateforme est n'importe quel service qui implémente la `PlatformInterface` de Symfony AI. Elle est habituellement
construite par la `PlatformFactory` du pont d'un fournisseur, avec la clé et le client HTTP de l'application :

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

Chaque tâche a aussi besoin de ses invites dans le domaine de traduction `phil_ai`; voir
[Tâches et suggestions](tasks.md).

## Créer la table

Le bundle associe `Phil\AiBundle\Domain\Model\JournalEntry` pour Doctrine, à la table `phil_ai_journal`. Générez la
migration comme pour n'importe quelle entité :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## Étapes suivantes

- [Configuration](configuration.md) pour chaque option.
- [Clés et quotas](keys-and-quotas.md) pour laisser les gens apporter leur propre clé, et pour compter les jetons.
