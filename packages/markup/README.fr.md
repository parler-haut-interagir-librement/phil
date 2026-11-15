<!-- phil-dev source-sha256=55a1097839c69d2c6ffd04b535f41f7cf2b6a56d254bb6bd79e795c6eb06873a -->
# phil/markup

**Du Markdown, du BBCode, du MediaWiki, du DokuWiki, du Creole, de l'UJO, du HTML ou du texte brut, changés en HTML
qu'on peut afficher sans risque.**

phil/markup lit un texte écrit en Markdown, en BBCode, dans la syntaxe de MediaWiki, de DokuWiki ou de Creole, ou en
UJO (Universal JSON Output), le format de document par blocs de `phil/ujo`, et en fait un document UJO. Il en produit
le rendu HTML avec `phil/ujo`, puis assainit ce HTML à un niveau donné. Son lecteur Markdown repose sur un analyseur qui
lui est propre : CommonMark, les extensions de GitHub, les notes de bas de page, les attributs, les listes de
définitions, les ancres de titres, une table des matières, le préambule (*front matter*), les blocs `:::` et `{{ }}`,
les pages, les colonnes, les encadrés et les blocs de statistiques, chacun associé au type UJO de même rôle. Son
lecteur BBCode, sur le même analyseur, lit les balises des forums et des extensions dans leur style (`[footnote]`,
`[columns]`, `[pagebreak]`, `[statblock]`, `[toc]`, `[note]`, attributs), qui donnent au BBCode toutes les
fonctionnalités du lecteur Markdown, en un temps linéaire dans la source. Ses lecteurs de MediaWiki, de DokuWiki et de
Creole lisent la syntaxe native de chaque wiki et les extensions qu'ils partagent, écrites comme chaque wiki écrit les
siennes (balises `<note>`, `<ref>`, modèles `{{TOC}}`, macros `~~PAGEBREAK~~`, espaces réservés `<<footnote>>`) : là
encore, toutes les fonctionnalités du lecteur Markdown. Le HTML est compilé tel qu'il est écrit, et son lecteur le
convertit en UJO : le HTML de `phil/ujo` est relu en ses blocs, un collage et des textes plus anciens en les leurs. Le
texte brut est compilé directement en HTML, et son lecteur le convertit en paragraphes UJO. Le gras et l'italique de
Markdown et des wikis sont l'importance forte `<strong>` et l'emphase d'insistance `<em>`, comme CommonMark les écrit.
`basic` garde l'emphase en ligne, pour un nom ou un commentaire ; `forum` ajoute les paragraphes, les liens, les listes,
les citations et les images, pour un message de forum ; `all` garde chaque élément que l'assainisseur considère comme
sûr, pour un article qu'écrit un rédacteur. Une liste de contrôle garde ses cases à cocher accessibles à `all` et dit
l'état de chaque élément en texte en dessous. `MarkedUpText` garde ensemble la source, son format et le HTML assaini, sous forme d'objet
intégrable (*embeddable*) Doctrine, pour qu'une page ne soit pas compilée à chaque lecture. Il ne plafonne pas la
longueur de l'entrée. Un module complémentaire apporte un dialecte de Markdown (ses règles en ligne et de bloc, la
syntaxe qu'il lit autrement) ou un format entier, lu par un lecteur à lui sous un nom à lui.

Il s'adresse à toute application PHP qui affiche du texte qu'écrivent ses visiteurs, ses membres ou ses rédacteurs
(commentaires, messages de forum, profils, articles, livres de jeu), et à celle qui convertit ce qui a été écrit dans
un langage de balisage en blocs d'un éditeur par blocs. Le texte des personnes ne devient du HTML qu'après
assainissement, et le HTML stocké peut être assaini de nouveau à l'affichage : une politique resserrée atteint donc
aussi les textes plus anciens. Dans une application Symfony, phil/markup-bundle enregistre les services et ajoute les
filtres Twig `markup_html` et `markup_purify`. phil/content-bundle compile et assainit son corps de texte avec lui.

C'est une simple bibliothèque PHP avec des attributs Doctrine sur `MarkedUpText`, lus seulement quand
Doctrine le mappe. Son espace de noms racine contient ce qu'un modèle de domaine peut nommer : les enums `MarkupFormat`
et `Level`, les ports `MarkupCompiler`, `LevelAwareCompiler` et `HtmlPurifier`, et les exceptions. `Lexer` contient
l'analyseur que partagent tous les lecteurs, un curseur qui fait l'analyse lexicale sans expressions régulières ;
`Reader`, les lecteurs vers UJO ; `Adapter`, les compilateurs et les assainisseurs. `DefaultCompiler` lit ou compile
chaque format, et prend par leur nom les lecteurs des formats des modules complémentaires ; `SanitizerPurifier`
applique une politique `symfony/html-sanitizer` par niveau, et `AmmoniaPurifier` les mêmes politiques sur l'extension
`ammonia` de PHP, là où elle est chargée. `Markup` enchaîne les deux : source, lecteur, UJO, moteur de rendu, HTML,
assainisseur.

## État

| Partie | État |
|---|---|
| `MarkupFormat`, `Level`, `MarkupCompiler`, `LevelAwareCompiler`, `HtmlPurifier`, `InvalidSource` | Implémentés et testés |
| `Markup`, `MarkedUpText` (un objet intégrable Doctrine) | Implémentés et testés |
| `Lexer\Cursor`, `Lexer\Slugger` : l'analyseur partagé | Implémentés et testés |
| `Reader\Markdown\MarkdownReader` : CommonMark, GitHub, notes de bas de page, attributs, listes de définitions, ancres, `[TOC]`, préambule, blocs `:::`, HTML brut selon le niveau | Implémenté et testé |
| Blocs `{{ }}` et segments qui sont des blocs, blocs de statistiques, pages et colonnes | Implémentés et testés |
| `MarkdownDialect`, `BaseDialect`, `BlockRule`, `InlineRule`, `ReaderProvider` : les dialectes et les formats des modules complémentaires | Implémentés et testés |
| `Reader\BBCode\BBCodeReader` : les balises des forums et les extensions de chaque fonctionnalité de Markdown, HTML brut selon le niveau, temps linéaire | Implémenté et testé |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader` : la syntaxe native de chaque wiki et les extensions partagées de chaque fonctionnalité de Markdown, `Reader\Wiki\WikiLinks`, HTML brut selon le niveau, temps linéaire | Implémentés et testés |
| `Reader\Html\HtmlReader` : HTML vers UJO, le HTML de `phil/ujo` relu, les collages et les textes plus anciens | Implémenté et testé |
| `Reader\UjoReader`, `Reader\Text\TextReader` | Implémentés et testés |
| `Adapter\DefaultCompiler` : Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO et les formats des modules complémentaires par UJO ; HTML et texte brut tels qu'écrits, leurs lecteurs sur demande | Implémenté et testé |
| `Adapter\SanitizerPurifier` : une politique par niveau | Implémenté et testé |
| `Adapter\AmmoniaPurifier`, `Adapter\Purifiers` : les mêmes politiques sur l'extension `ammonia`, là où elle est chargée | Implémentés et testés |
| Mentions, contenus intégrés, liens externes, ponctuation typographique, dans chaque format | Pas commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Formats](doc/fr/formats.md) | Chaque format, quelle fonctionnalité chacun écrit et comment : la table de correspondance. |
| [Markdown](doc/fr/markdown.md) | Ce que lit le lecteur Markdown, et le bloc UJO que devient chaque construction. |
| [L'analyseur](doc/fr/analyser.md) | Le curseur, les règles, les deux passes, et les limites qui gardent la lecture linéaire. |
| [BBCode](doc/fr/bbcode.md) | Les balises et les extensions, leurs types UJO, comment se lit une source mal formée, et pourquoi la lecture est linéaire. |
| [Les wikis](doc/fr/wikis.md) | MediaWiki, DokuWiki et Creole : leur syntaxe, les balises d'extension qu'ils partagent, où se trouvent les pages et les fichiers, et pourquoi la lecture est linéaire. |
| [HTML](doc/fr/html.md) | Ce que lit le lecteur HTML, les classes de `phil/ujo` qu'il relit, et ce qu'il laisse de côté. |
| [Accélérateurs](doc/fr/accelerators.md) | L'extension `ammonia` : ce que garde `AmmoniaPurifier`, les mesures, l'installation par PIE. |
| [Exceptions](doc/fr/exceptions.md) | `MarkupException`, `InvalidSource`, `MissingExtension` et `UnknownFormat`. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace, les dialectes de Markdown et les formats des modules complémentaires. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |
| [Référence](doc/fr/reference.md) | Les types, les formats, les niveaux et ce que garde chacun, et comment chaque niveau produit le rendu d'une liste de contrôle. |

## Démarrage rapide

```php
$markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());

$markup->toHtml('[b]Bonjour[/b] https://example.com', MarkupFormat::BBCode, Level::Forum);
// '<p><b>Bonjour</b> <a href="https://example.com">https://example.com</a></p>'

$post->body = $markup->text($source, MarkupFormat::Markdown);   // source, format and purified HTML
```

```php
$reading = new MarkdownReader()->read("---\ntitle: Notes\n---\n# Hello *world*");

$reading->metadata;                  // ['title' => 'Notes']
$reading->document->blocks[0];       // a `header` block, level 1, its anchor `hello-world`
```

```php
#[ORM\Embedded(class: MarkedUpText::class)]
public MarkedUpText $body;                                      // body_source, body_format, body_html
```

Dans une application Symfony, `phil/markup-bundle` enregistre les services et les filtres Twig.

## Prérequis

PHP 8.5 ou plus récent avec `ext-ctype`, `ext-dom`, `ext-filter`, `ext-libxml` et `ext-mbstring`, `phil/ujo`,
`symfony/html-sanitizer` 8.2 et `symfony/yaml` 8.2. Doctrine ORM 3.7 et DBAL 4.5 sont facultatifs : seule une
application qui stocke un `MarkedUpText` comme objet intégrable en a besoin, et elle les exige elle-même ; les
lecteurs, le compilateur, l'assainisseur et `Markup::text()` fonctionnent sans eux. L'extension `ammonia`, pour PHP 8.2
à 8.5, est facultative elle aussi : `Purifiers::defaultPolicies()` assainit avec elle là où elle est chargée,
`SanitizerPurifier` partout ailleurs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
