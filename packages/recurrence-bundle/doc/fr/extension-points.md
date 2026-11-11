<!-- phil-dev source-sha256=caa62a42acb6ef57c11a26ad40f84e2d8076392b58185094d28b911311628d2a -->
# Points d'extension

Ce que l'application étend, remplace ou écoute. Les règles sont celles de `phil/recurrence`.

## Mots

Les phrases de `RuleDescription` et de `rrule_text` sont des messages ICU du domaine `phil_recurrence`, tout comme les
libellés du formulaire. L'application change les mots, ou ajoute une langue, en remplaçant les messages : voir
[Mots, Twig et stockage](words-twig-storage.md).

## Formulaires

| Point | Comment |
|---|---|
| Ce que le formulaire offre | `RecurrenceRuleType` écrit les règles courantes : fréquence, intervalle, jours, jour du mois ou jour de la semaine, mois, fin ; une règle qui les dépasse est conservée par un champ de l'application qui contient le texte RFC 5545 |
| Plus que ce que fait le formulaire | Une extension de type de formulaire de l'application sur `RecurrenceRuleType` |

Le contrôleur Stimulus montre les rangées de la fréquence et de la fin choisies. Ses cibles sont `frequency`, `end` et
`row`, et chaque rangée nomme dans `data-show` les fréquences ou les fins qui la concernent : un thème de formulaire de
l'application les conserve ainsi. Voir [Formulaires](forms.md).

## Stockage et Twig

| Point | Comment |
|---|---|
| L'endroit où une règle est conservée | Le type de colonne `phil_rrule`, le texte RFC 5545 |
| Les occurrences d'une page | `rrule_occurrences`, avec ses bornes |
| Les données structurées | `rrule_schema`, un `Schedule` de schema.org |
