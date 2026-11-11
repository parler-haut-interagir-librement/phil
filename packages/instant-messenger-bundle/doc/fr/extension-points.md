<!-- phil-dev source-sha256=5fa77989fc3558e832ec0f3b4565df0551a2f2a38dce5f05aed6a92ffa97042e -->
# Points d'extension

Ce qu'une application ajoute ou étend. Les services et les identifiants sont ceux de `phil/instant-messenger`.

## Les services propres à l'application

Une enum de l'application qui implémente `Phil\InstantMessenger\MessengerType` est acceptée partout où des services sont
nommés : dans les `types` de `HandleType` et de `HandleOfType`, avant ou après les services internationaux, sous forme
de classe d'enum, de cas, d'`EnumSet` ou de liste de ceux-ci. Ses choix sont nommés par le nom de leur cas ;
`messenger_label` donne la même chose (voir [Formulaires et validation](forms-and-validation.md)).

## Formulaires et validation

| Point | Comment |
|---|---|
| Les services offerts, et leur ordre | L'option `types` de `HandleType` |
| Les services acceptés | Les `types` de `HandleOfType` ; son `message` et le code `HandleOfType::NOT_ACCEPTED_ERROR` |
| Plus que ce que fait le champ | Une extension de type de formulaire de l'application sur `HandleType` |

## Stockage et Twig

| Point | Comment |
|---|---|
| L'endroit où un identifiant est conservé | Deux colonnes que l'application mappe (le service avec sa propre enum, l'identifiant canonique), relues avec `Handle::fromCanonical()` |
| La façon d'afficher un identifiant | `messenger_display`, `messenger_link` et `messenger_label` (voir [Twig](twig.md)) |
