<!-- phil-dev source-sha256=d8e6daa3b9003f7cc31ffd4a76e0c980e44690ef340de06e9a540adbffd9e300 -->
# Correcteurs

Chaque correcteur implémente `Fixer` (`fix(Run $run): Run`) et peut s'utiliser seul, dans votre propre `Typographer`
ou par un préréglage. Ils sont listés ici dans l'ordre où les préréglages les exécutent. L'ordre compte : les
guillemets sont produits avant que les espaces du français soient placées autour d'eux, et la césure vient en dernier.

| Nom dans les préréglages | Classe | Ce qu'il fait |
|---|---|---|
| `unicode_normalization` | `UnicodeNormalization` | La forme de normalisation C : `e` suivi d'un accent combinant devient `é` |
| `ellipsis` | `Ellipsis` | `...` et `. . .` deviennent `…` |
| `dimension` | `Dimension` | `1920 x 1080`, `20cm x 30cm`, `6' x 8'` prennent `×` ; `0x1F` reste tel quel |
| `dash` | `Dash` | `---` → `—`, `--` → `–` ; `1914-1918` → `1914–1918` ; ` - ` → le tiret du style |
| `smart_quotes` | `SmartQuotes` | `"…"` et `'…'` deviennent les guillemets de la langue |
| `curly_quote` | `CurlyQuote` | Chaque `'` restant devient l'apostrophe `’` |
| `no_space_before_comma` | `NoSpaceBeforeComma` | `word , word` et `word ,word` deviennent `word, word` |
| `space_before_punctuation` | `SpaceBeforePunctuation` | Les espaces du français de `: ; ! ?` et des `« »` |
| `trademark` | `Trademark` | `(tm)` → `™`, `(r)` → `®`, `(c) 2026` → `© 2026`, `(p) 1999` → `℗ 1999` |
| `unit` | `Unit` | L'espace de `50 km`, `20 °C`, `75 %`, `12,50 $` devient une espace insécable |
| `hyphen` | `Hyphen` | Des traits d'union conditionnels dans les mots de cinq lettres ou plus, là où les motifs le permettent |
| `word_break` | `WordBreak` | Des espaces de largeur nulle entre les mots thaïs |

## Points de suspension

Trois points collés ou séparés par des espaces simples. Quatre points donnent des points de suspension et un point
final.

## Dimension

La lettre `x` ou `X`, avec ou sans espace de part et d'autre, entre deux nombres. Le premier nombre peut porter une
unité (`mm`, `cm`, `dm`, `m`, `km`, `in`, `ft`, `px`, `pt`, `em`, `rem`) ou une marque de pied ou de pouce. Une chaîne
comme `10x20x30` est corrigée d'un bout à l'autre. Un nombre hexadécimal (`0x1F`), un nombre après une lettre (`A4x5`)
ou après un séparateur décimal est laissé tel quel.

## Tiret

| Écrit | Devient |
|---|---|
| `---` | tiret cadratin `—` |
| `--` | tiret demi-cadratin `–` |
| `1914-1918`, `12-15`, `(5-7)` | tiret demi-cadratin entre des nombres d'un à quatre chiffres |
| `2026-11-06`, `555-123-4567`, `1.5-2` | inchangé : une chaîne ou un nombre décimal n'est pas un intervalle |
| `this - that` | `this – that` (`DashStyle::Spaced`) ou `this—that` (`DashStyle::Closed`) |

Un numéro de téléphone écrit en deux groupes, `555-1234`, ressemble à un intervalle et reçoit un tiret demi-cadratin.
Écrivez-le avec un trait d'union insécable (U+2011), ou retirez le correcteur là où de tels numéros sont courants.

## Guillemets typographiques

Un guillemet double ouvre après le début du bloc, une espace ou une marque ouvrante (`( [ { « “ ‘ „ ‹ - – — / >` ou un
autre guillemet), quand un caractère le suit ; sinon, il ferme. `>` compte comme marque ouvrante parce qu'une balise
échappée en texte, `&lt;b&gt;"word"`, se termine par lui, tout comme un marqueur de texte cité. Un guillemet simple
n'est un guillemet intérieur que lorsqu'il ouvre à cette position et qu'un guillemet simple correspondant ferme avant
une espace, un signe de ponctuation ou `<`. Tout autre guillemet simple est une apostrophe, laissée à `CurlyQuote`. Les
guillemets viennent d'un `QuoteStyle`, anglais par défaut :

```php
new SmartQuotes(new QuoteStyle('«', '»', '‹', '›'));
```

Limites connues : `rock 'n' roll` est lu comme une citation intérieure de `n` ; un guillemet double à l'intérieur d'une
citation double est une citation à part entière, jamais une citation intérieure.

## Apostrophe

Un simple remplacement de `'` par `’` : placé après `SmartQuotes`, il transforme les apostrophes que ce correcteur a
laissées. Utilisé seul, il transforme chaque guillemet simple droit.

## Aucune espace avant une virgule

Les espaces avant une virgule sont retirées ; quand une lettre suit directement la virgule, une espace est placée
après elle. `1,5` et `a ,5` gardent leurs chiffres ensemble.

## Espacement du français

`SpaceBeforePunctuation` écrit l'espace avant la ponctuation haute qui termine un mot, quoi que l'auteur ait tapé
(rien, une espace, une espace insécable, une espace fine), et à l'intérieur des guillemets français. Voir
[Locales](locales.md#espacement-du-français) pour les règles de la France et du Canada. Le deux-points de `12:30` ou
de `https://` n'est pas suivi d'une espace et reste tel quel ; `?!` prend une seule espace, avant la première marque.

## Marques de commerce

`(tm)` et `(r)`, dans l'une ou l'autre casse, sont attachés au mot qui les précède. `(c)` et `(p)` deviennent `©` et
`℗` seulement quand une année suit, liée à eux par une espace insécable : l'élément `(c)` d'une liste reste donc tel
qu'écrit. `© 2026` reçoit lui aussi son espace insécable.

## Unité

Seule une espace existante est changée : le correcteur n'en ajoute jamais. Les unités sont listées dans
`Unit::UNITS` : les unités SI et leurs multiples courants, les tailles de données, les unités typographiques, les
vitesses, les températures, les pourcentages et les symboles monétaires. Un mot qui commence simplement comme une
unité (`5 mice`) est laissé tel quel.

## Césure

`Hyphen` prend un `Hyphenator` et une longueur minimale, cinq caractères par défaut. Un mot est une suite de lettres et
de signes combinants ; les chiffres, les émojis et les symboles le terminent. Un mot qui contient déjà un trait d'union
conditionnel est laissé tel que son auteur l'a écrit : corriger deux fois donne donc le même texte. Voir
[Césure](hyphenation.md).

## Coupure de mots

`WordBreak` place une espace de largeur nulle (U+200B) entre les mots de chaque suite de caractères thaïs, aux
occasions de coupure de ligne qu'ICU trouve avec son dictionnaire thaï, jamais avant la marque de répétition `ๆ`. Les
autres écritures sont laissées telles quelles, et une espace de largeur nulle déjà écrite termine une suite : corriger
deux fois donne donc le même texte. Voir [Césure](hyphenation.md#coupures-de-mots-du-thaï).

## Écrire un correcteur

```php
final readonly class NoBreakAfterShortWords implements Fixer
{
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => preg_replace('/\b(\p{L}{1,2}) /u', "\$1\u{A0}", $text) ?? $text);
    }
}
```

`Run::replace()` donne au correcteur la partie entre ses caractères voisins et ne garde le résultat que s'ils sont
inchangés ; `Run::map()` donne la partie et ses voisins séparément ; `Run::characters()` donne tous les caractères de
la suite d'un coup. Un correcteur ne doit réécrire que du texte : ce qu'il retourne est écrit dans des nœuds texte, si
bien que `<` reste un caractère.
