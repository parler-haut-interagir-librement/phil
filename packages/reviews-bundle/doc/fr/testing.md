<!-- phil-dev source-sha256=8a537f38fd252de8dbddd898265ec68302ad648791cc00ba2732fc31dba8b1f9 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit/Domain` | Les valeurs et l'avis : leurs vérifications, ses états, les retraits, les contestations et l'anonymisation. |
| `tests/Unit/Application` | `Reviews` et `Ratings` sur SQLite en mémoire : un avis par membre, la fenêtre des participants à la seconde, la mention vérifiée, la compensation, les votes, les réponses, les retraits, les contestations, les ordres et le classement ; les types et les critères de retrait. |
| `tests/Unit/Infrastructure` | Les dépôts DBAL et leurs tables, deux connexions à un même fichier SQLite qui écrivent un avis, votent et calculent en même temps, la modération, la vie privée, l'`AggregateRating` absent quand un avis n'a pas été recueilli sur le site, la garde contre le pourriel, les limites de débit, la commande et les fonctions Twig. |
| `tests/Unit` | La configuration, les services enregistrés avec chaque paquet facultatif, et la recette Flex. |
| `tests/Functional` | Les pages dans une application : les formulaires sans JavaScript, les votes avec et sans JSON, les réponses, les contestations, les erreurs et les pages d'avis. |
| `tests/js` | Le contrôleur Stimulus des votes, sous l'exécuteur de tests de Node. |

La suite unitaire s'exécute sans noyau, sans serveur de base de données et sans réseau. Dans le monodépôt, Infection
maintient le paquet à 100 % des mutants couverts.
