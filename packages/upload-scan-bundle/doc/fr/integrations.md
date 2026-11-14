<!-- phil-dev source-sha256=87ffded48b79d6d9e7625558cd416f7a25c8d5416fe8a60b281825e31e118cca -->
# Intégrations

Chaque intégration s'active quand son bundle est enregistré (`integrations.<name>: auto`). Elle peut aussi être exigée
(`on`) ou désactivée (`off`).

## La médiathèque

Avec `phil/media-bundle`, `ScanningMediaStorage` décore son `MediaStorage`. Chaque original écrit — un téléversement
de l'explorateur de fichiers, un remplacement, une image d'un document importé — est inspecté sous le contexte `media`
avant d'être stocké. Un fichier que la politique ne conserve pas est refusé avec `InvalidMedia`, que l'explorateur
affiche à côté du nom du fichier, et rien n'est écrit. Ce que la médiathèque produit à partir d'un original —
redimensionné, converti — n'est pas analysé de nouveau.

## L'importateur de documents

`phil/document-import-bundle` n'exige aucune intégration de ce bundle : quand les deux sont enregistrés, il envoie
chaque document à `UploadScanner`, sous le contexte `document_import`, avant de le lire, et sa propre option `scan`
l'active ou la désactive. clamd regarde à l'intérieur des archives : les images et les macros d'un DOCX ou d'un ODT sont
donc analysées avec lui. Quand les images vont dans la médiathèque, elles passent aussi par l'intégration des médias.

## Des événements pour les paquets qui conservent des fichiers

Un paquet qui conserve des fichiers et les analyse plus tard — une médiathèque qui ne sert rien avant qu'il soit
blanchi, un importateur qui lit des documents sur un *worker* — n'a besoin d'aucun code de ce bundle hors ses
événements : `FileInfected` et `FileFlagged` portent le contexte de l'analyse (`media`, `document_import`,
`rescan.<target>`), et `FileRescanned` la cible, l'identifiant et la nouvelle inspection d'un fichier analysé de nouveau
sur un *worker*. Voir [Quarantaine et événements](quarantine-and-events.md) et [Nouvelles analyses](rescans.md#filerescanned).

## Le contrôle de santé

Avec `phil/monitoring-bundle`, et l'analyseur `clamd` ou `fake`, `ScannerHealthCheck` s'exécute à chaque ronde sous le
nom `upload_scan` :

| clamd | État | Résumé |
|---|---|---|
| répond à `VERSION` | `up` | La version que déclare clamd, par exemple `ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026` |
| ne répond pas | `degraded` | L'antivirus ne répond pas : les téléversements sont refusés ou signalés. |

Le site fonctionne sans son antivirus : le contrôle ne répond donc jamais `down`. Une sonde ne met pas le site hors
service, mais un opérateur est prévenu. La date des signatures dans le résumé montre quand `freshclam` a cessé de les
mettre à jour.
