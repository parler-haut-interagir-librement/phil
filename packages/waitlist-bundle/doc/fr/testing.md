<!-- phil-dev source-sha256=5bf2dc649d9a3046c033e15e8a0c1eeb0fffcd32b8f0a254b8d192a31eb5432c -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Waitlist`, `Entry` | Chaque valeur et son refus, les bornes de la fenêtre, les places gardées, le germe, le rang dans une file et dans un tirage, chaque état et ses changements, l'échéance à la seconde |
| `Waitlists` | Chaque méthode sur des dépôts en mémoire : le verrou, chaque refus, l'inscription répétée, la position, les places gardées jusqu'à ce que quelqu'un leur convienne, les offres et leur échéance, accepter, refuser, quitter, faire expirer liste par liste, le tirage, le lot, le choix de la politique vérifié, les événements et leur ordre |
| `FirstInLine`, `FirstThatFits` | L'ordre conservé, ou un groupe dépassé |
| Dépôts Doctrine | SQLite en mémoire : ce qui est conservé, le sujet unique, les filtres, l'ordre et la limite de chaque requête, les lectures qui voient ce qu'une autre requête a enregistré, le verrou validé, annulé et relisant la liste |
| `WaitlistNotifier` | La notification de chaque événement : son destinataire, son sujet, ses données et sa clé de doublon ; les types non nommés ou non déclarés |
| `phil:waitlist:lapse` | Les offres expirées et le décompte |
| Le bundle | Ses services et ses options, leurs bornes, le mappage, le notificateur câblé ou retiré, la recette, et un noyau sur SQLite avec le bundle de notifications et un écouteur des événements |

Infection maintient le bundle à 100 % des mutants tués.

```sh
vendor/bin/phpunit packages/waitlist-bundle/tests
```

Les tests n'ont besoin d'aucun serveur : SQLite s'exécute en mémoire.
