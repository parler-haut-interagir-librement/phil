<!-- phil-dev source-sha256=fd12ea9101713d0a141422a3a0bad097c227223673e56d5386c4aea5548049ca -->
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

Le magasin `directory` garde chaque tâche sous forme de fichier JSON et chaque export sous forme de fichier, nommés par
32 chiffres hexadécimaux aléatoires, dans un répertoire lisible par son seul propriétaire ; un identifiant d'une autre
forme n'atteint jamais le disque. Rien n'est désérialisé : un fichier que `ReportJob::toArray()` n'a pas écrit est
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
| `mail` | Un courriel à chaque destinataire, le fichier en pièce jointe, envoyé par les transports du mailer depuis le *worker* : la pièce jointe n'entre jamais dans une file d'attente. Le fichier est lu une seule fois, en entier. |
| `notification` | Une notification sur les canaux configurés de Notifier, avec le titre du rapport, sa période, son nombre de lignes et le nom du fichier ; aucun canal ne joint le fichier. |

Une livraison de l'application implémente `ReportDelivery` ; voir [Points d'extension](extension-points.md).
