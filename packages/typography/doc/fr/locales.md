<!-- phil-dev source-sha256=4a26c934ec4bf7b2400fd8973c0bfbcfad1877f295fea6f4cc3c14ecd355857c -->
# Locales

`Locale::parse()` lit une locale à partir d'une langue de deux ou trois lettres, puis d'une écriture facultative de
quatre lettres et d'une région facultative de deux lettres ou trois chiffres, joints par `_` ou `-`, dans n'importe
quelle casse : `fr`, `fr_CA`, `fr-ca`, `sr_Latn_RS`, `es_419`. Tout ce qui dépend de la locale la cherche de la forme
la plus précise à la langue seule : `sr_Latn_RS`, `sr_RS`, `sr_Latn`, `sr`.

## Préréglages

`Preset::names($locale)` donne les noms des correcteurs recommandés pour une locale, dans l'ordre où ils s'exécutent,
et `Preset::fixers($locale)` les correcteurs eux-mêmes, réglés pour elle. Toutes les langues reçoivent les mêmes
règles : normalisation Unicode, points de suspension, dimension, tiret, guillemets typographiques, apostrophe, aucune
espace avant une virgule, signes juridiques, unités. Le français ajoute son espacement. La césure vient en dernier quand
la bibliothèque a des motifs pour la langue et que `$hyphenation` vaut true ; le thaï reçoit à sa place les espaces de
largeur nulle de `word_break`, sous le même indicateur.

```php
Preset::names(Locale::parse('fr_CA'));
// unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma,
// space_before_punctuation, trademark, unit, hyphen

$typographer = Typographer::for('de_CH');              // the preset of the locale
$typographer = Typographer::for('de_CH', hyphenation: false);
```

`Typographers` garde un typographe par locale, pour 64 locales au plus, afin que des locales tirées des requêtes ne
puissent pas faire croître la mémoire d'un processus de longue durée. Il permet à une application de remplacer le
préréglage de certaines locales par des noms de correcteurs ; les correcteurs configurés pour `fr` servent aussi
`fr_BE` et `fr_CH` :

```php
$typographers = new Typographers('fr_CA', ['en' => ['ellipsis', 'smart_quotes', 'curly_quote']]);
$typographers->for()->fix($html);       // fr_CA, its preset
$typographers->for('en_GB')->fix($html); // the three fixers configured for en
```

## Guillemets

`QuoteStyles::for($locale)` donne les guillemets de la locale, d'après les délimiteurs du CLDR d'Unicode ; une langue
qui n'est pas listée reçoit les guillemets anglais.

| Locales | Principaux | Intérieurs |
|---|---|---|
| `en`, `af`, `da`, `eo`, `ga`, `gl`, `ko`, `mn`, `nl`, `pt`, `th`, `tr`, `vi`, `zh`, `es_419`, `es_MX`, `es_US` | “ ” | ‘ ’ |
| `fr`, `ca`, `el`, `es`, `eu`, `it`, `kk`, `pt_PT`, `sq` | « » | “ ” |
| `fr_CH`, `de_CH`, `de_LI`, `it_CH`, `rm`, `am`, `fa` | « » | ‹ › |
| `de`, `cs`, `et`, `hr`, `is`, `lt`, `mk`, `sk`, `sl` | „ “ | ‚ ‘ |
| `ru`, `uk`, `be` | « » | „ “ |
| `pl`, `ro` | „ ” | « » |
| `hu` | „ ” | » « |
| `bg` | „ “ | „ “ |
| `bs`, `sr` | „ “ | ‘ ’ |
| `ka` | „ “ | « » |
| `lv` | “ ” | „ “ |
| `nb`, `nn`, `no` | « » | ‘ ’ |
| `fi`, `sv` | ” ” | ’ ’ |
| `he` | ” ” | ’ ’ |
| `ar` | ” “ | ’ ‘ |
| `cy` | ‘ ’ | “ ” |
| `hy` | « » | « » |
| `ja`, `zh_Hant`, `zh_HK`, `zh_TW` | 「 」 | 『 』 |

## Styles de tiret

`DashStyle::for($locale)` donne le tiret cadratin collé (`this—that`) à l'anglais sans région et à l'anglais
américain, canadien et philippin, et le tiret demi-cadratin entouré d'espaces (`this – that`) à toutes les autres
locales, anglais britannique compris.

## Espacement du français

`FrenchSpacing::for($locale)` donne les règles du Canada à `fr_CA` et celles de la France à toutes les autres locales
françaises.

| Avant ou à l'intérieur | France, Belgique, Suisse… | Canada (Office québécois de la langue française) |
|---|---|---|
| `;` `!` `?` | espace fine insécable (U+202F) | aucune espace |
| `:` | espace insécable (U+00A0) | espace insécable (U+00A0) |
| `« »` | espace insécable (U+00A0) | espace insécable (U+00A0) |

Le préréglage donne `SpaceBeforePunctuation` à toute locale dont la langue est `fr` ; une autre langue qui le veut
ajoute elle-même le correcteur.
