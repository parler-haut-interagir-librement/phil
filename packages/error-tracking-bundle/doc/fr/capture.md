<!-- phil-dev source-sha256=042dec845e069c693b424c893779f0fa051c4f265ba5125a71db7d667bbe3211 -->
# Capture

Les points de capture ci-dessous sont l'interface du bundle : une application y déclare ses erreurs, et par
`SafeCapture` pour ses propres appels. Ils n'exigent aucun SDK et ne lisent aucun format tiers ; une application qui
quitte un SDK de Sentry le remplace par eux, comme le montre [Migrer depuis sentry-symfony](migrating-from-sentry.md).

## Les points de capture

| Point | Service | Source | Étiquettes |
|---|---|---|---|
| L'exception sur laquelle se termine une requête, `kernel.exception` à la priorité 128 | `ExceptionListener` | `http` | la requête réduite |
| Un message en échec dans un *worker*, `WorkerMessageFailedEvent`, avec le *handler* que nomme `HandlerFailureEvent` | `WorkerFailureListener` | `messenger` | `message`, `transport`, `will_retry`, `handler` quand il est connu |
| L'erreur sur laquelle se termine une commande, `console.error` | `ConsoleErrorListener` | `console` | `command`, `exit_code` |
| Un enregistrement de journal du niveau du gestionnaire et au-dessus | `phil_error_tracking.monolog_handler` | `log` | `channel` |
| Le rapport d'un navigateur | `BrowserErrorController` | `browser` | la page et le `User-Agent` |
| L'appel propre à l'application | `SafeCapture::throwable()` ou `ErrorTracker` | `manual` par défaut | les siennes |

L'écouteur des requêtes s'exécute avant que le noyau journalise l'exception, pour que l'événement porte la requête ;
l'enregistrement de journal de la même exception qui suit n'est pas capturé de nouveau.

Une exception HTTP sous 500 (une 404, une 403) est laissée de côté, sauf si `capture.http.client_errors` vaut true. Un
message en échec est capturé une fois qu'il ne sera plus retenté, sauf si `capture.messenger.retries` vaut true ;
l'exception de chaque *handler* est capturée, un événement pour chaque *handler* qui a échoué, plutôt que la
`HandlerFailedException` de Messenger, pour qu'elle se groupe comme elle le ferait dans une requête. Le
`HandlerFailureEvent` de Messenger nomme le *handler* de chaque exception, comme `App\Handler\ExportHandler::__invoke`,
que l'événement porte sous `handler` ; l'écouteur garde ces noms faiblement, pour le seul message en cours de
traitement, et les oublie à `kernel.reset`. Un *handler* qui échoue dans une requête, sur un bus réparti de façon
synchrone, passe plutôt par la capture de la requête. Les arguments d'une commande ne sont jamais gardés.

Le gestionnaire Monolog capture l'exception de la clé `exception` de l'enregistrement, ou sinon l'enregistrement
lui-même, comme un événement de type `Log record` dont le message est celui de l'enregistrement, avec ses espaces
réservés quand aucun processeur ne les a remplacés : `Order {id} failed` se groupe ainsi en un seul groupe.
`critical` et au-dessus sont fatals, `error` est une erreur, et ce qui est en dessous est un avertissement.

Un processeur qui s'exécute avant les gestionnaires peut avoir remplacé l'exception par un tableau de sa `class`, de
son `message`, de son `file`, de sa `line` et de son exception `previous`, comme le fait le caviardage des journaux de
`phil/privacy-bundle`. Le gestionnaire lit ce tableau comme la chaîne qu'il décrit, chaque exception avec le seul cadre
de son fichier et de sa ligne, pour que l'événement garde sa classe et son emplacement et se groupe par eux plutôt que
comme un `Log record`. L'enregistrement d'une exception que l'écouteur de requête a déjà capturée reste laissé de
côté : la classe, le fichier et la ligne de chaque throwable capturé sont retenus jusqu'à la réinitialisation des
services, et le premier tableau qui correspond à l'un d'eux est pris pour lui.

## Les arguments dans les messages

Un cadre garde son fichier, sa ligne et sa fonction, jamais les valeurs de ses arguments. PHP 8.6 ajoute
`error_include_args`, désactivé par défaut : activé, PHP écrit les arguments des appels dans les messages de ses
erreurs, sauf les paramètres marqués `#[\SensitiveParameter]`. Un message est capturé tel quel : un argument que PHP y a
écrit — une adresse, un jeton que le code a oublié de marquer — parvient donc au magasin, où seuls les motifs de
l'assainisseur lui barrent la route. Gardez `error_include_args` désactivé en production, et marquez
`#[\SensitiveParameter]` les paramètres qui portent des secrets, quelle que soit sa valeur.

## La garde

`SafeCapture` est la seule entrée des points de capture vers le traqueur :

- elle ne lève jamais d'exception : une capture ratée (la base de données est en panne, le clavardage est
  injoignable) retourne `null` ;
- pendant qu'une capture s'exécute, toute autre capture est refusée : l'avertissement qui journalise une capture ratée
  atteint le gestionnaire Monolog, qui demande une capture, laquelle est refusée, et la boucle ne commence jamais ;
- l'échec est journalisé une fois, comme un avertissement qui ne nomme que sa classe ;
- un throwable déjà capturé n'est pas capturé de nouveau avant la réinitialisation des services, à la fin de chaque
  requête d'un *worker* (`kernel.reset`), ni la première exception qu'un processeur de journal a transformée en
  tableau avec sa classe, son fichier et sa ligne ;
- les classes de `ignored_exceptions` et leurs sous-classes ne sont jamais capturées.

## Vos propres captures

```php
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTrackingBundle\Application\SafeCapture;

public function __construct(private SafeCapture $capture) {}

try {
    $this->exporter->run($order);
} catch (ExportFailed $failure) {
    $this->capture->throwable($failure, new CaptureContext(context: ['order' => $order->id]));
}
```
