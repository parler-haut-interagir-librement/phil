<!-- phil-dev source-sha256=17aa5a06b77a67b657bd0d3a4070b56ada8cc3ef55cf28dc3bb55a51d5b27803 -->
# phil/survey-bundle

**Menez des sondages qui s'adaptent à chaque réponse : sections conditionnelles, questions de classement et de matrice,
quotas croisés, anonymat par défaut, résultats affichés au-delà d'un seuil.**

phil/survey-bundle est un bundle Symfony pour les questionnaires. Un questionnaire a des **sections** et des
**questions** de douze types — texte libre, courriel, nombre, date, oui ou non, choix unique et choix multiple,
classement, échelle, indice de promotion net (Net Promoter Score) et matrice — et des **conditions** stockées comme des
structures JsonLogic, jamais comme du code, évaluées par `phil/form-logic` dans le navigateur, puis de nouveau sur le
serveur. Les réponses libres sont vérifiées par `phil/forms-bundle` ; les choix, les notes, les classements et les
lignes de matrice, par les règles de `phil/poll`, qui les compte aussi.

Le serveur juge chaque réponse : les questions masquées sont écartées, toutes les erreurs sont données à la fois, et la
première **fin** dont la condition est vraie est affichée, avec son profil, ou une **redirection** vers une origine
que l'application autorise. Les **quotas croisés** sont tenus par des compteurs atomiques dans la transaction de la
réponse : des répondants simultanés ne dépassent donc jamais un quota.

Les questionnaires sont **anonymes par défaut**, et la page le dit avant la première question : aucun répondant, aucune
adresse IP, le jour seulement. La **rétroaction de séance** s'ouvre dans une fenêtre autour de chaque séance ; les
**addenda** permettent à un répondant de compléter une réponse envoyée ; les **résultats** sont retenus sous un minimum,
et ne sont montrés aux répondants que si un questionnaire les active. Chaque questionnaire fixe la durée de
conservation de ses réponses, et une purge planifiée supprime le reste. Les questionnaires sont faits à partir de
**gabarits**, et exportés et importés en JSON avec un schéma publié ; leurs questions viennent aussi d'un **fichier
CSV** par `phil/import-wizard`, avec un essai à blanc qui refuse, cellule par cellule, chaque ligne que le
questionnaire refuserait. Les réponses identifiées participent à l'exportation et à l'effacement de
`phil/privacy-bundle`.

Les pages fonctionnent sans JavaScript ; un contrôleur Stimulus affiche une question ou une section à la fois, avec la
progression. Les tables, sur Doctrine DBAL, sont testées sur SQLite, MySQL 8.4 et PostgreSQL 17.

## État

| Partie | État |
|---|---|
| Le modèle du questionnaire, ses douze types de questions, son format d'échange JSON et son schéma | Implémenté, testé et soumis au fuzzing |
| Les conditions en structure, évaluées par `phil/form-logic` ; les fins, les profils et les redirections vers des origines autorisées | Implémenté et testé |
| Les réponses jugées sur le serveur, les quotas croisés par compteurs atomiques | Implémenté et testé, sur SQLite, MySQL et PostgreSQL avec des répondants simultanés |
| L'anonymat par défaut, la rétroaction de séance et sa fenêtre, les addenda | Implémenté et testé |
| Les résultats retenus sous un minimum, les résultats publics après la réponse, désactivés par défaut | Implémenté et testé |
| Une conservation par questionnaire, sa purge planifiée | Implémenté et testé |
| Les gabarits et le gabarit `session_feedback` | Implémenté et testé |
| L'importation CSV des questions par `phil/import-wizard`, son essai à blanc et sa commande | Implémenté, testé et soumis au fuzzing |
| L'exportation et l'effacement de `phil/privacy-bundle` | Implémenté et testé |
| Les pages sans JavaScript et le contrôleur Stimulus `progress` | Implémenté ; le rythme du contrôleur est testé sous Node |
| Un constructeur visuel de conditions, des exportations vers des tableurs | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Questionnaires](doc/fr/questionnaires.md) | Le modèle, les types de questions, les conditions, le format JSON et les gabarits. |
| [Importation CSV](doc/fr/csv-import.md) | Le fichier CSV des questions, ses cellules, l'essai à blanc, l'importation dans un questionnaire et la commande. |
| [Réponses](doc/fr/responses.md) | Les envois, les fins et les redirections, les quotas, l'anonymat, la rétroaction de séance et les addenda. |
| [Résultats, conservation et vie privée](doc/fr/results.md) | Les résultats et leur minimum, la purge, l'exportation et l'effacement. |
| [Pages](doc/fr/pages.md) | Les routes, les gabarits et le contrôleur Stimulus. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\SurveyBundle\PhilSurveyBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_survey.yaml
phil_survey:
    redirect_origins: ['https://example.org']
```

```yaml
# config/routes/phil_survey.yaml
phil_survey:
    resource: '@PhilSurveyBundle/config/routes.php'
    prefix: /survey
```

```php
$questionnaires->fromTemplate('session_feedback', 'keynote', 'Feedback on the keynote');
// GET /survey/keynote?subject=keynote-2026 shows it while the session's feedback is open.
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
