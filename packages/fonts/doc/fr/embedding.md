<!-- phil-dev source-sha256=b145a6e9891de977d64889b209abb0f0e5fae4d8a024643b38969e307b0b96ca -->
# Permissions d'incorporation

La licence d'une police décide si un document peut la transporter. La table `OS/2` d'OpenType l'indique dans son champ
`fsType`, que lit `EmbeddingPermissions`.

## Usage

| `EmbeddingUsage` | Bit de `fsType` | Ce qu'un document peut faire |
|---|---|---|
| `Installable` | aucun des bits 1 à 3 | Incorporer la police, et laisser le lecteur l'installer pour de bon |
| `Editable` | bit 3, `0x0008` | Incorporer la police et être modifié avec elle ; la police disparaît avec le document |
| `PreviewAndPrint` | bit 2, `0x0004` | Incorporer la police pour afficher et imprimer le document, qui reste en lecture seule |
| `Restricted` | bit 1, `0x0002` | Ne pas incorporer la police sans la permission expresse de son propriétaire |

`allowsEmbedding()` est faux pour `Restricted` seulement ; `allowsEditing()` est vrai pour `Installable` et `Editable`.

À partir de la version 3 de `OS/2`, une police indique au plus un de ces bits. Une police plus ancienne peut en indiquer
plusieurs, et la spécification OpenType demande alors de retenir le moins restrictif : `Editable` l'emporte sur
`PreviewAndPrint`, qui l'emporte sur `Restricted`. Une face sans table `OS/2` ne déclare rien, ce qui se lit comme
installable.

## Sous-ensembles et bitmaps

| Méthode | Bit de `fsType` | Signification |
|---|---|---|
| `allowsSubsetting()` | bit 8, `0x0100`, à zéro | Un document peut n'incorporer que les glyphes qu'il utilise ; lorsque le bit est à un, il doit incorporer la police entière |
| `bitmapOnly()` | bit 9, `0x0200`, à un | Un document ne peut incorporer que les bitmaps de la police, jamais ses contours |
| `allowsOutlineEmbedding()` | | L'usage permet l'incorporation et la police n'est pas limitée aux bitmaps : ce dont un écrivain PDF ou EPUB a besoin |

`fsType` lui-même reste disponible, pour les bits réservés qu'une révision future d'OpenType pourrait définir.

```php
$permissions = FontFile::fromFile($path)->face()->embedding;

if (!$permissions->allowsOutlineEmbedding()) {
    throw new RuntimeException('This font may not be embedded in a PDF.');
}

$subset = $permissions->allowsSubsetting();
```

Ces bits sont ce que dit la police. C'est la licence sous laquelle la police a été obtenue qui engage, et elle peut
accorder plus ou moins : un registre de licences (voir [Licences](licences.md)) est l'endroit où une application
consigne ce qu'on lui a dit.

## phil/paged-media

`phil/paged-media` lit les polices TrueType pour les mesurer et les incorporer dans des documents PDF. Il lit les mêmes
bits de `fsType` pour refuser une police dont la licence interdit l'incorporation et pour incorporer en entier une
police qui interdit les sous-ensembles. Les deux bibliothèques ne dépendent pas l'une de l'autre, et aucune ne copie
l'autre :

- paged-media lit les tables dont un incorporateur a besoin (contours des glyphes, métriques, correspondance des
  caractères vers les glyphes) et promet un écrivain PDF sans dépendance ; phil/fonts lit les tables dont un catalogue a
  besoin (chaque nom, la licence, les axes de variation, les conteneurs WOFF et WOFF2) et ne construit rien.
- Ce qu'elles partagent est peu de chose : le répertoire des tables, quelques champs de `OS/2`, le nom PostScript.

Si elles en viennent à partager la lecture des polices, la dépendance ira de paged-media vers phil/fonts (l'écrivain de
documents utilisant le lecteur de métadonnées, la couche inférieure), jamais dans l'autre sens : phil/fonts n'a aucune
raison de connaître PDF. D'ici là, une application qui incorpore des polices lit ici les permissions d'une police pour
les afficher, et laisse paged-media les faire respecter au moment de l'écriture.
