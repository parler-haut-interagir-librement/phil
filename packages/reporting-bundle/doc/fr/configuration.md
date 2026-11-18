<!-- phil-dev source-sha256=6b9dba88373a7c9efd705d347557fca925b70f1716e1479f5c109addcfdb80ce -->
# Configuration

```yaml
# config/packages/phil_reporting.yaml
phil_reporting:
    timezone: America/Toronto        # the periods are whole days of this time zone; UTC by default
    chart: { width: 640, height: 320 } # the SVG images, 100 to 4000 pixels; they scale to their container
    png:                             # the charts as PNG images too, where the resvg extension is loaded
        fonts: ['%kernel.project_dir%/assets/fonts/Inter-Regular.ttf']
        scale: 2                     # 1 to 4; 2 for screens of high density
        max_pixels: 16777216         # the largest image, width times height
    max_rows: 100000                 # the most rows a report exports, 1 to 10 000 000
    store: directory                 # or memory, for the tests and the sync transport
    directory: '%kernel.project_dir%/var/phil_reporting'
    tighten_open_directory: true     # false refuses a directory open to other users instead of narrowing it
    keep_delivered: false            # whether a delivered export stays in the export store
    mail:
        enabled: true
        from: 'Reports <reports@example.com>'
    notification:
        enabled: true
        channels: [chat/slack]       # the importance's channels when empty
    dashboards:
        sales:
            title: Sales
            role: ROLE_MANAGER       # none by default: everyone may see it
            period: last_30_days
            periods: [last_7_days, last_30_days, this_month, last_month]
            widgets:
                - { metric: orders, compare: true }
                - { metric: revenue, title: Revenue }
                - { series: visits, chart: line }
                - { series: sales_by_category, render: html }
                - { series: refunds, render: table }
    schedules:
        monthly_sales:
            report: sales
            format: csv
            every: 1 month
            from: '2026-12-01 06:00'
            period: last_month
            delivery: mail
            recipients: [manager@example.com]
            parameters: { status: paid }
```

## Les tableaux de bord

| Clé | Par défaut | Sens |
|---|---|---|
| `title` | obligatoire | Le titre du tableau de bord. |
| `role` | `null` | Le rôle dont un lecteur a besoin. Un tableau de bord qui en nomme un est refusé quand aucun vérificateur d'autorisation n'est installé. |
| `period` | `last_30_days` | La période montrée d'abord ; elle doit figurer dans `periods`. |
| `periods` | `[period]` | Les périodes parmi lesquelles le lecteur choisit, dans leur ordre ; les doublons sont retirés. |
| `widgets` | `[]` | Les tuiles, dans leur ordre. |

Un tableau de bord est nommé par 1 à 64 lettres, chiffres, points, tirets ou traits de soulignement. Les périodes sont
`today`, `yesterday`, `last_7_days`, `last_30_days`, `this_month`, `last_month`, `last_12_months`, `this_year` et
`last_year`.

Un *widget* nomme une source `metric` ou une source `series` par sa clé :

| Clé | Par défaut | Sens |
|---|---|---|
| `metric` / `series` | — | La clé de la source ; exactement l'une des deux. |
| `title` | celui de la source | Le titre du *widget*. |
| `compare` | `false` | Si une mesure affiche sa variation par rapport à la période précédente. |
| `chart` | `bar` | `bar`, `line`, `pie` ou `doughnut`, pour l'image SVG. |
| `render` | `svg` | `svg`, `html` ou `table` ; voir [Tableaux de bord](dashboards.md). |

## Les calendriers

| Clé | Par défaut | Sens |
|---|---|---|
| `report` | obligatoire | La clé du rapport. |
| `format` | `csv` | La clé du format. |
| `every` | obligatoire | La fréquence, dans un format de date relative : `1 day`, `1 week`, `1 month`. |
| `from` | `null` | Le premier moment, `06:00` ou `2026-12-01 06:00` ; le démarrage du planificateur s'il est nul. |
| `period` | `last_month` | La période prédéfinie que couvre le rapport, au moment où il s'exécute. |
| `delivery` | `null` | `mail`, `notification` ou une livraison de l'application ; l'export reste dans le magasin s'il est nul. |
| `recipients` | `[]` | Ce que lit la livraison : des adresses courriel pour `mail` et `notification`. |
| `parameters` | `{}` | Les paramètres du rapport, des scalaires sous des clés de 1 à 64 lettres, chiffres, points, tirets ou traits de soulignement. |

Un calendrier est nommé comme un tableau de bord. Les destinataires sont gardés dans le magasin de tâches avec la
tâche, jamais dans le message.

## Les graphiques PNG

Les clients de courriel n'affichent pas les images SVG, et un graphique téléchargé pour être collé dans un document est
voulu en image matricielle. Là où l'extension PHP `resvg` est chargée, les graphiques sont aussi dessinés en images PNG,
par le `ResvgRasterizer` de phil/svg-chart :

| Clé | Défaut | Sens |
|---|---|---|
| `fonts` | `[]` | Les fichiers TrueType ou OpenType avec lesquels les images sont dessinées ; la famille du premier dessine chaque texte. Aucune police du système n'est lue : sans polices, il n'y a pas de PNG. |
| `scale` | `2` | L'échelle des images, de 1 à 4 : un graphique de 640 × 320 est dessiné en 1280 × 640 par défaut. |
| `max_pixels` | `16777216` | La plus grande image, sa largeur fois sa hauteur, jusqu'à 67 108 864 ; une image plus grande est refusée avant d'être dessinée. |

Le rasterizer est enregistré quand `fonts` liste un fichier et que l'extension est chargée là où le conteneur est
compilé : compilez-le avec le PHP qui sert l'application. Sans l'extension ni les polices, rien n'est enregistré, les
graphiques restent des images SVG, et le PNG n'est explicitement pas offert : `ChartResponses::hasPng()` est faux et
`ChartResponses::png()` lève `PngUnavailable`, et le courriel d'un rapport part avec son fichier seulement. Quand aucune
police n'est listée, un service `Phil\SvgChart\Contract\Rasterizer` de l'application dessine les images, s'il en
enregistre un.

## Les livraisons

`mail` a besoin de son adresse `from` ; elle est enregistrée quand Mailer est installé et que `mail` est activé. Le
graphique d'un rapport qui en a un est l'image de son courriel quand des graphiques PNG sont dessinés ; voir
[Rapports](reports.md#le-graphique-dun-rapport-dans-son-courriel). `notification` est enregistrée quand Notifier est installé et que `notification` est activé.
