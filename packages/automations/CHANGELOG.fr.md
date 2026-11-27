<!-- phil-dev source-sha256=8f4e2771d3687d7e74eab732a07d125858f788c4459f21b35f24b537d3a7cb32 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/automations` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/http-client-contracts` 3.8, `symfony/service-contracts` 3.8),
  and of each development requirement (`doctrine/dbal` 4.6).

## 0.1.1-alpha - 2026-11-14

### Ajouté

- `ValidatedDefinitions` : des `Definitions` devant un autre stockage qui refusent, avec chaque problème, une définition
  que le `DefinitionValidator` n'accepte pas, ses conditions analysées par l'évaluateur qui les exécute ; l'installation
  le branche devant `DbalDefinitions`.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `AutomationDefinition`, `Step` (action, délai, filtre, branche), `StepPath`, `Condition`, `TriggerReference`,
  `Options` et `AutomationId` : une définition comme données, bornée en étapes, en imbrication, en options et en
  tailles.
- `DefinitionReader` et `DefinitionWriter` : le JSON d'une définition, lu strictement et écrit de façon canonique.
- `Catalogue`, `ComponentDescription`, `OptionSpec`, `OptionType` et `DefinitionValidator` : les déclencheurs, les
  actions et les langages de conditions enregistrés, leurs options pour un constructeur, et la vérification d'une
  définition avant sa publication.
- Les ports `Trigger`, `Action`, `CompensableAction`, `ConditionEvaluator`, `Definitions`, `RunStore` et `WebhookKeys`,
  et les attributs `AsAutomationTrigger` et `AsAutomationAction`.
- `ExpressionConditions` sur `SafeExpressionLanguage` : des conditions dans l'ExpressionLanguage de Symfony sans
  fonctions, sur le contenu de l'exécution, ses sorties et son sujet, avec un cache borné.
- `AutomationEngine` : une exécution par clé d'événement, un bail tenu par un enregistrement sur la révision lue, des
  actions avec des clés d'idempotence, des délais, des filtres, des branches, des reprises avec un recul de 30 secondes
  à une heure, et la compensation dans l'ordre inverse.
- `Payload`, `TriggerMatch`, `ActionContext`, `AutomationRun`, `RunId`, `RunState` et `CompletedStep` : des exécutions
  qui ne contiennent que des identifiants.
- `AdvanceRun`, `ResumeDueRuns`, leurs handlers et `AutomationsSchedule` pour Symfony Messenger et Scheduler.
- `WebhookAction` : un `POST` JSON signé selon Standard Webhooks, un `webhook-id` par étape d'une exécution, derrière le
  `NoPrivateNetworkHttpClient` de Symfony.
- `InMemoryDefinitions`, `InMemoryRunStore`, `DbalDefinitions` et `DbalRunStore`, testés sur MySQL 8.4 et PostgreSQL 17.
- Une cible de fuzzing pour `DefinitionReader`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
