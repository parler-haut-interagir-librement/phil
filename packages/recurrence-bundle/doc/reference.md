# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilRecurrenceBundle` | `final class`, `AbstractBundle` | Alias `phil_recurrence`; the services and the column type |
| `Application\RuleDescription` | `final readonly class` | A rule in words |
| `Infrastructure\Form\RecurrenceRuleType`, `RecurrenceRuleTransformer` | form type, data transformer | The rule form |
| `Infrastructure\Doctrine\RecurrenceRuleColumnType` | DBAL type | `phil_rrule` |
| `Infrastructure\Twig\RecurrenceExtension` | Twig extension | The filter `rrule_text`, the functions `rrule_occurrences` and `rrule_schema` |
| `assets/src/recurrence_controller.js` | Stimulus controller | The rows of the frequency and the end chosen |
