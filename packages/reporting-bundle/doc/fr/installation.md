<!-- phil-dev source-sha256=d7c85116f23088992cea98de2144f67ab58f1308f39db5d482d7fbda2fe1dd10 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `intl`, `json`, `mbstring` et `sodium` | Chiffres écrits dans une locale ; tâches en JSON ; libellés comptés en caractères ; identifiants aléatoires. |
| `phil/svg-chart` | `^0.1` | Les images SVG des graphiques. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | Le bundle et sa configuration. |
| `symfony/service-contracts` | `^3.6` | Les localisateurs des sources, rapports, formats et livraisons. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | Le moment à partir duquel les périodes sont comptées ; `ReportExported`. |

Chacun de ces paquets est utilisé quand il est installé :

| Paquet | Pour |
|---|---|
| `symfony/ux-twig-component` | Les composants `Dashboard`, `Metric` et `Chart`. |
| `symfony/ux-live-component` | Le composant `LiveDashboard`, dont la période change sans recharger la page. |
| `symfony/messenger` | `ReportQueue` et les *handlers* de `RunReport` et `RunScheduledReport`. |
| `symfony/scheduler` | Le calendrier `phil_reporting`. |
| `symfony/mailer`, `symfony/mime` | La livraison `mail`. |
| `symfony/notifier` | La livraison `notification`. |
| `symfony/console` | `phil:reporting:export`. |
| `symfony/http-foundation` | `ReportResponses`. |
| `symfony/security-core` | Les tableaux de bord réservés à un rôle. |
| `easycorp/easyadmin-bundle` | Un tableau de bord comme page de l'interface d'administration. |
| `symfony/asset-mapper` | La feuille de style associée sous `phil-reporting`. |

## Activer le bundle

```sh
composer require phil/reporting-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
];
```

Le bundle a besoin d'un service `clock`, qu'enregistre FrameworkBundle. Les composants ont besoin de TwigBundle et du
traducteur, pour leurs libellés du domaine `phil_reporting`.

## La feuille de style

Avec AssetMapper, la feuille de style est associée sous `phil-reporting/reporting.css` :

```twig
<link rel="stylesheet" href="{{ asset('phil-reporting/reporting.css') }}">
```

Elle est écrite dans la couche de cascade `phil.components` sur les jetons de PHIL UX (`phil/ux-css`), avec une valeur
propre pour chacun quand PHIL UX n'est pas chargé. Ses barres lisent leur longueur dans un attribut `data-share` : aucun
style en ligne, ce qui permet de les afficher avec une Content Security Policy sans `'unsafe-inline'` pour les styles.

## Le répertoire du *claim check*

Le magasin `directory` garde les tâches et les exports sous `%kernel.project_dir%/var/phil_reporting`, créé lisible par
son seul propriétaire. Le *worker* qui exécute les rapports et le code qui les met en file d'attente doivent le
partager : sur plusieurs machines, faites de `JobStore` et `ExportStore` des alias vers des services de l'application,
comme le montrent les [Points d'extension](extension-points.md).
