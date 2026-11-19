<!-- phil-dev source-sha256=e2b1a1ef085fa3df10db6561bdeabeba58951ea831ba6025e5882541c82df7c8 -->
# Sécurité

Une affectation s'exécute souvent sur des entrées que des gens ont saisies — leurs choix, leur groupe, un fichier qu'un
organisateur a téléversé — dans une requête Web ou un worker partagé par de nombreuses requêtes. La bibliothèque
empêche que cette entrée coûte plus qu'elle ne le doit, et garde pseudonymes les gens qu'elle place.

## Borné avant tout travail

`Problem` vérifie sa taille contre `Limits` avant de construire quoi que ce soit : 5 000 créneaux, 20 000 candidats,
20 choix par candidat et 50 membres par groupe par défaut. `ProblemReader` vérifie les mêmes bornes sur chaque liste
avant de lire ses éléments, refuse sans la lire une chaîne JSON de plus de 16 Mio, et décode du JSON pas plus profond
que sa forme ne l'exige. Un problème qui passe les limites mais demanderait trop de travail — de nombreuses fermetures
de créneaux sous leur minimum, un réseau de flot conçu pour être lent — s'arrête à son `WorkBudget` avec
`BudgetExceeded`.

Pour une entrée venue du public, abaissez les deux :

```php
use Phil\Assignment\Limits;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\ProblemReader;
use Phil\Assignment\WorkBudget;

$reader = new ProblemReader(new Limits(slots: 200, applicants: 2_000, choices: 10, members: 8));
$assigner = new MinCostAssigner(budget: new WorkBudget(5_000_000));
```

## Refusé, jamais lu à moitié

Tout ce que le lecteur et le problème refusent est une `InvalidProblem`, avec un message qui nomme le champ : aucun
avertissement PHP, aucune erreur de type, aucun problème partiel. La cible de fuzzing `tests/Fuzz/problem.php` donne au
lecteur et aux quatre assigneurs du texte que personne n'a écrit, et vérifie que rien d'autre n'en sort et que chaque
résultat tient ses garanties.

## Identifiants pseudonymes

Les créneaux, les candidats et les membres sont nommés par des identifiants de 1 à 255 octets sans caractère de
contrôle. Un identifiant qui ressemble à une adresse de courriel est refusé : une adresse change et nomme une personne,
alors que l'identifiant stable de l'application — un numéro de compte, un code d'inscription — ne fait ni l'un ni
l'autre. Le résultat et son journal d'audit ne contiennent alors que des identifiants.

## Reproductible et vérifiable

Le tirage s'exécute sur un moteur à germe dont PHP fixe la séquence, et le germe revient avec le résultat. Un
organisateur qui annonce le germe avant le tirage, ou le publie avec le résultat, permet à tout participant de refaire
la même affectation et de vérifier que le placement de personne n'a été changé à la main. `Seed::random()` tire un
germe de la source sécurisée du système quand personne ne doit le choisir.

## Partagé entre les requêtes

Les assigneurs et le lecteur ne gardent aucun état entre les appels : le compteur de travail et chaque structure
intermédiaire sont créés pour chaque exécution. On peut les partager sans risque entre les requêtes d'un worker de
longue durée.
