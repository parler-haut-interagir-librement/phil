<!-- phil-dev source-sha256=507d6c0c0f6ec93d19315552fbee683fe017d0613dc52694eeccec43dfb7223b -->
# phil/fonts

**Ce qu'un fichier de police dit de lui-même (ses noms, sa graisse, sa licence d'incorporation) et la règle `@font-face`
pour le servir.**

phil/fonts lit les métadonnées des fichiers de police : TrueType et OpenType, collections TrueType et OpenType, WOFF et
WOFF2. Pour chaque face, il donne la famille et la sous-famille (typographiques d'abord, comme CSS s'y attend), le nom
complet et le nom PostScript, la version, le copyright, la marque de commerce, le fabricant et le dessinateur, la
description et l'URL de la licence tirées de la table `name`, la graisse, la largeur et l'inclinaison, les permissions
d'incorporation de la table `OS/2` (installable, modifiable, aperçu et impression, restreinte, sans sous-ensemble,
bitmaps seulement), les axes de variation d'une police variable et les plages de caractères que couvre sa table de
caractères. À partir de ces données, il écrit des règles `@font-face` et tient, pour chaque fichier, une fiche de
licence dans un registre qui indique quels fichiers ont changé depuis la dernière vérification de leur licence.

Il s'adresse à toute application PHP qui sert ou incorpore des polices qu'elle n'a pas dessinées : un site qui laisse
ses rédacteurs téléverser des polices et doit écrire leur CSS, une chaîne de production PDF ou EPUB qui doit savoir si
une police peut être incorporée et réduite à un sous-ensemble, un écran d'administration qui liste chaque police avec sa
licence, une étape de construction qui refuse une police dont elle ne connaît pas la licence. `phil/paged-media`
incorpore des polices TrueType dans des documents PDF ; cette bibliothèque permet à une application de les vérifier
d'abord.

C'est une simple bibliothèque PHP qui suit les spécifications OpenType 1.9.1, WOFF 1.0 et WOFF 2.0 et dont
les seuls prérequis sont `ext-mbstring` et `ext-zlib`. WOFF2 compresse ses tables avec Brotli, que PHP ne décode pas
seul. Un fichier WOFF2 est donc lu au moyen d'un décompresseur que l'application fournit, `Brotli::decompressor()`, qui
vaut `brotli_uncompress()` lorsque `ext-brotli` est chargée. Sans cette extension, les fichiers WOFF, TrueType et
OpenType sont lus, et un fichier WOFF2 est refusé avec un message qui le dit. Rien n'est installé avec elle, et rien
n'est téléchargé.

La conception est petite : `FontFile` lit un fichier pour en tirer son `Format` et ses faces `Font` ; une `Font`
contient des `Names`, des `EmbeddingPermissions`, des valeurs `Axis` et des plages de caractères ; `FontFaceRule` écrit
du CSS à partir d'une `Font` ; `LicenceRecord` et `LicenceRegister` conservent ce que chaque fichier dit de sa licence.
phil/fonts et phil/paged-media ne dépendent pas l'un de l'autre : voyez [Permissions
d'incorporation](doc/fr/embedding.md) pour savoir pourquoi, et dans quel sens ils le feraient.

## État

| Partie | État |
|---|---|
| Conteneurs : TrueType, OpenType, collections, WOFF (zlib), WOFF2 (Brotli au moyen d'un décompresseur fourni) | Implémenté et testé |
| Table `name` : chaque enregistrement, UTF-16 et Mac Roman décodés, choix du nom qu'un lecteur attend | Implémenté et testé |
| Graisse, classe de largeur, inclinaison, angle d'italique, chasse fixe, contours, axes de variation, plages de caractères | Implémenté et testé |
| Permissions d'incorporation de `OS/2`, l'usage le moins restrictif lorsque plusieurs sont indiqués | Implémenté et testé |
| Description et URL de la licence, et reconnaissance de l'identifiant SPDX des licences de polices courantes | Implémenté et testé |
| Règles `@font-face` : sources locales et distantes, plages pour les polices variables, `font-display`, `unicode-range`, échappement | Implémenté et testé |
| Fiches de licence par fichier, registre tiré du balayage d'un répertoire, conservé en JSON, comparé à un nouveau balayage | Implémenté et testé |
| Collections WOFF2, décodage WOFF2 sans décompresseur Brotli | Non pris en charge : voir [Lire des fichiers de police](doc/fr/reading.md) |
| Reconstruction des tables `glyf`, `loca` et `hmtx` transformées par WOFF2 | Inutile : elles ne portent aucune métadonnée |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et `ext-brotli` pour WOFF2. |
| [Lire des fichiers de police](doc/fr/reading.md) | `FontFile`, les formats, les faces, les noms et les autres métadonnées d'une `Font`. |
| [Permissions d'incorporation](doc/fr/embedding.md) | `EmbeddingPermissions` et `EmbeddingUsage`, et le lien avec `phil/paged-media`. |
| [Règles `@font-face`](doc/fr/font-face.md) | `FontFaceRule` : sources, descripteurs, polices variables, `unicode-range`, échappement. |
| [Licences](doc/fr/licences.md) | `Licence`, `LicenceRecord` et `LicenceRegister` : ce qui est conservé, le JSON, la détection des fichiers modifiés. |
| [Points d'extension](doc/fr/extension-points.md) | Le décodeur Brotli, les règles et le registre : ce que l'application choisit. |
| [Exceptions](doc/fr/exceptions.md) | `FontsException` et ses quatre exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui lit des polices. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/fonts
```

```php
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;

$file = FontFile::fromFile('/path/to/ExampleSans-Bold.ttf');
$font = $file->face();

echo $font->family();                          // Example Sans
echo $font->weight;                            // 700
echo $font->embedding->usage->value;           // installable
echo $font->licence()->spdx();                 // OFL-1.1

echo FontFaceRule::for($font)
    ->withUrl('/fonts/example-sans-bold.woff2', Format::Woff2)
    ->withUrl('/fonts/example-sans-bold.ttf', Format::TrueType)
    ->toCss();
```

```php
use Phil\Fonts\Brotli;
use Phil\Fonts\Licence\LicenceRegister;

$kept = LicenceRegister::fromJson(file_get_contents('fonts/licences.json'));
$now = LicenceRegister::scan('fonts', Brotli::decompressor());

foreach ($kept->changedFiles($now) as $file) {
    echo "Check the licence of {$file}\n";
}

file_put_contents('fonts/licences.json', $now->toJson());
```

## Prérequis

PHP 8.5 ou plus récent, `ext-mbstring` et `ext-zlib` ; `ext-brotli`, ou un autre décodeur Brotli, pour WOFF2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
