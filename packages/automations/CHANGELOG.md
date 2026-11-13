# Changelog

All notable changes to `phil/automations` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `AutomationDefinition`, `Step` (action, delay, filter, branch), `StepPath`, `Condition`, `TriggerReference`,
  `Options` and `AutomationId`: a definition as data, bounded in steps, nesting, options and sizes.
- `DefinitionReader` and `DefinitionWriter`: the JSON of a definition, read strictly and written canonically.
- `Catalogue`, `ComponentDescription`, `OptionSpec`, `OptionType` and `DefinitionValidator`: the registered triggers,
  actions and condition languages, their options for a builder, and the check of a definition before it is published.
- The ports `Trigger`, `Action`, `CompensableAction`, `ConditionEvaluator`, `Definitions`, `RunStore` and
  `WebhookKeys`, and the attributes `AsAutomationTrigger` and `AsAutomationAction`.
- `ExpressionConditions` on `SafeExpressionLanguage`: conditions in Symfony's ExpressionLanguage without functions,
  over the run's payload, outputs and subject, with a bounded cache.
- `AutomationEngine`: one run per event key, a lease held by a save over the revision read, actions with idempotency
  keys, delays, filters, branches, retries with a backoff from 30 seconds to an hour, and compensation in reverse
  order.
- `Payload`, `TriggerMatch`, `ActionContext`, `AutomationRun`, `RunId`, `RunState` and `CompletedStep`: runs that hold
  identifiers only.
- `AdvanceRun`, `ResumeDueRuns`, their handlers and `AutomationsSchedule` for Symfony Messenger and Scheduler.
- `WebhookAction`: a JSON `POST` signed as Standard Webhooks, one `webhook-id` per step of a run, behind Symfony's
  `NoPrivateNetworkHttpClient`.
- `InMemoryDefinitions`, `InMemoryRunStore`, `DbalDefinitions` and `DbalRunStore`, tested on MySQL 8.4 and
  PostgreSQL 17.
- A fuzz target for `DefinitionReader`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
