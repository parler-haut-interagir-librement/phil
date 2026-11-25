<!-- phil-dev source-sha256=778b92d5f9bb5479901bfa9272a46642c821c8e37ae4aee74c55bc42d4f9ebf0 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Event`, `EventHost`, `Occurrence` | La planification, les vérifications, les journées entières, les règles, les dates ajoutées et les exceptions, la fin d'une série, les chevauchements, les limites, les organisateurs |
| Le mappage | Les colonnes, les longueurs et les index que créent les migrations, le lieu qu'un événement survit |
| `Venue`, `Venues`, `VenueDraft` | Un lieu propre et une fiche du répertoire, les limites et les refus, un événement dans un lieu avec son affiche |
| `Calendar`, `EventDraft` | Les brouillons à l'heure locale de leur fuseau horaire, les organisateurs, les occurrences d'une période dans l'ordre, d'un organisateur ou d'une étiquette |
| `ICalendar` | Les heures avec fuseau, en UTC et en dates, les règles, les dates ajoutées, les exceptions, les échappements, le repliement entre les caractères |
| `EventType`, `TagsTransformer` | La soumission, le fuseau horaire par défaut, les libellés et les options |
| `CalendarFeedController`, `ICalendarResponse` | Le fichier iCalendar, d'un organisateur et d'une étiquette |
| `Period`, `CalendarView` | Les bornes de chaque vue, les semaines du lundi et du dimanche, les mois de cinq et de six semaines, les périodes voisines, le changement d'heure |
| `MonthGrid`, `Lanes`, `TimeGrid`, `Resources` | Les barres sur les semaines et les voies, l'ordre d'une voie, ce qui n'a pas trouvé place, les heures côte à côte, les bords des heures et du jour, les instants, les jours partagés |
| `Agenda`, `DaySpan`, les sources | Les groupes par jours, les jours en mots en français, en anglais et en allemand, les événements et les dates de l'almanach comme éléments |
| `CalendarViews`, `CalendarComponent`, `AlmanacImport` | Les valeurs par défaut, les colonnes d'un jour partagé, les déplacements, les vues et les replis du composant, l'almanach en événements une seule fois, la commande |
| Le contrôleur Stimulus | Les jours et les semaines parcourus dans la grille, l'ordre de tabulation, avec l'exécuteur de tests de Node |
| `CalendarExtension`, `PhilCalendarBundle` | Quand une occurrence a lieu en deux langues ; les services, la configuration, le mappage |
| Traductions | Les tables, les limites et les refus d'une traduction ; les textes servis en entier, une locale non servie, les événements dans leur propre texte sans locales ; les données schema.org et Twig dans une locale |
| Participants, calendriers de membres, propositions | Chaque refus, la politique demandée en dernier, les décomptes et les pages, les places restantes ; les occurrences auxquelles un membre participe ou qu'il organise, une fois chacune ; les états et les transitions d'une proposition, une garde qui en retient une |
| Le budget, `ExpansionBudget` | Ses bornes, ce que coûte chaque événement, un événement coupé aux occurrences restantes, l'arrêt consigné une fois, la remise à zéro ; une requête de 1 500 règles qui ne correspondent jamais, tenue dans un temps borné qui croît avec le budget et non avec les événements |
| Le calendrier d'un membre | La valeur de chaque source, suivre, masquer et oublier, 200 choix au plus, le brouillon validé ; ce à quoi il participe, ce qu'il organise et ce qu'il suit moins ce qu'il masque, en une période et une requête d'organisateurs ; les secrets de flux émis, révoqués, refusés lorsqu'ils sont mal formés |
| Le flux d'un membre, la vie privée, le formulaire de choix | Des occurrences comme fichier à elles, la réponse privée, `304`, `404` pour un secret révoqué, inconnu ou mal formé ; l'exportation et un effacement exécutés deux fois ; les champs du formulaire ; le composant d'un membre, et sa note lorsque le budget coupe la période |
| Pages propres, impression | Les adresses lues par `phil/url`, leur borne, une nouvelle règle qui les retire ; les mois imprimés (éléments à cheval sur des mois, un élément annulé, un élément qui finit à minuit, des semaines à partir du dimanche), les bornes, le PDF |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle, PhilRecurrenceBundle
et le bundle : `CalendarBundleTest` stocke des événements et leurs organisateurs, les trouve par période, par
organisateur et par étiquette, interroge les deux flux, produit le rendu du formulaire et de la fonction Twig en
`fr_CA`, garde des lieux et des affiches, et produit le rendu du composant dans chaque vue, avec TwigComponentBundle,
LiveComponentBundle et StimulusBundle. `CalendarFeaturesTest` traduit un événement et son lieu et les lit en anglais et
en français, inscrit des membres sur `DoctrineAttendanceRepository` dans les limites d'une capacité, lit le calendrier
d'un membre, approuve et refuse une proposition par son flux de travail, imprime un mois et suit une page propre sur les
vues. `PersonalCalendarTest` garde des choix et des secrets dans leurs tables, répond au flux d'un membre à son adresse,
dessine les choix avec les recettes du kit, produit le rendu du composant d'un membre, remet le budget à zéro avec
`services_resetter`, et oublie les données d'un membre. `AssetMapperTest` démarre un noyau avec AssetMapperBundle et
trouve le contrôleur et la feuille de style là où le bundle les associe ; l'autre noyau démarre sans lui. Lorsque le
bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de développement, la suite est sautée.

La suite d'intégration exécute les tables et les requêtes du calendrier d'un membre — choix, secret et leurs moments en
UTC, participations, propositions, effacement — sur MySQL et PostgreSQL, à partir de l'URL de chaque serveur :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
    vendor/bin/phpunit packages/calendar-bundle/tests/Integration
```

```sh
vendor/bin/phpunit packages/calendar-bundle/tests
```

## Bancs d'essai

Les sujets phpbench sous `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles — entre autres le mois
d'un membre de 100 et de 500 événements, et un mois de 1 000 et de 5 000 règles qui ne correspondent jamais, que le
budget tient au même temps. Depuis la racine du monodépôt :

```sh
composer bench -- packages/calendar-bundle/tests/Benchmark
```

## Les requêtes de LiveComponent

`tests/Functional/LiveRequestsTest.php` envoie au composant les requêtes que le navigateur envoie, et le tient à ce que
LiveComponent 3.8.1 impose : sous le nom de sa classe plutôt que sous son nom, le composant répond 404 ; un 422 levé
avant son action, par un résolveur d'arguments ou un attribut de contrôleur, n'en produit aucun rendu ; et une
redirection répond 204 avec `X-Live-Redirect` et son `Location`, sans `X-Live-Url`, tandis qu'une réponse ordinaire
porte l'adresse que ses propriétés d'URL écrivent.
