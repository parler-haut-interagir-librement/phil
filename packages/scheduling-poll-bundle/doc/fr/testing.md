<!-- phil-dev source-sha256=7b58133bd7d52cf15c79445d404987675761fe619c93dfadbc9aaa099e4226bc -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```
La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau, sur les dépôts en mémoire et sur SQLite, que les
mêmes tests tiennent à un seul contrat. Elle couvre une clôture manuelle qui course l'échéance, dans un processus et sur
deux connexions à une même base de données, en annonçant la date une seule fois ; les fuseaux horaires à travers les
changements d'heure ; un lien révoqué ; qui voit les réponses avant la clôture ; et un fichier iCalendar vérifié ligne
par ligne. Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

La suite fonctionnelle, sous `tests/Functional`, démarre une petite application — FrameworkBundle, DoctrineBundle sur
SQLite, TwigBundle, TwigComponentBundle avec les recettes du kit PHIL UX — et demande les pages : se joindre, répondre
sans JavaScript, un lien qui n'ouvre rien, un sondage clôturé et le tableau de l'organisateur.

Les tests des contrôleurs Stimulus, sous `tests/js`, s'exécutent avec l'exécuteur de tests de Node, Stimulus étant résolu
vers une doublure.

## Tester du code qui utilise le bundle

Réglez `store: memory` dans l'environnement de test : les sondages, les répondants et les liens vivent en mémoire et
sont vidés entre deux requêtes. Un `MockClock` comme service `clock` fait avancer le temps au-delà d'une échéance ;
`closeDue()` clôt alors les sondages, et `eraseExpired()` efface ce dont le délai est passé, sans *worker*.
