<!-- phil-dev source-sha256=0bbec68fde23f04b641e9df45feb008facd7f7e2ce16ee6e0cde8d662d5257c8 -->
# Tableaux de bord

## Les composants

| Composant | Affiche |
|---|---|
| `<twig:PhilReporting:Dashboard name="sales" />` | Un tableau de bord configuré : son titre, les dates de sa période, le choix de ses périodes, ses *widgets*. |
| `<twig:PhilReporting:LiveDashboard name="sales" />` | Le même, dont la période change sans recharger la page. |
| `<twig:PhilReporting:Metric source="orders" period="this_month" compare />` | Une mesure, avec ou sans tableau de bord. |
| `<twig:PhilReporting:Chart source="visits" chart="line" render="svg" />` | Une source de séries, avec ou sans tableau de bord. |

`Dashboard` reçoit la période choisie dans sa propriété `period`. Le choix des périodes est un formulaire `GET` dont le
champ s'appelle `period` : une page transmet donc la valeur de la requête.

```twig
<twig:PhilReporting:Dashboard name="sales" :period="app.request.query.get('period', '')" />
```

`LiveDashboard` garde la période dans l'adresse elle-même, comme propriété modifiable : un lien, un rechargement ou un
navigateur sans JavaScript affiche la même période. Une période venue de l'adresse n'est acceptée que parmi celles
qu'offre le tableau de bord ; toute autre valeur affiche la période propre au tableau de bord.

`Metric` et `Chart` reçoivent `title` (celui de la source s'il est vide), `period` (`last_30_days` par défaut) et
`locale` (la locale courante si elle est vide). `Metric` reçoit `compare` ; `Chart` reçoit `chart` et `render`.

## Les rendus d'un graphique

| `render` | Ce qui est dessiné |
|---|---|
| `svg` | L'image SVG de `phil/svg-chart`, puis le tableau de ses valeurs dans un élément `<details>`. |
| `html` | Des barres horizontales en HTML et en CSS, dans un tableau : une rangée par libellé, une colonne par série, chaque cellule avec sa barre et sa valeur. |
| `table` | Le tableau des valeurs seul. |

Aucun script ne dessine quoi que ce soit. Chaque rendu garde les valeurs lisibles sous forme de texte :

- l'image SVG porte `role="img"`, son titre, un `<desc>` qui lit chaque valeur, et un `<title>` sur chaque marque ;
- l'image se trouve dans une `<figure>` dont la `<figcaption>` est le titre du *widget*, et son tableau a une
  `<caption>` ;
- les barres sont un tableau avec des en-têtes de rangée et de colonne ; la barre est décorative, la valeur à côté est
  du texte, et une valeur négative est hachurée en plus d'être colorée ;
- les couleurs suivent la palette d'Okabe et Ito, que distinguent toutes les visions des couleurs courantes ;
- la variation d'une mesure s'écrit sous forme de phrase — « En hausse de 20 % par rapport à la période précédente » —,
  la flèche à côté étant cachée aux lecteurs d'écran.

## Un graphique en téléchargement PNG

Là où des graphiques PNG sont dessinés — l'extension `resvg` chargée et `png.fonts` listé, voir
[Configuration](configuration.md#les-graphiques-png) —, `ChartResponses` donne le graphique d'une source de séries sous
forme de fichier PNG, depuis un contrôleur de l'application, qui vérifie d'abord qui peut le voir :

```php
use Phil\ReportingBundle\Infrastructure\Http\ChartResponses;
use Phil\SvgChart\ChartType;

#[Route('/reports/visits.png')]
#[IsGranted('ROLE_MANAGER')]
public function visits(ChartResponses $charts, Periods $periods): Response
{
    return $charts->png('visits', $periods->period(PeriodPreset::Last30Days), ChartType::Line);
}
```

L'image est le graphique SVG de la page, dessiné à l'échelle configurée ; le fichier porte le nom de la source et des
premier et dernier jours de la période, `visits-20261009-20261107.png`, et est envoyé en pièce jointe avec
`Cache-Control: private, no-store` et `X-Content-Type-Options: nosniff`. Un lien vers lui n'est offert que lorsque
`$charts->hasPng()` est vrai : ailleurs, `png()` lève `PngUnavailable` avant que rien ne soit dessiné, et la page garde
son image SVG et son tableau. Une source inconnue échoue avec `NotFound`.

## Accès

Un tableau de bord qui nomme un `role` n'est montré qu'à un lecteur qui l'a, demandé au vérificateur d'autorisation de
Symfony à chaque rendu, y compris ceux d'un tableau de bord en direct ; il est refusé avec `AccessDenied` quand aucun
vérificateur n'est installé. L'application protège tout de même la page qui affiche un tableau de bord ; le rôle
empêche un tableau de bord placé sur une autre page de s'afficher.

Les composants `Metric` et `Chart` ne vérifient rien : ils montrent ce que donne leur source à quiconque voit la page.

## EasyAdmin

Quand EasyAdmin est installé, `ReportingAdmin::TEMPLATE` affiche un tableau de bord dans la mise en page de
l'interface d'administration. Ajoutez une action au contrôleur du tableau de bord, et son lien au menu :

```php
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin;

#[AdminRoute(path: '/reports/{dashboard}', name: 'reports')]
public function reports(string $dashboard): Response
{
    return $this->render(ReportingAdmin::TEMPLATE, ['dashboard' => $dashboard, 'live' => true]);
}

public function configureMenuItems(): iterable
{
    yield ReportingAdmin::menuItem('Sales', 'sales', 'admin_reports');
}
```

Avec `live: false`, la page affiche `PhilReporting:Dashboard` avec la `period` de la requête.

Le back-office charge ses propres ressources, pas celles des pages de l'application : déclarez-les dans
`configureAssets()` du contrôleur du tableau de bord. L'entrée `app` d'AssetMapper démarre Stimulus, dont le Live
Component a besoin pour changer la période sans recharger la page, et la feuille de style du bundle dessine les mesures
et les barres :

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

public function configureAssets(): Assets
{
    return Assets::new()
        ->addAssetMapperEntry('app')
        ->addCssFile('phil-reporting/reporting.css');
}
```
