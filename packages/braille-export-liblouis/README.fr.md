<!-- phil-dev source-sha256=41e9a28dd6a6ac8e22da1ce611668e4f77664e81828be83f7db4d388efe0d6e0 -->
# phil/braille-export-liblouis

**Du braille abrégé et intégral pour phil/braille-export par liblouis, dans toutes les langues pour lesquelles il a des tables.**

phil/braille-export-liblouis est le traducteur liblouis de `phil/braille-export`. `LiblouisTranslator` implémente le
port `Translator` du paquet en exécutant `lou_translate`, le programme de traduction de liblouis : un document entier
passe en une seule exécution, un texte par ligne de son entrée standard, et revient en braille Unicode, une ligne par
texte.

Employez-le pour le braille abrégé — « braille abrégé », degré 2 —, pour une langue autre que le français et
l'anglais, ou pour les règles complètes d'un code. Le traducteur intégral de `phil/braille-export` n'a besoin d'aucun
programme, et prend le relais quand liblouis manque, par un `ChainTranslator`.

Le paquet ne contient ni table ni code de liblouis : liblouis est installé sur la machine, avec ses tables, depuis les
paquets du système ou dans l'image de l'application. Voir [Installation](doc/fr/installation.md).

`LiblouisTables` choisit la table de chaque langue et de chaque contraction : Unified English Braille et le Code
braille français uniformisé par défaut. Le programme est démarré avec ses arguments en liste, jamais par un interpréteur
de commandes ; son temps, son entrée et sa sortie sont bornés, et un programme ou une table manquants lancent leur
propre exception. Voir [Sécurité](doc/fr/security.md).

## État

| Partie | État |
|---|---|
| `LiblouisTranslator` : lots, morceaux des textes longs, braille Unicode vérifié, limites, version | Implémenté et testé avec un programme de remplacement |
| `LiblouisTables` : la table de chaque langue et contraction | Implémenté et testé |
| Les exceptions : programme manquant, table manquante, autres échecs | Implémenté et testé |
| La suite d'intégration contre le vrai `lou_translate` | Écrite ; ignorée sur une machine sans liblouis |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation de liblouis depuis le système ou dans une image Docker, le branchement du service. |
| [Configuration](doc/fr/configuration.md) | Les tables, la table d'affichage, le chemin des tables, le programme, les limites. |
| [Sécurité](doc/fr/security.md) | Comment le programme est démarré, et ce qui le borne. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le traducteur, et quand. |
| [Points d'extension](doc/fr/extension-points.md) | Les arguments qu'une application choisit, et ce qu'elle remplace. |
| [Tests](doc/fr/testing.md) | Le programme de remplacement des tests unitaires, et la suite du vrai programme. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/braille-export-liblouis
```

```php
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Translation\ChainTranslator;
use Phil\BrailleExport\Translation\UncontractedTranslator;
use Phil\BrailleExportLiblouis\LiblouisTranslator;

$translator = new ChainTranslator(new LiblouisTranslator(), new UncontractedTranslator());
$book = new BrailleExporter($translator)->layout($document, Contraction::Contracted);

file_put_contents('book.brf', new BrfWriter()->write($book));
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` ; `phil/braille-export` 0.1 ; `symfony/process` 8.2 ; liblouis 3
avec son programme `lou_translate` et ses tables sur la machine qui traduit.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
