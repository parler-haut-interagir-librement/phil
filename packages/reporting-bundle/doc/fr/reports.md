<!-- phil-dev source-sha256=0a9ceeb973260c262ba7110b309e7b05be2ee4b8f81773bc87898a7431ee0dc3 -->
# Rapports

## Une définition

```php
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportRequest;

final readonly class SalesReport implements ReportDefinition
{
    public function __construct(private Orders $orders) {}

    public static function key(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Sales';
    }

    public function columns(ReportRequest $request): array
    {
        return ['Order', 'Date', 'Total', 'Paid'];
    }

    public function rows(ReportRequest $request): iterable
    {
        foreach ($this->orders->iterateBetween($request->period->start, $request->period->end, $request->parameter('status')) as $order) {
            yield [$order->number, $order->placedAt->format('Y-m-d'), $order->total, $order->isPaid()];
        }
    }
}
```

Les lignes sont lues à mesure qu'elles sont écrites : produisez-les à partir d'une requête parcourue ligne par ligne,
et un export ne garde qu'une ligne à la fois en mémoire. Chaque ligne a une cellule par colonne — une chaîne, un
entier, un nombre à virgule flottante, un booléen ou `null` —, sinon l'export s'arrête avec `InvalidReportJob`.
Au-delà de `max_rows`, il s'arrête avec `ReportTooLarge`.

Deux rapports sont fournis avec le bundle dès que l'application branche un système d'apprentissage : les
[heures et coûts de formation](training.md) et le [gabarit du 1 % de la masse salariale](quebec-one-percent.md).

Un `ReportRequest` est la période et les paramètres : au plus 32 scalaires sous des clés de 1 à 64 lettres, chiffres,
points, tirets ou traits de soulignement, chaque texte comptant au plus 200 caractères.

## Les formats

| Clé | Fichier | Écrit |
|---|---|---|
| `csv` | `text/csv`, `.csv` | RFC 4180 : virgules, guillemets doubles, CRLF. |
| `tsv` | `text/tab-separated-values`, `.tsv` | Tabulations, avec les guillemets du CSV. |
| `json` | `application/json`, `.json` | Un tableau d'objets indexés par les titres, une ligne du rapport par ligne du fichier. |

En CSV et en TSV, une cellule de texte qui commence par `=`, `+`, `-`, `@`, une tabulation ou un retour de chariot
est précédée d'une apostrophe : une valeur qu'un utilisateur a tapée ne s'exécute ainsi jamais comme une formule quand
un tableur ouvre le fichier. Le JSON garde les valeurs telles quelles, avec les booléens et les nombres typés.

Le fichier porte le nom du rapport et les premier et dernier jours de sa période : `sales-20261001-20261031.csv`.

## Un téléchargement

`ReportResponses` diffuse un rapport depuis un contrôleur de l'application, qui vérifie d'abord qui peut le lire :

```php
#[Route('/reports/sales.csv')]
#[IsGranted('ROLE_MANAGER')]
public function sales(ReportResponses $responses, Periods $periods): StreamedResponse
{
    return $responses->download('sales', 'csv', new ReportRequest($periods->period(PeriodPreset::LastMonth)));
}
```

Un rapport ou un format inconnu échoue avec `NotFound` avant que rien ne soit envoyé. La réponse est une pièce jointe,
avec `Cache-Control: private, no-store` et `X-Content-Type-Options: nosniff`.

Le rapport est écrit dans la réponse au fur et à mesure qu'il est lu, ligne par ligne : un long rapport démarre aussitôt
et ne garde pas plus d'une ligne en mémoire. Derrière FrankenPHP, Caddy coupe une écriture qui reste bloquée une
minute — un client qui cesse de lire —, et le fichier arrive tronqué. Un rapport trop long pour être téléchargé pendant
que le lecteur attend passe plutôt par [le *claim check*](#dans-un-worker--le-claim-check).

## La commande

```sh
bin/console phil:reporting:export                       # lists the reports
bin/console phil:reporting:export sales --format=json --period=this_month
bin/console phil:reporting:export sales --output=var/sales.csv
```

Sans `--output`, le fichier va sur la sortie standard. Un fichier existant n'est jamais écrasé.

## Dans un *worker* : le *claim check*

`ReportQueue::queue(ReportJob)` garde la tâche dans le magasin de tâches et répartit `RunReport`, qui ne porte que
l'identifiant de la tâche : la requête, les paramètres et les destinataires ne voyagent jamais dans la file d'attente.

```php
$queue->queue(new ReportJob('sales', 'csv', new ReportRequest($period, ['status' => 'paid']), 'mail', ['manager@example.com']));
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\ReportingBundle\Application\Message\RunReport: async
            Phil\ReportingBundle\Application\Message\RunScheduledReport: async
```

Dans le *worker*, `ReportRunner` lit la tâche, écrit le rapport dans un flux temporaire, garde le fichier dans le
magasin d'exports et le livre quand la tâche nomme une livraison. Il oublie ensuite le fichier livré — sauf si
`keep_delivered` est activé — et la tâche, puis répartit `ReportExported` avec les identifiants de la tâche et du
fichier. Une tâche sans livraison laisse son fichier dans le magasin d'exports, pour que l'application le remette par
son identifiant.

| Échec | Ce qui se passe |
|---|---|
| Un échec du bundle : la tâche disparue, un rapport, un format ou une livraison inconnus, trop de lignes, une ligne mal formée | `UnrecoverableMessageHandlingException` : jamais de nouvelle tentative ; la tâche est oubliée avec ses destinataires. |
| Une livraison qui échoue : un serveur de courriel injoignable | Nouvelle tentative par Messenger ; le fichier de la tentative est retiré, la tâche reste. |
| Un export qui ne peut pas être écrit : le fichier non créé, ou son flux non vidé et fermé, comme sur un disque plein | `ExportFailed`, nouvelle tentative par Messenger ; le fichier tronqué est retiré, la tâche reste. |

Le magasin `directory` garde chaque tâche sous forme de fichier JSON et chaque export sous forme de fichier, nommés par
32 chiffres hexadécimaux aléatoires, dans un répertoire lisible par son seul propriétaire ; un identifiant d'une autre
forme n'atteint jamais le disque. Chaque fichier est écrit sous forme de fichier temporaire du répertoire, `.phil-`
suivi de caractères aléatoires, créé en `0600` par `tempnam()`, puis reçoit son nom en une seule étape une fois entier :
aucun autre utilisateur ne lit une tâche ou un export à aucun moment, et un fichier tronqué n'est jamais un fichier du
magasin. Rien n'est désérialisé : un fichier que `ReportJob::toArray()` n'a pas écrit est
refusé. Le magasin `memory` sert aux tests et au transport `sync://`, et il est vidé quand le conteneur est
réinitialisé.

## Selon un calendrier

Chaque calendrier configuré devient un `RunScheduledReport` récurrent dans le calendrier `phil_reporting` de Symfony
Scheduler, tous les `every` à partir de `from`. Son *handler* construit la tâche sur la période du calendrier à ce
moment et la met en file d'attente.

```sh
bin/console messenger:consume scheduler_phil_reporting async
```

## Les livraisons

| Clé | Livre |
|---|---|
| `mail` | Un courriel à chaque destinataire, le fichier en pièce jointe, envoyé par les transports du mailer depuis le *worker* : la pièce jointe n'entre jamais dans une file d'attente. Le fichier est lu une seule fois, en entier. Le graphique d'un `ChartedReport` est l'image du courriel là où des graphiques PNG sont dessinés. |
| `notification` | Une notification sur les canaux configurés de Notifier, avec le titre du rapport, sa période, son nombre de lignes et le nom du fichier ; aucun canal ne joint le fichier. |

Une livraison de l'application implémente `ReportDelivery` ; voir [Points d'extension](extension-points.md).

### Le graphique d'un rapport dans son courriel

Un rapport qui implémente aussi `ChartedReport` nomme le graphique qui l'accompagne : une source de séries, dessinée sur
la période du rapport, en barres par défaut.

```php
final class SalesReport implements ReportDefinition, ChartedReport
{
    // key(), title(), columns(), rows()…

    public function chart(): ReportChart
    {
        return new ReportChart('sales_by_day', ChartType::Line);
    }
}
```

Là où des graphiques PNG sont dessinés — voir [Configuration](configuration.md#les-graphiques-png) —, la livraison
`mail` ajoute une partie HTML au courriel : le texte, puis le graphique en image PNG affichée en place (`cid:chart.png`),
dont le `alt` est le titre du graphique ; le fichier reste en pièce jointe. Les clients de courriel n'affichent pas le
SVG : il n'y a donc pas de repli SVG. Sans l'extension ni ses polices, ou quand l'image ne peut pas être dessinée, le
courriel est le texte et son fichier, comme pour tout rapport. Une source qui n'existe pas fait échouer la livraison avec
`NotFound`, sans nouvelle tentative.
