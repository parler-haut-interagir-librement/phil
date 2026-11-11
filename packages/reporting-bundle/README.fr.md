<!-- phil-dev source-sha256=ba19e60ed7686afa7fa0fe69460f91f465c4c4e31447e183587c91d0c0d71542 -->
# phil/reporting-bundle

**Des tableaux de bord et des rapports pour les applications Symfony : des chiffres fournis par n'importe quel bundle,
des graphiques dessinés sans script, des rapports exportés, planifiés et livrés.**

phil/reporting-bundle rassemble en un seul endroit ce que mesure une application. N'importe quel bundle, ou
l'application elle-même, fournit un chiffre — les commandes reçues, un taux de renouvellement — sous forme de
`MetricSource`, et une série — les visites par jour, les ventes par catégorie — sous forme de `SeriesSource`. La
configuration les place sur des tableaux de bord. Des composants Twig affichent un tableau de bord, une mesure ou un
graphique sur n'importe quelle page, et une interface d'administration EasyAdmin affiche un tableau de bord comme
l'une de ses pages quand EasyAdmin est installé.

Les graphiques sont du HTML, du CSS et du SVG, jamais un script. Une image SVG est dessinée sur le serveur par
`phil/svg-chart`, avec un titre, une description qui lit chaque valeur, et le tableau de ses valeurs à côté. Des barres
en HTML et en CSS forment elles-mêmes un tableau, et un tableau seul est un troisième rendu. Une mesure affiche sa
valeur écrite dans la locale du lecteur, et sa variation par rapport à la période précédente.

Un rapport est un service avec des colonnes et des lignes, lu ligne par ligne. Il peut être exporté en CSV, en TSV ou
en JSON, diffusé en téléchargement, écrit depuis la ligne de commande, ou planifié avec Symfony Scheduler et livré par
Mailer ou Notifier depuis un *worker* de Messenger. Un message ne porte qu'un identifiant : la tâche, avec ses
destinataires et ses paramètres, et le fichier exporté attendent dans des magasins que seul leur propriétaire peut
lire. C'est le *claim check*.

Il s'appuie sur la bibliothèque `phil/svg-chart` pour dessiner les images ; Symfony UX, Messenger, Scheduler, Mailer,
Notifier, Console et EasyAdmin sont chacun utilisés quand ils sont installés.

## État

| Partie | État |
|---|---|
| `MetricSource`, `SeriesSource` : le port des sources de données, trouvées par clé | Implémenté et testé |
| `Period`, `Metric`, `Series`, `SeriesSet`, avec des limites ; `PeriodPreset`, compté dans le fuseau horaire configuré | Implémenté et testé |
| Tableaux de bord dans la configuration, réservés à un rôle quand l'un est nommé | Implémenté et testé |
| Composants `PhilReporting:Dashboard`, `:LiveDashboard`, `:Metric`, `:Chart` | Implémenté et testé |
| Rendus SVG, HTML et tableau ; une feuille de style sur les jetons de PHIL UX, sans style en ligne | Implémenté et testé |
| `ReportDefinition`, `ReportFormat` (CSV, TSV, JSON), cellules de formule neutralisées, lignes bornées | Implémenté et testé |
| `ReportQueue`, `RunReport`, les magasins de tâches et d'exports du *claim check* | Implémenté et testé |
| Le calendrier `phil_reporting` de Symfony Scheduler | Implémenté et testé |
| Les livraisons `mail` et `notification` | Implémenté et testé |
| Téléchargements `ReportResponses`, `phil:reporting:export`, la page EasyAdmin | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle, la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_reporting`. |
| [Sources](doc/fr/sources.md) | Fournir des mesures et des séries, périodes et limites. |
| [Tableaux de bord](doc/fr/dashboards.md) | Les composants, les rendus, leur accessibilité, EasyAdmin. |
| [Rapports](doc/fr/reports.md) | Définitions, formats, téléchargements, la commande, calendriers, livraisons et le *claim check*. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester le code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class OrdersTaken implements MetricSource
{
    public function __construct(private OrderRepository $orders) {}

    public static function key(): string { return 'orders'; }

    public function title(): string { return 'Orders'; }

    public function metric(Period $period): Metric
    {
        return new Metric($this->orders->countBetween($period->start, $period->end));
    }
}
```

```yaml
# config/packages/phil_reporting.yaml
phil_reporting:
    timezone: America/Toronto
    dashboards:
        sales:
            title: Sales
            periods: [last_30_days, this_month, last_month]
            widgets:
                - { metric: orders, compare: true }
```

```twig
<twig:PhilReporting:LiveDashboard name="sales" />
```

## Prérequis

PHP 8.5 ou plus récent, avec `intl`, `json`, `mbstring` et `sodium` ; Symfony 8.2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
