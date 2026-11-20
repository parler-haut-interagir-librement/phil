<!-- phil-dev source-sha256=5afd6a7e1153b3425c04b341e87e9335e93a84fef5e776f6342b03de72582552 -->
# Tests

## Les tests du bundle

```sh
vendor/bin/phpunit packages/moderation-bundle/tests
```

| Domaine | Ce qui est couvert |
|---|---|
| Le domaine | Les membres et le refus des adresses, les cibles, les identifiants, les signalements, les décisions, les exposés des motifs, les notes, les lignes de la file |
| Les services | Les signalements une fois par membre, les refus ; les décisions appliquées et fermant, les rôles et leurs types ; les exposés des motifs, leurs motifs vérifiés avant l'action, lus par leur seul membre ; la file selon le rôle ; le bac à sable quitté à l'approbation ; les mots surveillés ; le masquage temporaire ; les notes ; le journal sans noms ; la montée en flèche |
| Les magasins | Une seule suite sur SQLite en mémoire, MySQL 8.4 et PostgreSQL 17 : ordre, regroupement, pages, types, UTC, oubli, lignes qui ne se lisent plus |
| Les adaptateurs | Le membre et le modérateur du jeton, l'export et l'effacement, la proposition du mode d'incident, la notification d'un exposé, les fonctions Twig, l'écouteur de schéma, les aides d'EasyAdmin |
| Le bundle | Chaque option et ses bornes, les services sans SecurityBundle, les passes |
| Les pages | Un noyau sur SQLite avec les recettes du kit : le formulaire de signalement, la file, une décision et son exposé des motifs lu par l'auteur, les notes, la file qui offre la page de `phil/incident-mode-bundle` et propose un incident, une inscription, une invitation et un formulaire refusés par ses points de contrôle, le journal, chaque refus |
| EasyAdmin | Un tableau de bord avec les pages greffées, une décision dans l'interface d'administration, aucun mode d'incident offert sans son bundle |

Les tests d'intégration lisent `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` et sont ignorés sans elles. Dans le
monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

## Banc d'essai

`tests/Benchmark/ModerationBench.php` exécute les deux chemins sur SQLite en mémoire avec 10 000 signalements sur 2 000
cibles, dont la moitié ont été jugées. Sur un portable récent :

| Sujet | Temps |
|---|---|
| Une page de 25 lignes de la file, avec son décompte | environ 10 ms |
| La soumission d'un contenu par un membre hors du bac à sable | environ 2 µs |

```sh
composer bench -- packages/moderation-bundle/tests/Benchmark
```

## Tester une application

Une cible de l'application se teste contre le contrat de `ModerationTarget` : `describe()` donne `null` pour un contenu
qui n'existe plus, et `apply()` peut être appelée deux fois. Les pages se testent avec un noyau qui enregistre la cible,
un `CurrentMember` qui lit les utilisateurs du test, et les recettes du kit.
