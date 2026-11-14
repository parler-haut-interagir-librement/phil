<!-- phil-dev source-sha256=4ef2f490d8c16d6d2d799bd1f6c38f6c8fe3f0157352d59ccfebedb678a501a0 -->
# Points d'extension

## L'analyseur

`scanner: app.scanner` fait de tout service qui implémente `Phil\UploadScan\Contract\Scanner` l'analyseur du bundle :
une API d'analyse en nuage, plusieurs moteurs interrogés tour à tour. Voir les points d'extension de
`phil/upload-scan` pour le contrat.

## La quarantaine

`quarantine.service: app.quarantine` remplace le répertoire par un service qui implémente
`Phil\UploadScanBundle\Application\Contract\Quarantine` :

```php
interface Quarantine
{
    public function isolate(mixed $stream, string $name, ScanResult $result): string;
}
```

`isolate()` lit le flux à partir de sa position courante, le conserve là où aucun visiteur ne peut l'atteindre — un
compartiment privé, un volume séparé — et répond un identifiant. Elle lève `QuarantineFailed` quand elle ne peut pas
conserver le fichier ; le bundle le journalise et refuse tout de même le fichier. Ne tirez jamais un chemin de
`$name` : c'est ce que l'expéditeur a choisi.

## L'inventaire de la quarantaine

La page d'administration lit la quarantaine par `Phil\UploadScanBundle\Application\Contract\QuarantineInventory` :

```php
interface QuarantineInventory
{
    /** @return list<QuarantinedFile> */
    public function files(int $limit, int $offset = 0): array;

    public function file(string $id): ?QuarantinedFile;

    public function delete(string $id): bool;
}
```

`files()` liste les fiches, les plus récentes d'abord ; `file()` répond `null` pour un identifiant que la quarantaine ne
donne pas ; `delete()` supprime pour de bon un fichier et sa fiche, et répond `false` quand il n'y en avait pas. Aucune
ne rend ce qu'un fichier contient. `FilesystemQuarantine` l'implémente ; une quarantaine de l'application qui
l'implémente à côté de `Quarantine` sert à la page sans autre configuration, et un alias de `QuarantineInventory` vers
un autre service l'emporte. Sans inventaire, ni la page ni la commande de purge ne sont enregistrées.

## Les cibles de nouvelle analyse

Un service qui implémente `Phil\UploadScanBundle\Application\Contract\RescanTarget` est trouvé par son `name()` quand
un `RescanFile` le nomme : voir [Nouvelles analyses](rescans.md).

## Les événements

`FileInfected` et `FileFlagged` permettent à l'application de réagir — une alerte, une fiche marquée en attente, un
compte examiné : voir [Quarantaine et événements](quarantine-and-events.md). `FileRescanned` dit à un paquet qui
conserve des fichiers ce qu'une nouvelle analyse de sa cible a trouvé, sans qu'il implémente la cible : voir
[Nouvelles analyses](rescans.md#filerescanned).
