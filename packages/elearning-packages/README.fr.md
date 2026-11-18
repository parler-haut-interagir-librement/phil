<!-- phil-dev source-sha256=64089dca3cd10af639285aa7923b36f7d59af385b8fdd2b7b61e88bfabe03027 -->
# phil/elearning-packages

**Paquets SCORM 1.2, SCORM 2004 et cmi5 lus en toute sécurité, et le modèle de données d'exécution SCORM, sans plateforme d'apprentissage.**

phil/elearning-packages lit les paquets de cours qu'exportent les outils de formation en ligne : une archive ZIP qui
contient à sa racine `imsmanifest.xml` (SCORM 1.2 ou SCORM 2004, selon IMS Content Packaging) ou `cmi5.xml` (une
structure de cours cmi5). La bibliothèque reconnaît le format, lit le manifeste en valeurs immuables — organisations,
éléments et ressources, ou blocs et unités assignables — et note ce que le manifeste nomme mais que le paquet ne
contient pas, comme un fichier de lancement absent.

Un paquet vient d'ailleurs : la bibliothèque le lit donc comme une entrée hostile. L'archive est vérifiée en entier
avant qu'un fichier soit lu : sa taille et son nombre d'entrées, le chemin de chaque entrée, qui doit rester dans le
paquet, les liens symboliques, les entrées chiffrées, la taille que chaque entrée annonce et son taux de compression.
Chaque fichier est ensuite lu par un flux qui s'arrête un octet après ce que son entrée annonçait, et vérifié selon son
CRC-32. Le manifeste est analysé sans type de document ni entité, sans accès au réseau, et dans des limites sur sa
taille, sa profondeur et le nombre d'éléments qu'il décrit. Rien n'est extrait sur le disque et rien n'est exécuté.

Elle contient aussi le modèle de données d'exécution de SCORM 1.2 et de SCORM 2004 4e édition : chaque élément, son type,
son vocabulaire et son intervalle, qui peut le lire et l'écrire, les codes d'erreur de chaque version, l'API d'exécution
avec ses états, les règles d'une validation, les statuts et les notes, et le temps de session ajouté au temps total. Une
plateforme qui exécute du contenu SCORM y vérifie les appels du contenu, dans le navigateur par la description qu'elle
donne, puis de nouveau sur le serveur.

Elle sert une plateforme qui importe et exécute des cours, un formulaire de téléversement qui refuse un paquet brisé
avant qu'il soit stocké, ou un outil de migration qui énumère le contenu d'un ensemble de paquets. C'est une bibliothèque PHP
simple, sans dépendance au-delà des extensions `dom`, `libxml`, `mbstring` et `zip`.

## État

| Partie | État |
|---|---|
| L'archive ZIP vérifiée avant qu'un fichier soit lu, puis lue dans ses limites | Implémenté, testé et soumis au fuzzing |
| Les chemins dans un paquet, résolus selon `xml:base` et vérifiés | Implémenté et testé |
| Le manifeste lu sans type de document ni entité, borné en taille, en profondeur et en éléments | Implémenté, testé et soumis au fuzzing |
| Les manifestes SCORM 1.2 et SCORM 2004 : version, organisations, éléments, ressources, séquencement conservé en XML | Implémenté, testé et soumis au fuzzing |
| Les structures de cours cmi5 : cours, blocs, unités assignables, dans la langue demandée | Implémenté, testé et soumis au fuzzing |
| Le format reconnu, les fichiers de lancement et les fichiers déclarés vérifiés | Implémenté et testé |
| Ce qu'un environnement d'exécution remet à un SCO, lu dans le manifeste : données de lancement, note de maîtrise, note de passage normalisée, seuil d'achèvement, temps alloué et son action | Implémenté et testé |
| Le modèle de données d'exécution SCORM : les éléments de SCORM 1.2 et 2004, leurs règles de lecture et d'écriture et leurs codes d'erreur, les validations, statuts, notes, temps, l'API et ses états | Implémenté, testé et soumis au fuzzing |
| L'environnement d'exécution lui-même — une page, un point de terminaison, les tentatives conservées —, un client xAPI, l'import dans un modèle de cours | Pas dans cette bibliothèque : une plateforme les construit dessus |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Lire SCORM](doc/fr/reading-scorm.md) | Le manifeste SCORM, sa version, ses organisations, ses éléments et ses ressources. |
| [Lire cmi5](doc/fr/reading-cmi5.md) | La structure de cours cmi5, ses blocs et ses unités assignables, une structure seule. |
| [Le modèle de données d'exécution SCORM](doc/fr/scorm-runtime.md) | Les éléments, la lecture et l'écriture, les codes d'erreur, l'API, le début et la fin d'une session, ce qui est laissé à la plateforme. |
| [Limites et sécurité](doc/fr/limits-and-security.md) | Les limites, ce que l'on vérifie dans l'archive et le manifeste, ce que la bibliothèque ne fait jamais. |
| [Exceptions](doc/fr/exceptions.md) | Ce que la bibliothèque lève, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, remplace ou sur quoi elle s'appuie. |
| [Tests](doc/fr/testing.md) | Les tests, les cibles de fuzzing et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/elearning-packages
```

```php
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageReader;

try {
    $package = new PackageReader()->readFile($uploadedFile);
} catch (PackageRefused $refused) {
    echo $refused->getMessage(); // 'The path "../../etc/passwd" holds an empty, "." or ".." segment.'
}

$package->format;       // PackageFormat::Scorm2004
$package->title();      // 'First aid'
$package->identifier(); // 'org.example.first-aid'
$package->files;        // ['imsmanifest.xml', 'sco/start.html', …]
$package->warnings;     // ['The launch file "sco/quiz.html" of the item "a2" is not in the package.']
```

## Prérequis

PHP 8.5 ou une version plus récente avec les extensions `dom`, `libxml`, `mbstring` et `zip`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
