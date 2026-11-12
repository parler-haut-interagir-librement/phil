<!-- phil-dev source-sha256=a8e5309d72952fd41c6b6581e1179e5371456455f64fe27ae17587bf92a9c7ad -->
# phil/creation-disclosure

**Comment une œuvre a été faite, déclarée partie par partie dans le vocabulaire Digital Source Type de l'IPTC :
captée, faite par des personnes, retouchée ou créée par l'IA générative, ou non déclarée, avec la vue « sans IA » et
l'exigence que fixe un concours ou une boutique.**

phil/creation-disclosure note comment une œuvre a été faite — un livre, un module de jeu, une illustration, une
chanson — une partie à la fois : son texte, ses images, son audio, sa vidéo, son code. Chaque partie est déclarée par
un code du vocabulaire Digital Source Type de l'IPTC, celui que portent XMP, C2PA et le `digitalSourceType` de
schema.org, ou reste non déclarée, ce qui reste visible et ne passe jamais pour « sans IA ». Des déclarations, elle lit
une vue, ce que l'œuvre doit à l'IA générative : rien, peut-être, en partie ou en entier. « Sans IA » est cette vue à
`None`, pas une étiquette à part.

Elle sert à toute application PHP qui reçoit ou vend des œuvres et laisse ses lecteurs filtrer selon leur fabrication :
un catalogue avec un filtre « sans IA », un concours qui refuse les œuvres générées, une boutique qui demande à ses
vendeurs de déclarer leurs images. L'application garde la déclaration dans une colonne JSON et montre la vue ; la
bibliothèque tient le vocabulaire et les règles.

C'est une bibliothèque PHP pure, qui n'exige que `ext-mbstring`.

La conception : `DigitalSourceType` est le vocabulaire, codes retirés compris ; `Part` nomme les parties d'une œuvre ;
`CreationDisclosure` tient une déclaration par partie, immuable ; `GenerativeAi` est la vue et sa combinaison sur les
parties ; `DisclosureRequirement` est ce que demande un concours ou une boutique.

## État

| Partie | État |
|---|---|
| Le vocabulaire Digital Source Type de l'IPTC, codes, adresses et libellés, codes retirés et leurs remplaçants | Implémenté et testé |
| Les déclarations partie par partie, les parties non déclarées gardées visibles | Implémenté et testé |
| La vue de l'IA générative d'une partie et d'une œuvre entière, la vue « sans IA » | Implémenté et testé |
| Les exigences : parties à déclarer et plafond d'IA générative | Implémenté et testé |
| La conservation et la relecture en tableau de codes | Implémenté et testé |
| La lecture et l'écriture du XMP d'un fichier, les manifestes C2PA | Pas commencé : la bibliothèque donne la propriété et les adresses |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Déclarer](doc/fr/declaring.md) | Les parties, les déclarations, les vues, la conservation d'une déclaration. |
| [Vocabulaire](doc/fr/vocabulary.md) | Chaque type de source numérique, ce qu'il dit de l'IA générative, les codes retirés. |
| [Exigences](doc/fr/requirements.md) | Ce que demande un concours, une boutique ou un catalogue, et comment vérifier une œuvre. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/creation-disclosure
```

```php
use Phil\CreationDisclosure\CreationDisclosure;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;

$book = CreationDisclosure::of(Part::Text, Part::Image)
    ->declare(Part::Text, DigitalSourceType::HumanEdits)
    ->declare(Part::Image, DigitalSourceType::TrainedAlgorithmicMedia);

$book->generativeAi();         // GenerativeAi::Partial
$book->isFreeOfGenerativeAi(); // false
$book->toArray();              // ['text' => 'humanEdits', 'image' => 'trainedAlgorithmicMedia']
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
