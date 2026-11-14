<!-- phil-dev source-sha256=a78f5a04ad77ee345d69289e710b8882ce95e82602b758dce477bc32e51678d6 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le domaine : les règles des déclarations, l'ordre des étapes, la progression et ses
heures. Elle teste `Onboarding` sur le stockage en mémoire, avec une horloge simulée et un répartiteur qui enregistre :
les vérificateurs interrogés, les étapes accomplies, cochées, passées, refusées, le parcours terminé une seule fois,
rangé, recommencé, un utilisateur oublié, les événements. Elle teste aussi l'écouteur des événements ; les stockages
Doctrine et en mémoire selon un même contrat partagé, sur SQLite en mémoire, et le mapping de la table ; les points de
terminaison, leur vérification CSRF et leurs réponses ; la liste de contrôle et la visite rendues à partir des
gabarits ; enfin la configuration du bundle, sa passe de compilation et ses services. La suite `tests/Functional`
démarre un noyau avec DoctrineBundle sur SQLite, TwigBundle, les composants et `phil/settings-bundle`, et fait suivre
un parcours à un membre : un formulaire, un événement, un vérificateur, le JSON de la visite et les visites
désactivées.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/onboarding-bundle/tests
```

Le contrôleur Stimulus est testé avec l'exécuteur de tests de Node, Stimulus étant résolu vers un *stub* :

```sh
cd packages/onboarding-bundle
node --import ./tests/js/register.mjs --test tests/js
```

Le banc d'essai `tests/Benchmark/OnboardingBench.php` mesure le chemin critique : la progression d'un utilisateur dans
un parcours de vingt étapes, lue dans une table de mille utilisateurs avec un vérificateur interrogé, et le catalogue
construit à partir de ses déclarations :

```sh
composer bench -- packages/onboarding-bundle/tests/Benchmark
```

Infection exige du bundle un score de mutation couvert de 100 %.

## Tester du code qui utilise des parcours

Configurez le stockage `memory` dans l'environnement de test :

```yaml
# config/packages/test/phil_onboarding.yaml
phil_onboarding:
    store: memory
```

```php
$onboarding->complete('ada', 'author', 'profile');

self::assertSame(1, $onboarding->progress('ada', 'author')->done());
```

Ou construisez `Onboarding` directement sur un `FlowCatalog::fromDeclarations()`, un `InMemoryProgressRepository`, un
`ServiceLocator` de vérificateurs et le `MockClock` de Symfony.
