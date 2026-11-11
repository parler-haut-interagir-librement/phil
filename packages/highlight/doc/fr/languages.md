<!-- phil-dev source-sha256=b3d818c0c0ab832710811eb49eed519b3298f90a81cb866f267ee408a52df95c -->
# Langages

Chaque langage est demandé par son nom ou l'un de ses alias, dans n'importe quelle casse :
`Highlighter::default()->toHtml($code, 'TS')` lit du TypeScript.

| Langage | Nom | Alias | Analyseur lexical |
|---|---|---|---|
| Bash et autres shells | `bash` | `sh`, `shell`, `zsh`, `ksh` | `Code` avec `Definitions::bash()` |
| C | `c` | `h` | `Code` avec `Definitions::c()` |
| C++ | `cpp` | `c++`, `cc`, `cxx`, `hpp`, `hh`, `hxx` | `Code` avec `Definitions::cpp()` |
| C# | `csharp` | `cs`, `c#`, `dotnet` | `Code` avec `Definitions::csharp()` |
| CSS | `css` | — | `StyleSheet` |
| Diffs et correctifs | `diff` | `patch`, `udiff` | `Diff` |
| Dockerfile | `dockerfile` | `docker`, `containerfile` | `Dockerfile`, arguments lus comme du shell |
| Go | `go` | `golang` | `Code` avec `Definitions::go()` |
| HTML | `html` | `htm`, `xhtml`, `vue`, `svelte` | `Markup`, les scripts comme du JavaScript et les styles comme du CSS |
| INI, `.env`, propriétés | `ini` | `dosini`, `cfg`, `conf`, `properties`, `env`, `dotenv`, `editorconfig`, `gitconfig` | `Ini` |
| Java | `java` | `jsp` | `Code` avec `Definitions::java()` |
| JavaScript | `javascript` | `js`, `mjs`, `cjs`, `jsx`, `node` | `Code` avec `Definitions::javascript()` |
| JSON | `json` | `jsonc`, `json5`, `geojson`, `webmanifest` | `Code` avec `Definitions::json()` |
| Markdown | `markdown` | `md`, `mkd`, `mdx`, `commonmark`, `gfm` | `Markdown` |
| PHP | `php` | `php8`, `phtml` | `Php` : le code avec `Definitions::php()`, le balisage comme du HTML |
| Python | `python` | `py`, `py3`, `python3`, `gyp` | `Code` avec `Definitions::python()` |
| Ruby | `ruby` | `rb`, `gemspec`, `podspec`, `rake` | `Code` avec `Definitions::ruby()` |
| Rust | `rust` | `rs` | `Code` avec `Definitions::rust()` |
| SCSS | `scss` | `less`, `sass` | `StyleSheet(scss: true)` |
| SQL | `sql` | `mysql`, `postgresql`, `postgres`, `pgsql`, `sqlite`, `plsql`, `tsql`, `mariadb` | `Code` avec `Definitions::sql()` |
| Twig | `twig` | `html.twig`, `jinja`, `jinja2`, `nunjucks`, `njk`, `liquid` | `Twig` : les expressions par `Definitions::twigExpression()`, le texte comme du HTML |
| TypeScript | `typescript` | `ts`, `tsx`, `mts`, `cts` | `Code` avec `Definitions::typescript()` |
| XML | `xml` | `svg`, `xsl`, `xslt`, `xsd`, `rss`, `atom`, `plist`, `wsdl`, `xaml`, `mathml` | `Markup` |
| YAML | `yaml` | `yml` | `Yaml` |

## Langages de programmation

`Code` lit une source une seule fois et, à chaque position, essaie dans l'ordre : les espaces, les marques méta, une
ligne du préprocesseur, les commentaires, les textes entre guillemets, les variables, les annotations, les symboles,
les nombres, les mots, les expressions régulières, les opérateurs et la ponctuation.

| Quoi | Type | Langages |
|---|---|---|
| Mots réservés | `keyword` | Tous ; SQL dans n'importe quelle casse |
| Noms de types, et noms qui commencent par une majuscule | `type` | Tous ceux qui ont une liste ; les majuscules en PHP, JavaScript, TypeScript, Python, Ruby, Rust, Java, C# |
| `true`, `null`, `None`, `nil`… | `constant` | Tous |
| Fonctions que fournit le langage et qu'on appelle souvent sans parenthèses | `builtin` | Shell (`echo`, `cd`…), Python, Ruby, Go, SQL, JavaScript (`console`) |
| Un nom suivi de `(`, une macro Rust (`vec!`), un filtre Twig (`|upper`) | `function` | Tous |
| `$var`, `${var}`, `$?`, `@var`, `@@var` | `variable` | PHP, shell, Ruby, SQL |
| Textes entre guillemets, avec leurs préfixes (`f"…"`, `b"…"`, `u8"…"`, `$"…"`, `@"…"`) et littéraux de caractères | `string` | Tous ; une durée de vie Rust (`'a`) n'est pas un caractère |
| Clés JSON | `property` | JSON |
| Nombres : `0x1F`, `1_000u32`, `.5`, `1e-3` | `number` | Tous |
| Commentaires : de ligne, de bloc | `comment` | Tous |
| Littéraux d'expressions régulières là où une valeur est attendue | `regex` | JavaScript, TypeScript, Ruby |
| Décorateurs et annotations (`@Override`), attributs PHP et Rust (`#[…]`), balises PHP, lignes du préprocesseur, shebangs | `meta` | Python, JavaScript, TypeScript, Java ; PHP, Rust ; C, C++, C# ; shell, Python, Ruby, JavaScript |
| Symboles Ruby (`:name`) | `constant` | Ruby |

Un nom de méthode Ruby peut se terminer par `?` ou `!`, comme `empty?`. Un guillemet qui peut s'étendre sur plusieurs
lignes le fait : les gabarits littéraux de JavaScript, les triples guillemets de Python, les blocs de texte de Java et
de C#, les chaînes brutes de Go, les chaînes du shell et du SQL. Un guillemet doublé en SQL (`'it''s'`) se lit comme
une seule chaîne.

## Balisage

`Markup` lit les balises, les attributs et leurs valeurs, les commentaires, les références de caractères (`&amp;`,
`&#38;`), les sections CDATA, les doctypes et les instructions de traitement. En HTML, le contenu de `<script>` est lu
comme du JavaScript et celui de `<style>` comme du CSS, jusqu'à la balise de fin dans n'importe quelle casse.

## Feuilles de style

`StyleSheet` lit chaque instruction comme une règle @ (`@media`, un `keyword`), un sélecteur ou une déclaration : au
premier niveau, une instruction est un sélecteur ; à l'intérieur d'un bloc, c'est un sélecteur imbriqué quand un `{`
vient avant le prochain `;` ou `}`, et une déclaration sinon. Une propriété est une `property`, une propriété
personnalisée (`--x`) une `variable` ; dans une valeur, les nombres avec leurs unités et les couleurs (`#fff`) sont des
`number`, les fonctions des `function`, `!important` un `keyword`, et l'adresse dans `url()`, entre guillemets ou non,
une `string`. Le SCSS ajoute les `$variables`, les interpolations `#{…}` et les commentaires de ligne.

## Langages par lignes

| Langage | Ce qu'il colore |
|---|---|
| YAML | Commentaires, `---` et `...`, tirets de séquence, clés simples ou entre guillemets, scalaires entre guillemets et simples (une constante comme `true`, `yes`, `on`, `null`, `~`…, un nombre ou une chaîne), ancres et alias, étiquettes, collections en flux, et les lignes des scalaires de bloc (`|`, `>`) |
| INI | Commentaires ouverts par `;` ou `#`, `[sections]`, clés, `=` et `:`, valeurs (entre guillemets, nombres, constantes ou chaînes), et un commentaire après une valeur et une espace |
| Dockerfile | Commentaires, l'instruction qui ouvre une ligne dans n'importe quelle casse, et ses arguments, prolongés par une barre oblique inverse, lus comme du shell |
| Diff | En-têtes de fichier (`diff`, `index`, `---`, `+++`), en-têtes de blocs (`@@`), lignes ajoutées (`+`, `>`) et retirées (`-`, `<`), et notes `\ No newline` |
| Markdown | Blocs de code clôturés, titres, séparateurs thématiques, marques de citation et de liste, cases de tâche ; code en ligne, emphase forte, emphase, liens, images et liens automatiques fermés sur leur ligne |

## Langages mixtes

`Php` lit une source sans balise d'ouverture comme du code ; sinon, du balisage jusqu'à chaque `<?php` ou `<?=`, et du
code jusqu'au `?>` suivant. `Twig` lit les commentaires, les délimiteurs des affichages et des balises avec leurs
contrôles d'espaces, les expressions entre eux, et le texte qui les entoure comme du HTML. Chaque portion de balisage
est lue à part : une balise coupée par une expression Twig (`<a href="{{ url }}">`) perd quelques couleurs après la
coupure.

## Ce qui est laissé tel quel

Les analyseurs lexicaux colorent ce dont un lecteur a besoin ; ils n'analysent pas la syntaxe. Les heredocs et les
nowdocs, les commentaires de bloc imbriqués, les chaînes brutes Rust aux délimiteurs `#`, les chaînes brutes C++ et
les expressions interpolées dans les chaînes prennent la couleur de ce qui les contient. Les marques en ligne Markdown
doivent se fermer sur leur ligne. Une source d'un langage lue comme un autre n'échoue jamais : elle revient avec moins
de couleurs.
