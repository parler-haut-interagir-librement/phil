<!-- phil-dev source-sha256=e0cf0a5cac6cbb1826b843f22ff66d588c076da104dd1a5cbd07f0baa6033ca8 -->
# Gotenberg

`Gotenberg\Gotenberg` est un client de [Gotenberg](https://gotenberg.dev) 9, bâti sur Symfony HttpClient. Il
envoie les étapes PDF — en liste sur `/forms/pdf/pipe`, ou seules sur leur propre route —, la conversion LibreOffice,
et les routes qui lisent un PDF. Chaque requête est vérifiée avant d'être envoyée : les options d'une étape à la
construction de l'étape, l'ordre d'une liste à la construction de la liste, les fichiers à l'écriture de la requête.
Une réponse qui n'est pas un succès devient une exception.

Les routes Chromium ne sont pas couvertes : un moteur de média paginé choisi par l'application met en page le HTML et
le CSS.

## Le client

```php
use Phil\PagedMedia\Gotenberg\BasicAuth;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Symfony\Component\HttpClient\HttpClient;

$gotenberg = new Gotenberg(HttpClient::create(), 'http://gotenberg:3000');
$gotenberg = new Gotenberg($httpClient, 'https://pdf.example.com/gotenberg', new BasicAuth('app', $password), timeout: 120);
```

L'URL est l'URL de base de Gotenberg, avec son chemin racine quand il s'exécute derrière un tel chemin
(`--api-root-path`). C'est une adresse de service du réseau de l'application : absolue, en `http` ou en `https`, avec
un hôte — un nom simple comme `gotenberg`, une adresse IP et `localhost` compris — et sans identifiants, qui vont dans
`BasicAuth`. Elle est lue par l'analyseur d'URL WHATWG de l'extension URI de PHP ; toute autre est refusée avec
`InvalidRequest`. `BasicAuth` correspond à un Gotenberg démarré avec `--api-enable-basic-auth`. Le délai borne chaque
requête, en secondes ; sans lui, celui du client HTTP s'applique. La limite propre à Gotenberg (`--api-timeout`,
30 secondes par défaut) tient toujours : augmentez les deux pour les longs documents.

`version()` renvoie la version du Gotenberg joint, par exemple `9.1.0`.

## Fichiers

Une requête travaille sur des `Files` : des fichiers téléversés sous `files`, des archives ZIP que Gotenberg
décompresse, et des URL qu'il télécharge d'abord.

```php
use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;

$files = Files::of(
    InputFile::fromPath('/var/data/1-report.pdf'),          // read in chunks when the request is sent
    InputFile::fromContents('2-annex.pdf', $annexBytes),     // held in memory
    InputFile::fromDocument('3-receipt.pdf', $document),     // a Document of the native writer, rendered now
)
    ->withArchive(InputFile::fromPath('/var/data/scans.zip'))
    ->withDownload(new Download('https://files.example.com/a.pdf', ['Authorization' => 'Bearer …']));
```

Les noms comptent. Gotenberg prend les fichiers dans l'ordre alphanumérique de leurs noms — `merge` les ajoute bout à
bout dans cet ordre —, nomme ses résultats d'après eux, et une étape nomme les fichiers dont elle a besoin par leurs
noms. Un nom ne contient ni barre oblique, ni guillemet, ni saut de ligne. Un fichier dont une étape a besoin, comme une
image à estampiller, est donné à l'étape, qui le téléverse à côté des PDF ; Gotenberg ne le traite pas comme un PDF.
Le même fichier nommé deux fois est envoyé une seule fois ; deux fichiers différents du même nom sont refusés avec
`InvalidRequest`.

Un téléchargement est une adresse `http` ou `https` publique dont le serveur répond avec un en-tête
`Content-Disposition` qui nomme le fichier. L'adresse est lue par `phil/url`, qui refuse les identifiants, une adresse
IP et un nom d'hôte simple comme `storage` : Gotenberg la va chercher depuis le réseau où il s'exécute, et un document
ne doit pas le mener vers un service qui n'est pas public. Un fichier du réseau de l'application est téléversé à la
place. L'exploitant de Gotenberg décide aussi quels hôtes il peut joindre (`--api-download-from-allow-list`,
`--api-download-from-deny-private-ips`) ; une URL qu'il bloque répond 403.

## Produire des fichiers

```php
$result = $gotenberg->pipe($files, new Pipeline(new Merge(), new Optimize()));
$result = $gotenberg->run($files, new Rotate(90, 'odd'));              // one stage, on /forms/pdf/rotate
$result = $gotenberg->convert($documents, new LibreOfficeOptions(), new Pipeline(new Remediate(Profile::PdfA3b)));
```

`pipe()` envoie la liste dans le champ `stages` ; `run()` envoie l'étape seule à sa route, ses options comme champs de
formulaire — un texte tel quel, une liste ou un objet en JSON. [Étapes](stages.md) liste chaque étape et les règles
d'ordre, [LibreOffice](libreoffice.md) la conversion.

`RequestOptions` règle les en-têtes d'une requête qui produit des fichiers :

```php
use Phil\PagedMedia\Gotenberg\RequestOptions;

$gotenberg->pipe($files, $pipeline, new RequestOptions(outputFilename: 'facture-été', trace: 'order-42'));
```

`outputFilename` nomme le résultat sans extension : Gotenberg ajoute la bonne. `trace` est l'identifiant de la requête
dans les journaux de Gotenberg, renvoyé dans `Result::$trace`.

## Le résultat

Un `Result` est un seul fichier — un PDF, une image, un texte — ou un ZIP de plusieurs, quand plusieurs PDF sortent ou
qu'un `split` a été exécuté.

| Membre | Ce qu'il contient |
|---|---|
| `contents()` | Les octets, lus en entier |
| `saveTo($path)` | Écrit les octets dans un fichier au fur et à mesure qu'ils arrivent, sans les garder en mémoire |
| `$contentType`, `isZip()` | `application/pdf`, un type d'image, `text/plain` ou `application/zip` |
| `$filename` | Le nom tiré de `Content-Disposition` : `report.pdf`, `report.docx.pdf`, `report_1.png` |
| `$residual`, `$rolledBack`, `conforms()` | Après `remediate` : les violations restantes et les correctifs annulés ([Conformité](conformance.md)) |
| `$trace`, `$version` | L'identifiant de la requête et la version de Gotenberg qui a répondu |
| `$deferred` | Vrai quand un *webhook* reçoit le résultat : le corps est vide |

## Webhooks

Un `Webhook` fait répondre Gotenberg 204 tout de suite, téléverser le résultat vers une URL, puis envoyer un événement
à une autre. Les deux sont des adresses de service, comme l'est l'URL de Gotenberg : l'application les reçoit
d'ordinaire sur son propre réseau.

```php
use Phil\PagedMedia\Gotenberg\Webhook;
use Phil\PagedMedia\Gotenberg\WebhookMethod;

$result = $gotenberg->pipe($files, $pipeline, new RequestOptions(trace: 'order-42', webhook: new Webhook(
    'https://app.example.com/pdf/upload',
    'https://app.example.com/pdf/events',
    WebhookMethod::Put,
    ['Authorization' => 'Bearer …'],
)));
// $result->deferred === true
```

Le téléversement porte les en-têtes `Content-Type`, `Content-Disposition`, `Gotenberg-Trace` et de remédiation du
résultat ; l'événement est un `POST` JSON que lit `WebhookEvent::fromJson()` :

```php
$event = WebhookEvent::fromJson($request->getContent());
$event->success;          // webhook.success or webhook.error
$event->correlationId;    // the trace of the request
$event->errorStatus;      // with webhook.error: the status the request would have answered
$event->errorMessage;
```

Les routes de lecture répondent directement en JSON et n'acceptent aucun *webhook*.

## Échecs

Une requête refusée avant d'être envoyée lève `InvalidStage`, `InvalidPipeline` ou `InvalidRequest`. Une réponse qui
n'est pas un succès lève un `GotenbergFailure`, qui garde le statut HTTP — `0` quand Gotenberg n'a pas pu être joint —,
le message qu'a écrit Gotenberg, et la trace de la requête :

| Statut | Exception | `isRetryable()` |
|---|---|---|
| 400, 405, 413, 415, autres 4xx | `RequestRefused` | non |
| 401, 403 | `AccessDenied` | non |
| 404 | `RouteUnavailable` : le moteur n'est pas dans l'image, ou ses routes sont désactivées | non |
| 429, 503, aucune réponse | `ServiceUnavailable` | oui |
| 409, 500, autres 5xx | `ConversionFailed` | non |
| 200 avec un JSON d'une autre forme | `UnexpectedResponse` | non |

```php
try {
    $gotenberg->run($files, new Select('9'));
} catch (RequestRefused $refused) {
    // "Gotenberg answered 400: Stage 'select' failed on 'report.pdf': invalid page range: page 9 out of range [1, 3]"
}
```

[Exceptions](exceptions.md) les liste avec les autres exceptions de la bibliothèque.
