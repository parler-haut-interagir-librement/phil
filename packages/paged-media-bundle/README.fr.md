<!-- phil-dev source-sha256=33ac903be81d1e4848429b1e435d4ea4010a610d4d2077ba83a6673eba74861d -->
# phil/paged-media-bundle

**Des PDF dans une application Symfony : dessinés par un moteur d'écriture natif ou travaillés par Gotenberg, par un
seul service, tout de suite ou dans un *worker* de Messenger.**

phil/paged-media-bundle intègre `phil/paged-media` à une application Symfony. Un seul service, `PagedMedia`, produit le
rendu des documents du moteur d'écriture natif de la bibliothèque. Quand des étapes sont données, il les envoie ensuite
à Gotenberg pour les rendre conformes à PDF/A, les estampiller, les signer ou les chiffrer. Les PDF et les documents
bureautiques vont directement à Gotenberg. La configuration donne l'URL et les identifiants de connexion de Gotenberg,
le certificat qui signe les PDF — un fichier, un secret gardé sous forme de texte ou HashiCorp Vault — et l'autorité
d'horodatage. Les gabarits de documents s'enregistrent par un attribut. `PagedMediaQueue` produit le rendu d'un gabarit,
exécute des étapes ou convertit des documents bureautiques dans un *worker* de Messenger, garde le fichier et
l'annonce. Ses messages ne transportent que l'identifiant d'une tâche gardée à part, jamais un document, un paramètre ou
des renseignements personnels.

Il s'adresse à toute application Symfony qui imprime : des reçus et des billets dessinés par le moteur d'écriture
natif, des factures archivées, des certificats signés, des documents bureautiques transformés en PDF, un livre dont les
pages reviennent estampillées et conformes.

C'est un bundle bâti au-dessus de `symfony/config` et de `symfony/dependency-injection`. Symfony HttpClient,
Messenger et un répartiteur d'événements sont facultatifs. Sans l'URL de Gotenberg, seul le moteur d'écriture natif
est disponible. Le *handler* des messages et la file d'attente sont enregistrés quand Messenger est installé, et
l'événement est réparti quand un répartiteur est présent.

La conception est sobre : `PhilPagedMediaBundle` lit la configuration, `PagedMediaServices` enregistre les services,
`PagedMedia` est le service unique, `PagedMediaQueue` garde les tâches et répartit leurs messages, `PagedMediaHandler`
les traite. `JobStore` est le port où attendent les tâches et `PdfStorage` le port où leurs fichiers sont gardés,
chacun étant un répertoire par défaut.

## État

| Partie | État |
|---|---|
| Configuration : URL, identifiants de connexion, délai et client HTTP de Gotenberg | Implémenté et testé |
| `PagedMedia` : le moteur d'écriture natif et Gotenberg derrière un seul service ; les gabarits par nom | Implémenté et testé |
| HTML et CSS par le moteur d'impression de l'application ; `PrintSubject`, `PrintStarting` et `UnnamedPrintStarting` avant une impression | Implémenté et testé |
| `PrintedNotes` : les notes d'un document UJO (Universal JSON Output) en bas de page, avec `phil/footnotes-sidenotes` | Implémenté et testé |
| Signature : la source du certificat (fichier, en ligne, coffre), l'autorité d'horodatage et ce que disent les signatures | Implémenté et testé |
| Messenger : `PagedMediaQueue`, et `RenderPdf`, `RunPipeline` et `ConvertDocuments` qui ne transportent que l'identifiant d'une tâche, avec nouvelle tentative quand l'échec peut être passager | Implémenté et testé |
| `JobStore`, son répertoire et `JobCodec` : les tâches en JSON, hors des messages | Implémenté et testé |
| `CacheJobStore` : les tâches dans un pool de cache PSR-6 partagé par chaque *worker* | Implémenté et testé |
| Les gabarits d'imprimés de la bibliothèque comme gabarits de documents — porte-noms, étiquettes, cartes-cadeaux et de téléchargement, calendriers, gros caractères — dessinés à partir de paramètres | Implémenté et testé |
| `FailedJobPurger` : la tâche d'un message en échec définitif est oubliée, sauf si `jobs.remove_failed` vaut false | Implémenté et testé |
| `PdfStorage` et son répertoire, et l'événement `PdfStored` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et activation du bundle, exécution de Gotenberg. |
| [Configuration](doc/fr/configuration.md) | Chaque option et les services qu'elle donne. |
| [Rendu](doc/fr/rendering.md) | `PagedMedia`, les gabarits de documents, le HTML et les notes, qui peut imprimer, les signatures et l'horodatage. |
| [Gabarits d'imprimés](doc/fr/templates.md) | Les porte-noms, étiquettes, cartes, calendriers et gros caractères de la bibliothèque, par nom et paramètres. |
| [Messenger](doc/fr/messenger.md) | La file d'attente, les trois messages, le stockage des tâches, le stockage des fichiers, l'événement et ce qui fait l'objet d'une nouvelle tentative. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le bundle, en plus des exceptions de la bibliothèque. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/paged-media-bundle symfony/http-client
```

```yaml
# config/packages/phil_paged_media.yaml
phil_paged_media:
    gotenberg:
        url: '%env(GOTENBERG_URL)%'
    signing:
        certificate: { type: file, path: '%kernel.project_dir%/config/signing/signer.p12', password: '%env(SIGNING_PASSWORD)%' }
        reason: Receipt
```

```php
#[AsDocumentTemplate('receipt')]
final readonly class ReceiptTemplate implements DocumentTemplate
{
    public function build(array $parameters): Document { /* draw with the native writer */ }
}

$output = $pagedMedia->renderTemplate('receipt', ['order' => 'R-42'], 'R-42.pdf', new Pipeline(new Remediate(Profile::PdfA3b)));
$job = $queue->render('receipt', ['order' => 'R-42'], 'receipts/R-42.pdf', sign: true);
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/paged-media`. Symfony HttpClient et un Gotenberg 9, pour les étapes et
les conversions ; Symfony Messenger, pour la file d'attente. `ext-sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
