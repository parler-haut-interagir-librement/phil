<!-- phil-dev source-sha256=d32b49c90c2c0d9fcc81d445b0ffaa262ac70f06d6e740deeb3e3267788dc63b -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `intl`, `json`, `mbstring` et `sodium` | Chiffres écrits dans une locale ; tâches en JSON ; libellés comptés en caractères ; identifiants aléatoires. |
| `phil/svg-chart` | `^0.1.1` | Les images SVG des graphiques, et leurs images PNG par son rasterizer. |
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
| `symfony/http-foundation` | `ReportResponses` et `ChartResponses`. |
| `symfony/security-core` | Les tableaux de bord réservés à un rôle. |
| `easycorp/easyadmin-bundle` | Un tableau de bord comme page de l'interface d'administration. |
| `symfony/asset-mapper` | La feuille de style associée sous `phil-reporting`, quand AssetMapperBundle est enregistré. |
| L'extension PHP `resvg` | Les graphiques en images PNG, avec les polices de `png.fonts` : un téléchargement, l'image du courriel d'un rapport. Voir [Configuration](configuration.md#les-graphiques-png). |

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

Avec AssetMapper — AssetMapperBundle enregistré —, la feuille de style est associée sous `phil-reporting/reporting.css` :

```twig
<link rel="stylesheet" href="{{ asset('phil-reporting/reporting.css') }}">
```

Elle est écrite dans la couche de cascade `phil.components` sur les jetons de PHIL UX (`phil/ux-css`), avec une valeur
propre pour chacun quand PHIL UX n'est pas chargé. Ses barres lisent leur longueur dans un attribut `data-share` : aucun
style en ligne, ce qui permet de les afficher avec une Content Security Policy sans `'unsafe-inline'` pour les styles. Les gabarits portent les
classes propres du bundle, `phil-reporting-*`, et aucune classe d'un kit : ils sont dessinés de la même façon à côté de
PHIL UX ou de Tailwind, et la légende du tableau des barres est masquée par la feuille de style,
`phil-reporting-bars__caption`, pour les lecteurs d'écran seulement.

## Le répertoire du *claim check*

Le magasin `directory` garde les tâches et les exports sous `%kernel.project_dir%/var/phil_reporting`, créé lisible par
son seul propriétaire (`0700`). Un répertoire qui existe déjà et qui est ouvert à son groupe ou à d'autres utilisateurs
est restreint à `0700` avant qu'un fichier y soit conservé ; avec `tighten_open_directory: false`, ou quand il ne peut
pas l'être parce qu'un autre utilisateur en est le propriétaire, la conservation d'une tâche ou d'un export échoue avec
une exception qui nomme le répertoire et son mode. Le *worker* qui exécute les rapports et le code qui les met en file d'attente doivent le
partager : sur plusieurs machines, faites de `JobStore` et `ExportStore` des alias vers des services de l'application,
comme le montrent les [Points d'extension](extension-points.md).
