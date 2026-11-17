<!-- phil-dev source-sha256=ebc2221757715ef5440ad4374f6797b6b3276665f7948c7899883d462a40c146 -->
# Limites et sécurité

Un paquet est écrit par quelqu'un d'autre, sur une autre plateforme, et peut être fabriqué pour nuire au serveur qui le
lit. La bibliothèque le lit comme une entrée hostile : chaque lecture est bornée, chaque chemin vérifié, et rien de ce
que contient le paquet n'est extrait, récupéré ni exécuté.

## Les limites

`PackageLimits` contient les bornes d'une lecture. Chaque borne est un nombre positif ; `InvalidLimits` est levée dans le
cas contraire.

| Argument | Valeur par défaut | Borne |
|---|---|---|
| `archiveBytes` | 512 Mio | La taille du fichier d'archive |
| `entries` | 10 000 | Les entrées de l'archive, répertoires compris |
| `entryBytes` | 256 Mio | La taille d'un fichier une fois décompressé |
| `totalBytes` | 2 Gio | La taille de tous les fichiers une fois décompressés : annoncée par les entrées, et lue |
| `ratio` | 200 | Combien de fois un fichier de plus de 1 Mio peut être compressé |
| `manifestBytes` | 4 Mio | La taille de `imsmanifest.xml` ou de `cmi5.xml` |
| `depth` | 16 | La profondeur d'imbrication des éléments d'une organisation SCORM ou des blocs d'une structure cmi5 |
| `items` | 5 000 | Les éléments, les ressources, ou les blocs et les unités qu'un manifeste décrit |

```php
use Phil\ElearningPackages\PackageLimits;

$limits = new PackageLimits(archiveBytes: 50 * 1024 * 1024, entries: 2_000, depth: 8);
```

Les constantes de `PackageLimits` contiennent les valeurs par défaut, pour une configuration qui les montre.

## L'archive

`ZipPackage::open()` vérifie toute l'archive avant qu'un fichier soit lu, et la refuse avec `PackageRefused` :

| Vérification | Refusé |
|---|---|
| Le fichier | Absent, illisible, plus gros que `archiveBytes`, ou n'est pas une archive ZIP |
| Nombre d'entrées | Au-delà de `entries` |
| Nom de chaque entrée | Pas un chemin qu'accepte `PackagePath` ; voir plus bas |
| Noms qui ne diffèrent que par la casse | Le second : sur un disque qui ignore la casse, il écraserait le premier |
| Liens symboliques | Toute entrée dont le mode Unix est un lien |
| Entrées chiffrées | Toutes |
| Taille annoncée par une entrée, et leur somme | Au-delà de `entryBytes`, ou de `totalBytes` |
| Taux de compression d'une entrée de plus de 1 Mio | Au-delà de `ratio` : une bombe de compression |

Une entrée de répertoire est vérifiée pour son nom et laissée de côté pour le reste : `files()` ne liste que des
fichiers.

`read()` lit ensuite un fichier par un flux qui s'arrête un octet après ce que son entrée annonçait, de sorte qu'une
archive dont les en-têtes mentent est refusée plutôt que crue. Un fichier dont la taille n'est pas celle annoncée, dont
les données s'arrêtent trop tôt ou dont le CRC-32 ne correspond pas est refusé, et les octets lus dans l'archive
comptent de nouveau contre `totalBytes`. Un avertissement que l'extension zip lève sur une entrée corrompue est
transformé en refus, jamais laissé au gestionnaire d'erreurs de l'application. Après `close()`, toute lecture est
refusée.

## Les chemins

`PackagePath::check()` n'accepte un chemin dans un paquet que s'il est :

- de 1 à 1 024 octets d'UTF-8 ;
- relatif et séparé par `/` : aucun `/` initial, aucune barre oblique inverse, aucun deux-points — donc pas de `C:` —,
  aucun caractère de contrôle ;
- fait d'au plus 32 segments, aucun vide, `.` ou `..`.

Un chemin qui sortirait du paquet est refusé, jamais nettoyé. Dans un refus, le chemin est montré avec ses caractères de
contrôle remplacés et coupé à 120 caractères.

`PackagePath::resolve()` lit la référence d'un manifeste comme une URI relative selon ses valeurs `xml:base` : sa requête
et son fragment mis à part, ses échappements décodés — de sorte que `%2e%2e/` devient `../` —, ses segments `.` retirés,
puis vérifiée.

## Les manifestes

`imsmanifest.xml` et `cmi5.xml` sont analysés par l'extension DOM de PHP :

- un document plus long que `manifestBytes` est refusé avant d'être analysé ;
- un document qui déclare un type de document ou une entité est refusé, de sorte qu'aucune entité — interne, externe ou
  imbriquée pour s'expanser — n'est jamais résolue : ni une XXE ni une bombe d'expansion d'entités ne peut atteindre
  l'analyseur ;
- l'analyseur s'exécute avec `LIBXML_NONET`, sans jamais atteindre le réseau, et ses erreurs sont recueillies puis
  effacées, ce qui laisse l'état d'erreur de libxml tel qu'il était ;
- la profondeur des éléments ou des blocs et leur nombre sont bornés par `depth` et `items`, comptés à mesure de la
  lecture ;
- un identifiant est borné à 255 octets, une URL cmi5 à 2 048, un texte à 4 096 caractères, et un élément de séquencement
  de plus de 64 Kio n'est pas conservé.

## Ce que la bibliothèque ne fait jamais

- Elle n'écrit jamais un fichier du paquet sur le disque, ni n'en nomme un d'après le paquet : l'appelant stocke où il
  veut les chaînes que `read()` renvoie, avec les chemins que la bibliothèque a vérifiés.
- Elle ne récupère jamais une URL qu'un manifeste nomme, ni ne résout une entité externe.
- Elle n'exécute, n'évalue ni ne désérialise jamais rien de ce que contient un paquet.
- Elle ne garde aucun état entre deux lectures : `PackageReader` et les lecteurs sont immuables, et un `ZipPackage`
  appartient à une seule lecture.

## Temps et mémoire

Le temps d'une lecture croît avec la taille du paquet, jamais avec son carré : l'archive est vérifiée entrée par entrée,
et le manifeste parcouru une fois. Un manifeste entier est gardé en mémoire pendant sa lecture, ce que borne
`manifestBytes` ; un fichier est gardé en mémoire une fois lu, ce que borne `entryBytes`. Les bancs d'essai de
[Tests](testing.md) mesurent un manifeste de mille éléments et une archive de mille entrées.
