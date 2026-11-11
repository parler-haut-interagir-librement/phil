<!-- phil-dev source-sha256=b88a0975e8a156c4c580237d6a42902f50e079ab33f05d728f012b5efdea55ce -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilRecurrenceBundle` | `final class`, `AbstractBundle` | Alias `phil_recurrence` ; les services et le type de colonne |
| `Application\RuleDescription` | `final readonly class` | Une règle en mots |
| `Infrastructure\Form\RecurrenceRuleType`, `RecurrenceRuleTransformer` | type de formulaire, transformateur de données | Le formulaire de règle |
| `Infrastructure\Doctrine\RecurrenceRuleColumnType` | type DBAL | `phil_rrule` |
| `Infrastructure\Twig\RecurrenceExtension` | extension Twig | Le filtre `rrule_text`, les fonctions `rrule_occurrences` et `rrule_schema` |
| `assets/src/recurrence_controller.js` | contrôleur Stimulus | Les rangées de la fréquence et de la fin choisies |
