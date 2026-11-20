<!-- phil-dev source-sha256=2439c6ad666c2ee3c705c23c8e7f831db4812aebaf6fb07232e10bee3578e284 -->
# Quarantaine et événements

## La quarantaine

Un fichier infecté est copié dans la quarantaine avant d'être refusé, pour qu'un opérateur l'examine : confirmer une
menace, ou libérer à la main un faux positif. `FilesystemQuarantine`, la valeur par défaut, le conserve dans
`%kernel.project_dir%/var/quarantine` :

```text
var/quarantine/                              drwx------
├── 9f0c…e2a1.quarantine                     -rw-------   the file, byte for byte
└── 9f0c…e2a1.json                           the record
```

```json
{
    "name": "chapter-3.docx",
    "signature": "Doc.Dropper.Agent-1234",
    "quarantined_at": "2026-11-08T10:30:00+00:00"
}
```

- L'identifiant compte 32 chiffres hexadécimaux aléatoires. Rien de ce que l'expéditeur a choisi ne devient un
  chemin : le nom qu'il a donné n'est qu'un champ de la fiche, sans ses caractères de contrôle, coupé à 255 octets.
- Le répertoire est créé lisible par son propriétaire seulement (`0700`), et chaque fichier est lu et écrit par son
  propriétaire seulement (`0600`) dès qu'il existe : la copie et la fiche sont écrites sous forme de fichiers
  temporaires du répertoire, `.phil-quarantine-` suivi de caractères aléatoires, créés en `0600` par `tempnam()`, puis
  reçoivent leurs noms en une seule étape, la fiche en dernier. Une copie qui échoue ne laisse aucun des deux fichiers,
  ni aucun fichier temporaire.
- Un répertoire qui existe déjà et qui est ouvert à son groupe ou à d'autres utilisateurs — créé à la main avec `0755`,
  ou monté ainsi — est restreint à `0700` avant qu'un fichier y soit conservé. Quand il ne peut pas l'être, parce
  qu'un autre utilisateur en est le propriétaire, ou quand `tighten_open_directory` est faux, `QuarantineFailed` nomme
  le répertoire et son mode, et rien n'est écrit. Un répertoire atteint par un lien symbolique est utilisé là où le
  lien pointe.
- Un répertoire sous `public/` est refusé à la construction du conteneur — tel qu'écrit, et une fois les liens
  symboliques des deux chemins suivis, la partie qui n'existe pas encore étant gardée telle qu'écrite —, et aucune route
  du bundle ne sert ce que contient la quarantaine : un fichier infecté n'est jamais téléchargé depuis le site. Gardez le répertoire hors des
  sauvegardes restaurées à des endroits que sert le serveur Web.
- Un opérateur supprime les fichiers traités sur la page d'administration, ci-dessous, et la commande de purge supprime
  ceux qui sont conservés plus longtemps que la durée de rétention.

Quand la quarantaine ne peut pas conserver un fichier — disque plein, répertoire non inscriptible —, l'échec est
journalisé comme une erreur, `quarantineId` vaut `null`, et le fichier est tout de même refusé.

## La page d'administration

Avec Twig et `symfony/security-csrf`, et sauf si `admin.enabled` vaut `false`, le bundle enregistre
`QuarantineAdminController`, la page où un opérateur lit la quarantaine et supprime les fichiers traités. Importez ses
routes derrière le pare-feu de l'application :

```yaml
# config/routes/phil_upload_scan_admin.yaml
phil_upload_scan_admin:
    resource: '@PhilUploadScanBundle/config/admin_routes.php'
    prefix: /admin/quarantine
```

| Route | Méthode et chemin | |
|---|---|---|
| `phil_upload_scan_quarantine` | `GET /` | Les fichiers, les plus récents d'abord, `page_size` à la fois : `page` |
| `phil_upload_scan_quarantine_delete` | `POST /{id}/delete` | Supprime pour de bon un fichier et sa fiche, avec le jeton CSRF `phil_upload_scan_quarantine` |

Chaque fichier montre le nom sous lequel il a été envoyé, la signature qui l'a condamné, le moment où il a été conservé,
sa taille et son identifiant, celui que `FileInfected` portait. Ce qu'un fichier contient n'est jamais montré ni servi :
un opérateur qui doit en regarder un le lit sur le serveur. Une suppression sans jeton CSRF valide répond `400` (voir
[CSRF](csrf.md)), celle d'un identifiant que la quarantaine ne détient pas `404` ; une suppression qui aboutit est journalisée au niveau
`notice` avec son identifiant et renvoie le navigateur à la liste par un `303`.

Chaque page demande à la sécurité de l'application `admin.role`, `ROLE_ADMIN` par défaut (un rôle, ou l'attribut d'un
*voter*) ; tout autre accès répond `403`, et chaque page aussi quand la sécurité de Symfony n'est pas installée. La page
étend `admin.layout`, un gabarit avec les blocs `title`, `stylesheets`, `javascripts` et `content` ; la valeur par
défaut est une page HTML nue qui demande aux robots de ne pas l'indexer. Le gabarit du bundle,
`@PhilUploadScan/admin/quarantine.html.twig`, est du HTML simple avec une classe `phil-upload-scan-quarantine`, et peut
être redéfini sous `templates/bundles/PhilUploadScanBundle/`.

La page lit la quarantaine par `QuarantineInventory`, qu'implémente `FilesystemQuarantine`. Une quarantaine de
l'application qui l'implémente aussi est lue de la même façon ; sinon l'application donne à `QuarantineInventory` un
alias vers un service à elle, ou la page n'est pas enregistrée. Voir [Points d'extension](extension-points.md).

Dans une interface d'administration EasyAdmin, `QuarantineAdmin::menuItem()` lie le menu du tableau de bord à la page :

```php
use Phil\UploadScanBundle\Infrastructure\EasyAdmin\QuarantineAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Security');
    yield QuarantineAdmin::menuItem();   // "Quarantine", fa fa-biohazard; the label may be a TranslatableInterface
}
```

Faites pointer `admin.layout` vers un gabarit de l'interface d'administration pour que la page garde son apparence.

## La rétention

`phil:upload-scan:purge-quarantine` supprime pour de bon les fichiers conservés plus longtemps que
`quarantine.retention_days`, 30 par défaut, avec leurs fiches, et journalise chaque suppression au niveau `notice` avec
son identifiant. Elle lit la quarantaine par `QuarantineInventory`, cent fiches à la fois, et est enregistrée avec le
composant Console quand la quarantaine peut être listée.

```sh
bin/console phil:upload-scan:purge-quarantine              # the configured retention
bin/console phil:upload-scan:purge-quarantine --days=7     # another retention, for this run
bin/console phil:upload-scan:purge-quarantine --dry-run    # counts, deletes nothing
bin/console phil:upload-scan:purge-quarantine --max=500    # at most 500 files in this run
```

Une exécution supprime au plus `--max` fichiers, 10 000 par défaut, pour qu'une quarantaine laissée grossir se vide en
plusieurs exécutions. Un fichier conservé exactement la durée de rétention est gardé. Lancez-la chaque jour, depuis
*cron* ou depuis un calendrier de Symfony Scheduler :

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('@daily', new RunCommandMessage('phil:upload-scan:purge-quarantine'));
```

`QuarantinePurger` fait la même chose depuis le code de l'application : `purge($days, $max, $dryRun)` rend les fichiers
supprimés, ou comptés lors d'un essai à blanc.

## `FileInfected`

Réparti sur le répartiteur d'événements PSR-14 pour chaque fichier infecté, qu'il ait été trouvé par un validateur,
une intégration ou une nouvelle analyse :

| Propriété | Valeur |
|---|---|
| `name` | Le nom que l'expéditeur a donné au fichier. Ne vous y fiez jamais comme chemin. |
| `signature` | La signature trouvée par l'antivirus, en ASCII imprimable. |
| `context` | La provenance : `upload`, le `context` d'une contrainte, `media`, `document_import`, `rescan.<target>`. |
| `quarantineId` | L'identifiant dans la quarantaine, ou `null`. |

```php
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AlertSecurityTeam
{
    public function __invoke(FileInfected $event): void
    {
        $this->notifier->send(new Notification(\sprintf('%s found in an upload (%s), quarantined as %s.', $event->signature, $event->context, $event->quarantineId ?? 'nothing')));
    }
}
```

L'événement ne transporte rien sur la personne qui a envoyé le fichier. Un écouteur qui en a besoin le lit dans la
requête ou dans le jeton de sécurité, et ne le conserve que le temps que permet la politique de vie privée de
l'application.

## `FileFlagged`

Réparti quand un fichier que l'analyseur n'a pas pu analyser est accepté sous `on_error: accept_and_flag` : `name`,
`reason` (pourquoi il n'a pas été analysé) et `context`. En général, un écouteur marque la fiche du fichier comme en
attente et répartit pour lui un [`RescanFile`](rescans.md), retardé de quelques minutes.

Un fichier analysé de nouveau sur un *worker* est suivi de [`FileRescanned`](rescans.md#filerescanned), une fois que sa
cible a résolu la nouvelle inspection.

Sans répartiteur d'événements dans le conteneur, aucun événement n'est réparti ; l'inspection dit tout de même ce qui
s'est passé.
