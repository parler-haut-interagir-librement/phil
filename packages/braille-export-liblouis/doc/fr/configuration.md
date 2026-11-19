<!-- phil-dev source-sha256=6de553399f8ce2a644749eaf09ddb5a0f9a8e977e341acabcef19c2e193fb943 -->
# Configuration

Chaque choix est un argument de `LiblouisTranslator` :

```php
use Phil\BrailleExport\Contraction;
use Phil\BrailleExportLiblouis\LiblouisTables;
use Phil\BrailleExportLiblouis\LiblouisTranslator;

$translator = new LiblouisTranslator(
    tables: LiblouisTables::defaults()->with('de', Contraction::Contracted, 'de-g2.ctb'),
    binary: '/usr/bin/lou_translate',
    timeout: 60.0,
    tablePath: '/srv/braille/tables',
);
```

| Argument | Valeur par défaut | Sens |
|---|---|---|
| `tables` | `LiblouisTables::defaults()` | La table de chaque langue et contraction. |
| `binary` | `lou_translate` | Le programme : un nom trouvé dans le `PATH`, ou un chemin. |
| `timeout` | 30 | Les secondes que peut prendre un lot. |
| `maxInputBytes` | 4 Mio | Le plus grand nombre d'octets de texte qu'un lot peut envoyer. |
| `maxOutputBytes` | 32 Mio | Le plus grand nombre d'octets que le programme peut écrire pour un lot, sortie d'erreur comprise. |
| `displayTable` | `unicode.dis` | La table d'affichage, qui doit écrire du braille Unicode. |
| `tablePath` | aucun | Les répertoires où liblouis cherche des tables, qui lui sont donnés par `LOUIS_TABLEPATH` ; son propre répertoire sans cet argument. |

## Les tables

Une table est le nom de fichier d'une table de liblouis, ou plusieurs séparés par des virgules, jamais un chemin.
`LiblouisTables` associe une langue à ses tables pour `uncontracted` et `contracted` :

```php
$tables = new LiblouisTables([
    'en' => ['uncontracted' => 'en-ueb-g1.ctb', 'contracted' => 'en-ueb-g2.ctb'],
    'en-US' => ['contracted' => 'en-us-g2.ctb'],
    'fr' => ['uncontracted' => 'fr-bfu-comp6.utb', 'contracted' => 'fr-bfu-g2.ctb'],
]);
```

| Langue | Intégral | Abrégé |
|---|---|---|
| `en` : Unified English Braille | `en-ueb-g1.ctb` | `en-ueb-g2.ctb` |
| `fr` : Code braille français uniformisé | `fr-bfu-comp6.utb` | `fr-bfu-g2.ctb` |

Ce sont les valeurs par défaut. Une étiquette de langue se lit en minuscules, `_` comme `-` : `fr-CA` prend les tables de
`fr-ca` quand il y en a, sinon celles de `fr`. `with()` donne une copie avec une table de plus. Les noms sont vérifiés :
lettres, chiffres, `.`, `_` et `-`, au plus huit tables de 128 caractères chacune.

Les tables qu'une langue peut prendre sont énumérées dans le répertoire des tables de liblouis, un fichier par table,
chacun commençant par sa langue et son degré dans ses commentaires.

## La table d'affichage

Le programme écrit chaque cellule comme le dit sa table d'affichage. Le traducteur lit du braille Unicode : la table
d'affichage est donc `unicode.dis`, à moins que les tables soient gardées ailleurs avec une autre table qui écrit les
mêmes caractères. Une ligne qui n'est pas du braille arrête le lot avec une `LiblouisFailed` qui nomme la table
d'affichage.

## Les textes longs

Le programme lit une ligne par texte. Un texte de plus de 1,000 octets est envoyé en morceaux coupés à la dernière
espace qui tient, sinon entre deux caractères, et son braille est rejoint avec une cellule blanche.
