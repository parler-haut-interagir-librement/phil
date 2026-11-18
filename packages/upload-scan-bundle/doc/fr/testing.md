<!-- phil-dev source-sha256=d3435fcc0a2ba1d74e02abc4cecaacf662b85703e1ee7f26651cbf71e74a2240 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le `UploadScanner` — chaque décision, l'analyse et la quarantaine du flux entier, où qu'il
soit positionné, un flux qui ne peut pas être relu, une quarantaine qui échoue, les événements —, le *handler* de
nouvelle analyse et son message, la quarantaine sur le système de fichiers, ses permissions pendant qu'un fichier est copié (jeu de données d'essai `WatchedStream`) et une fois conservé, un répertoire ouvert restreint ou refusé, deux fichiers conservés au même moment, sa fiche et ses échecs, aucun ne laissant de fichier,
son inventaire (l'ordre des fiches, leurs pages, une fiche illisible, des identifiants qui ne sont pas ceux de la
quarantaine, une suppression avec ou sans son fichier), la page d'administration rendue par Twig, ses pages, son
échappement, son jeton CSRF, son rôle et son journal, et le lien du menu EasyAdmin, la contrainte par le validateur de
Symfony, les adaptateurs des médias, des documents et de la surveillance, et le câblage du bundle, sur le
`ContainerBuilder` et sur un conteneur compilé, l'inventaire donné ou la page et la purge retirées par sa passe du compilateur, et la
route de `RescanFile` ajoutée à celle de Messenger ; la rétention, ses pages, sa borne, son essai à blanc et sa
commande ; `FileRescanned`.

La suite `tests/Functional` démarre une petite application — FrameworkBundle, TwigBundle, la sécurité de Symfony avec
HTTP Basic — et suit un fichier d'un bout à l'autre : envoyé à une page de téléversement qui le valide avec
`#[CleanFile]`, refusé et mis en quarantaine quand il est infecté, listé sur la page d'administration pour un
administrateur seulement, refusé à la suppression avec un jeton CSRF falsifié, supprimé pour de bon par le formulaire de
la page, et la commande de purge enregistrée. Elle s'exécute sur l'analyseur factice, ou sur clamd quand
`PHIL_UPLOAD_SCAN_CLAMD` en nomme un : le test appartient au groupe `clamd`.

`tests/Fuzz/quarantine-record.php`, lancé par `composer fuzz`, donne à la quarantaine sur le système de fichiers des
fiches et des identifiants que personne n'a écrits : une fiche de n'importe quel contenu se lit encore comme un fichier
de la quarantaine, et un identifiant que la quarantaine n'a pas donné ne nomme rien et ne supprime rien.

`ClamdTest`, dans `phil/upload-scan`, lance l'analyseur clamd contre un vrai clamd quand `PHIL_UPLOAD_SCAN_CLAMD` en
nomme un, et est sauté sinon. Depuis la racine du monodépôt qui contient `phil/upload-scan` :

```sh
docker run --rm -d --name clamav -p 3310:3310 clamav/clamav:stable   # its signatures load in a minute or two
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit packages/upload-scan/tests/Integration
```

La suite fonctionnelle du bundle s'exécute contre le même clamd :

```sh
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit --group clamd packages/upload-scan-bundle/tests
```

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/upload-scan-bundle/tests
```

## Tester une application qui analyse

Dans l'environnement de test, utilisez l'analyseur factice : aucun clamd, et la chaîne de test EICAR est détectée comme
clamd la détecterait.

```yaml
# config/packages/test/phil_upload_scan.yaml
phil_upload_scan:
    scanner: fake
    quarantine:
        directory: '%kernel.cache_dir%/quarantine'
```

```php
use Phil\UploadScan\FakeScanner;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$path = tempnam(sys_get_temp_dir(), 'eicar');
file_put_contents($path, FakeScanner::eicar());

$client->request('POST', '/manuscripts', files: ['manuscript' => ['file' => new UploadedFile($path, 'virus.docx', test: true)]]);

self::assertSelectorTextContains('.form-error', 'This file contains a threat and was refused.');
```

Pour tester le comportement pendant une panne de l'antivirus, enregistrez `new FakeScanner(error: 'down')` comme
service et nommez-le dans `scanner`.
