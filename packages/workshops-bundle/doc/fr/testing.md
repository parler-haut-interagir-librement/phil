<!-- phil-dev source-sha256=b31d55caf638bd5c6fb8a69983bbc7153bb753c9cfc8f66c520260956b288445 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Workshop`, `WorkshopSession`, `Registration` | Chaque valeur et son refus, la fenêtre et ses bornes, les états et leurs changements, les moments sur l'horloge de la séance |
| `Workshops` | Chaque méthode sur des dépôts en mémoire : le verrou, chaque refus et son ordre, l'inscription faite deux fois, les places qu'une liste d'attente réclame jamais prises, la capacité jamais sous les places prises et offertes, les événements et leur ordre |
| Dépôts Doctrine | SQLite en mémoire : ce qui est gardé, les moments stockés et lus en UTC dans un processus d'un autre fuseau, les filtres et l'ordre de chaque requête, la cascade d'un atelier supprimé, le verrou validé, annulé et relisant la séance |
| Messages | Chaque refus et chaque statut en anglais et en français, les deux langues avec chaque message |
| Les listes d'attente | Un noyau avec phil/waitlist-bundle sur SQLite : joindre et ses refus, les places libérées offertes tour à tour sans que personne double la file, les offres acceptées en inscriptions, les places tenues pour personne rendues, plus et moins de capacité, une séance annulée qui ferme sa liste, les listes d'autres sujets laissées intactes |
| Le bundle | Ses services et ses options, leurs bornes, l'intégration ajoutée et retirée, l'association, la recette, et un noyau sur SQLite avec un écouteur des événements |

Infection maintient le bundle à 100 % des mutants tués.

```sh
vendor/bin/phpunit packages/workshops-bundle/tests
```

Les tests n'ont besoin d'aucun serveur : SQLite s'exécute en mémoire.
