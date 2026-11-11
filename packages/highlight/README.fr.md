<!-- phil-dev source-sha256=1b32e844df2124131833567bba51c2212f2c359d1730a7c8bd8df6e7c0312a4d -->
# phil/highlight

**Du code en 24 langages coloré sur le serveur, en simples balises span HTML que n'importe quelle feuille de style peut
habiller.**

phil/highlight transforme du code source en HTML pour `<pre><code>` : chaque élément que le lecteur doit distinguer
(un mot-clé, une chaîne, un commentaire, un nombre, une balise, une clé) est enveloppé dans un
`<span class="hl-…">`, et tout est échappé. Il lit le PHP, le JavaScript, le TypeScript, le JSON, le HTML, le XML, le
CSS, le SCSS, le SQL, le YAML, le Twig, le Markdown, le shell, le Python, le Ruby, le Go, le Rust, le Java, le C, le
C++, le C#, l'INI, les Dockerfiles et les diffs, chacun par un petit analyseur lexical qui ne lève jamais d'exception
et ne perd jamais un octet, quoi qu'on lui donne.

Il s'adresse à toute application PHP qui affiche du code : une documentation, un blogue, un forum, un wiki, un livre
mis en page en HTML ou en PDF. Colorer sur le serveur envoie la page prête à lire, sans script et sans éclair de code
brut, et fonctionne dans un lecteur de fils, un courriel ou un PDF. Les mêmes classes `hl-` sont écrites dans le
navigateur par le contrôleur de coloration de `phil/ux-bundle` : un même thème sert donc aux deux. Le moteur de rendu
de `phil/ujo` prend le colorateur pour les blocs de code d'un document.

C'est une simple bibliothèque PHP sans dépendance. Chaque analyseur lexical lit la source une seule
fois, de gauche à droite, octet par octet avec `strspn` et `strcspn`, et ne garde aucune expression régulière sur son
chemin critique ; les sources hostiles (cent mille guillemets ou crochets ouverts) sont lues en temps linéaire. Il
colore ce dont un lecteur a besoin et rien de plus : ce n'est pas un analyseur syntaxique, il ne vérifie pas la
syntaxe et ne connaît pas tous les recoins de chaque grammaire.

La conception est petite : un `Language` transforme une source en `Token`, chacun doté d'un `TokenType` ; `Code` est
l'analyseur lexical d'un langage de programmation décrit par une `Syntax`, et `Definitions` contient celles qui sont
fournies ; le balisage, les feuilles de style, le YAML, l'INI, les Dockerfiles, les diffs, le Markdown, le Twig et le
mélange de balisage et de code de PHP ont les leurs ; `HtmlFormatter` écrit les balises span ; `Highlighter` trouve un
langage par son nom ou un alias et assemble le tout. Les thèmes sont des fichiers CSS.

## État

| Partie | État |
|---|---|
| `Highlighter` : langages par nom et alias dans n'importe quelle casse, ajoutés ou remplacés ; HTML ou jetons | Implémenté et testé |
| Langages de programmation par `Syntax` : PHP, JavaScript, TypeScript, JSON, SQL, shell, Python, Ruby, Go, Rust, Java, C, C++, C# | Implémenté et testé |
| HTML avec ses scripts et ses styles, XML, CSS, SCSS, YAML, INI, Dockerfile, diff, Markdown, Twig, PHP avec balisage | Implémenté et testé |
| Lignes : numéros, plages mises en évidence, annotations dans les commentaires | Implémenté et testé |
| Thèmes : clair, sombre, et automatique selon le système ou `data-theme` | Implémenté |
| Temps linéaire sur les sources hostiles ; rien de perdu sur aucune source | Implémenté et testé |
| Heredocs, commentaires imbriqués, chaînes brutes avec délimiteurs, interpolation colorée à l'intérieur des chaînes | Non lus : ils prennent la couleur de ce qui les contient |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les thèmes. |
| [Langages](doc/fr/languages.md) | Chaque langage, ses noms, ce que chacun colore et ce qu'il laisse tel quel. |
| [HTML et thèmes](doc/fr/html-and-themes.md) | Les types de jetons, leurs classes, le HTML écrit, et les thèmes. |
| [Lignes](doc/fr/lines.md) | Un bloc ligne par ligne : numéros, plages mises en évidence, et les annotations `[!code ++]`, `[!code --]` et `[!code highlight]`. |
| [Ajouter un langage](doc/fr/extending.md) | Une `Syntax` à vous, ou un `Language` écrit à la main, et le remplacement d'un langage fourni. |
| [Documents UJO](doc/fr/ujo.md) | Colorer les blocs de code des documents `phil/ujo`, et garder les couleurs à travers un purificateur. |
| [Exceptions](doc/fr/exceptions.md) | `HighlightException` et ses trois exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui colore. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/highlight
```

```php
use Phil\Highlight\Highlighter;

$highlighter = Highlighter::default();

echo '<pre><code class="language-php">'.$highlighter->toHtml('echo "Hi";', 'php').'</code></pre>';
// <pre><code class="language-php"><span class="hl-keyword">echo</span> <span class="hl-string">&quot;Hi&quot;</span><span class="hl-punctuation">;</span></code></pre>

$highlighter->highlight('x', 'cobol'); // null: a language it does not know
```

```html
<link rel="stylesheet" href="/vendor/phil/highlight/themes/auto.css">
```

## Prérequis

PHP 8.5 ou plus récent. Rien d'autre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
