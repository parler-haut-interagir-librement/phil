<!-- phil-dev source-sha256=c22b2dfc675d2f7b958320d1f640fcd6fae7b6d27beb04bdbc075a6cdf58987e -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses. |
| `ext-sodium` | | Les identifiants aléatoires de la quarantaine, écrits en temps constant. |
| Symfony | 8.2 | Le conteneur, le validateur, les fichiers de HttpFoundation. |
| `phil/upload-scan` | `^0.1` | Les analyseurs et la politique. |
| clamd | toute version de ClamAV prise en charge | L'antivirus, sur un socket Unix ou un port TCP : voir [ClamAV](clamav.md). |

Facultatifs :

| Paquet | Pour |
|---|---|
| `symfony/messenger` | Les nouvelles analyses sur un *worker*. |
| `phil/media-bundle` | Les originaux de la médiathèque, analysés avant d'être stockés. |
| `phil/document-import-bundle` | Les documents, analysés avant d'être importés, par sa propre option `scan`. |
| `phil/monitoring-bundle` | Le contrôle de santé de l'antivirus. |
| `twig/twig` et `symfony/security-csrf` | La page d'administration de la quarantaine, dont les suppressions exigent un jeton CSRF : voir [CSRF](csrf.md). |
| `easycorp/easyadmin-bundle` | `QuarantineAdmin`, le lien d'un menu EasyAdmin vers cette page. |

## Enregistrer le bundle

```sh
composer require phil/upload-scan-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\UploadScanBundle\PhilUploadScanBundle::class => ['all' => true],
];
```

Dès que Twig et `symfony/security-csrf` sont tous deux installés, pour quelque raison que ce soit, le bundle enregistre la
page d'administration de la quarantaine et vérifie un jeton CSRF sans état sur ses suppressions. `csrf_protection` de
FrameworkBundle doit alors rester activé ; une application qui le désactive règle `phil_upload_scan.admin.enabled` à
`false`. Voir [CSRF](csrf.md).

## Une première analyse

Si clamd écoute sur `tcp://127.0.0.1:3310`, la valeur par défaut, rien d'autre n'est nécessaire. Placez `#[CleanFile]`
sur un téléversement ([Validation](validation.md)), ou injectez le `UploadScanner` :

```php
use Phil\UploadScanBundle\Application\UploadScanner;

final readonly class AttachmentController
{
    public function __construct(
        private UploadScanner $scanner,
    ) {
    }

    public function attach(UploadedFile $file): void
    {
        $inspection = $this->scanner->inspectPath($file->getPathname(), $file->getClientOriginalName(), 'attachment');

        if (!$inspection->accepted()) {
            throw new UnprocessableEntityHttpException('This file was refused.');
        }
        // …
    }
}
```

Dans l'environnement de test, `scanner: fake` analyse sans clamd : voir [Tests](testing.md).
