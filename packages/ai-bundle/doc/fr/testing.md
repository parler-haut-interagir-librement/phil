<!-- phil-dev source-sha256=e17b49371406c0f097ea25095c03f615d3a1b1e8d495ac85910863065770fe07 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les types de valeur et la ligne de journal ; le catalogue, les définitions et la liste de
prix ; les gabarits d'invite sur le traducteur de Symfony, simples et ICU, avec les replis ; le quota sur un vrai
`UsageMeter` en mémoire (réservations refusées à chaque compteur et rendues, règlements au-dessus et au-dessous de la
réservation, rejeux, clé personnelle de la personne, sémaphore sur le magasin en mémoire de Symfony Lock) ; `AiTasks`
sur un modèle scénarisé et une horloge qui avance (clés selon la politique, échecs rendus, refus, quotas, budgets et
plafond, borne de coût avec et sans `max_tokens`, places parmi les tâches en cours en même temps, flux lus, échoués, quittés ou jamais lus, sorties, décisions, totaux,
effacement) ; un même contrat pour les deux journaux, exécuté en mémoire et sur Doctrine avec SQLite en mémoire, totaux
compris ; l'adaptateur Symfony AI sur la plateforme simulée de Symfony AI (messages, sortie structurée, outils, images
et parole, flux) ; l'adaptateur Replicate sur le client HTTP simulé de Symfony ; les avis sur les traductions fournies,
la divulgation, le registre et le module de vie privée ; l'adaptateur des secrets sur un vrai `UserSecrets` ; et la
configuration et le câblage. La suite `tests/Functional` démarre une petite application avec FrameworkBundle et son
traducteur, DoctrineBundle sur SQLite, le bundle des secrets et un compteur d'utilisation. Elle exécute des tâches de
leurs invites jusqu'à leur décision et à leur quota.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/ai-bundle/tests
```

La suite `tests/Integration` exécute le contrat du journal sur Doctrine avec un vrai MySQL 8.4 et un vrai PostgreSQL 17,
leurs totaux additionnés par chaque serveur ; le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle. Elle démarre aussi deux processus PHP qui admettent une tâche au même instant sur le magasin
Doctrine DBAL de `phil/usage-meter`, avec de la place pour une seule (pour les jetons, le budget et le plafond), sur
SQLite dans un fichier et sur les deux serveurs : l'une est admise, l'autre refusée, et une seule réservation reste
comptée.

Le bundle est tenu à un score de mutation couvert de 100 % par Infection.

## Tester une application qui l'utilise

Gardez le journal en mémoire, et répondez à la place du modèle en remplaçant le port `Completions` :

```yaml
# config/packages/test/phil_ai.yaml
phil_ai:
    store: memory
```

```yaml
# config/services_test.yaml
services:
    Phil\AiBundle\Application\Port\Completions:
        class: App\Tests\Ai\ScriptedCompletions
        public: true
```

```php
final class ScriptedCompletions implements Completions
{
    public function complete(ModelCall $call, #[\SensitiveParameter] ?string $userKey = null): Completion
    {
        return new Completion('A rewritten passage.', new Tokens(120, 80));
    }

    public function stream(ModelCall $call, #[\SensitiveParameter] ?string $userKey = null): Generator
    {
        yield 'A rewritten ';
        yield 'passage.';

        return new Completion('A rewritten passage.', new Tokens(120, 80));
    }
}
```

Vous pouvez aussi garder l'adaptateur Symfony AI et donner au service de plateforme le *mock* fourni par Symfony AI,
`Symfony\AI\Platform\Test\MockPlatformFactory::createPlatform()`. Il répond des résultats scénarisés et permet à un
test de lire les messages qu'il a reçus.
