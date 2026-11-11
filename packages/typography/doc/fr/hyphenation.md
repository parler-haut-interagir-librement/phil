<!-- phil-dev source-sha256=4f6466f453af267a0671b5c2366d91ce426d8e01ad92fee0a298e7637da23b6a -->
# Césure

## L'algorithme de Liang

`Hyphenator` implémente l'algorithme que Frank Liang a conçu pour TeX, écrit de zéro. Un motif est une chaîne de
lettres avec des chiffres entre elles (`.ach4`, `a1b`, `hy3ph`), le point marquant le début ou la fin d'un mot. Chaque
motif trouvé dans le mot, encadré de points, élève les chiffres entre ses lettres. Une coupure est permise là où le
chiffre le plus élevé est impair, jamais plus près de l'une ou l'autre extrémité que les longueurs minimales de la
langue, et jamais avant un signe combinant. Un mot listé parmi les exceptions (`ta-ble`, `project`) se coupe là où
l'exception l'indique.

```php
$hyphenator = Hyphenator::for('en_US');
$hyphenator->breaks('hyphenation');          // [2, 6]
$hyphenator->hyphenate('hyphenation', '-');  // hy-phen-ation
$hyphenator->hyphenate('hyphenation');       // with soft hyphens (U+00AD), the default
```

Un mot dont la forme en minuscules n'a pas autant de caractères que le mot lui-même (`İstanbul`) est laissé entier.
Les longueurs minimales de la langue peuvent être remplacées : `new Hyphenator($patterns, left: 3, right: 3)` ; aucune
n'est inférieure à un.

## Langues

`Dictionary` trouve les motifs d'une locale par ses candidates et une table d'alias, ou accepte directement une
étiquette de motifs (`de-1901`, `la-x-classic`, `fi-x-school`). La plupart des motifs sont ceux de hyph-utf8 (version
2026-02-21), les motifs de césure de TeX en UTF-8, importés pour chaque langue dont la licence les permet dans un
paquet MIT. Chaque fichier est une copie intégrale, et `data/LICENSES` reproduit l'en-tête du fichier source avec son
droit d'auteur et sa licence. Quand un fichier est offert sous plusieurs licences, c'est la licence permissive qui est
utilisée. Sept langues dont les motifs TeX portent une licence réciproque ou n'en portent aucune ont des motifs écrits
pour cette bibliothèque, sous sa licence MIT : voir
[Motifs écrits pour cette bibliothèque](#motifs-écrits-pour-cette-bibliothèque).

| Étiquette | Langue | Locales | Gauche / droite | Licence utilisée |
|---|---|---|---|---|
| `af` | Afrikaans | `af` | 1 / 2 | MIT |
| `as` | Assamais | `as` | 1 / 1 | MIT |
| `be` | Biélorusse | `be` | 2 / 2 | MIT |
| `bg` | Bulgare | `bg` | 2 / 2 | Avis de type BSD |
| `ca` | Catalan | `ca` | 2 / 2 | LPPL |
| `cop` | Copte | `cop` | 1 / 1 | MIT |
| `cs` | Tchèque | `cs` | 2 / 2 | MIT, écrits pour cette bibliothèque |
| `cu` | Slavon d'église | `cu` | 1 / 2 | MIT |
| `cy` | Gallois | `cy` | 2 / 3 | LPPL |
| `da` | Danois | `da` | 2 / 2 | LPPL |
| `de-1901` | Allemand, orthographe traditionnelle | — | 2 / 2 | MIT |
| `de-1996` | Allemand, orthographe réformée | `de` | 2 / 2 | MIT |
| `de-ch-1901` | Allemand, orthographe suisse traditionnelle | `de_CH`, `de_LI` | 2 / 2 | MIT |
| `el-monoton` | Grec moderne, orthographe monotonique | `el` | 1 / 1 | LPPL |
| `el-polyton` | Grec moderne, orthographe polytonique | — | 1 / 1 | LPPL |
| `en-gb` | Anglais, orthographe britannique | `en_AU`, `en_GB`, `en_IE`, `en_IN`, `en_NZ`, `en_UK`, `en_ZA` | 2 / 3 | MIT |
| `en-us` | Anglais, orthographe américaine | `en` | 2 / 3 | Avis tout permissif |
| `eo` | Espéranto | `eo` | 2 / 2 | LPPL |
| `es` | Espagnol | `es` | 2 / 2 | MIT/X11 |
| `et` | Estonien | `et` | 2 / 3 | MIT |
| `eu` | Basque | `eu` | 2 / 2 | Licence de données de type Unicode |
| `fi` | Finnois | `fi` | 2 / 2 | Avis de libre distribution |
| `fi-x-school` | Finnois, césure scolaire | — | 1 / 1 | MIT |
| `fr` | Français | `fr` | 2 / 2 | MIT |
| `fur` | Frioulan | `fur` | 2 / 2 | MIT |
| `ga` | Irlandais | `ga` | 2 / 3 | MIT |
| `gl` | Galicien | `gl` | 2 / 2 | Unlicence |
| `grc` | Grec ancien | `grc` | 1 / 1 | LPPL |
| `gu` | Goudjarati | `gu` | 1 / 1 | MIT |
| `hr` | Croate | `hr` | 2 / 2 | LPPL |
| `hsb` | Haut-sorabe | `hsb` | 2 / 2 | LPPL |
| `hu` | Hongrois | `hu` | 2 / 2 | MIT, écrits pour cette bibliothèque |
| `hy` | Arménien | `hy` | 2 / 2 | MIT, écrits pour cette bibliothèque ; expérimental, désactivé par défaut |
| `ia` | Interlingua | `ia` | 2 / 2 | LPPL |
| `id` | Indonésien | `id` | 2 / 2 | MIT, écrits pour cette bibliothèque |
| `is` | Islandais | `is` | 2 / 2 | LPPL |
| `it` | Italien | `it` | 2 / 2 | LPPL |
| `ka` | Géorgien | `ka` | 1 / 2 | MIT |
| `kk` | Kazakh | `kk` | 2 / 2 | Domaine public |
| `kmr` | Kurmandji, kurde du Nord | `kmr`, `ku` | 2 / 2 | LPPL |
| `kn` | Kannada | `kn` | 1 / 1 | MIT |
| `la` | Latin | `la` | 2 / 2 | MIT |
| `la-x-classic` | Latin classique | — | 2 / 2 | MIT |
| `la-x-liturgic` | Latin liturgique | — | 2 / 2 | MIT |
| `lt` | Lituanien | `lt` | 2 / 2 | MIT |
| `lv` | Letton | `lv` | 2 / 2 | MIT, écrits pour cette bibliothèque |
| `mk` | Macédonien | `mk` | 2 / 2 | MIT, écrits pour cette bibliothèque ; expérimental, désactivé par défaut |
| `ml` | Malayalam | `ml` | 1 / 1 | MIT |
| `mn-cyrl` | Mongol, écriture cyrillique | `mn` | 2 / 2 | LPPL |
| `mr` | Marathi | `mr` | 1 / 1 | MIT |
| `mul-ethi` | Plusieurs langues qui utilisent l'écriture éthiopienne | `am`, `ti` | 1 / 1 | MIT |
| `nl` | Néerlandais | `nl` | 2 / 2 | MIT |
| `nn` | Norvégien nynorsk | `nn`, `nb`, `no` | 2 / 2 | Avis tout permissif |
| `oc` | Occitan | `oc` | 2 / 2 | MIT |
| `or` | Odia, oriya | `or` | 1 / 1 | MIT |
| `pa` | Pendjabi | `pa` | 1 / 1 | MIT |
| `pi` | Pali | `pi` | 1 / 2 | MIT |
| `pl` | Polonais | `pl` | 2 / 2 | MIT |
| `pms` | Piémontais | `pms` | 2 / 2 | MIT |
| `pt` | Portugais | `pt` | 2 / 3 | Licence BSD à 3 clauses |
| `rm` | Romanche | `rm` | 2 / 2 | MIT |
| `ro` | Roumain | `ro` | 2 / 2 | MIT, écrits pour cette bibliothèque |
| `ru` | Russe | `ru` | 2 / 2 | LPPL |
| `sa` | Sanskrit | `sa` | 1 / 3 | Avis de libre utilisation |
| `sh-cyrl` | Serbo-croate, écriture cyrillique | `sh_Cyrl`, `sr` | 1 / 2 | LPPL |
| `sh-latn` | Serbo-croate, écriture latine | `bs`, `sh`, `sr_Latn` | 2 / 2 | LPPL |
| `sk` | Slovaque | `sk` | 2 / 3 | MIT |
| `sl` | Slovène | `sl` | 2 / 2 | LPPL |
| `sq` | Albanais | `sq` | 2 / 2 | MIT |
| `sv` | Suédois | `sv` | 2 / 2 | LPPL |
| `ta` | Tamoul | `ta` | 1 / 1 | MIT |
| `te` | Télougou | `te` | 1 / 1 | MIT |
| `tk` | Turkmène | `tk` | 2 / 2 | MIT |
| `tr` | Turc | `tr` | 2 / 2 | LPPL |
| `uk` | Ukrainien | `uk` | 2 / 2 | MIT |
| `zh-latn-pinyin` | Chinois mandarin, translittération pinyin | `zh_Latn` | 2 / 2 | MIT |

`de` donne l'orthographe réformée, `de_CH` et `de_LI` l'orthographe suisse traditionnelle, `de-1901` l'orthographe
traditionnelle. `el` donne l'orthographe monotonique, `el-polyton` l'orthographe polytonique. Le norvégien bokmål et le
nynorsk partagent les motifs de hyph-no, sous l'étiquette du nynorsk. Le serbe est lu par les motifs du serbo-croate,
en cyrillique pour `sr` et en écriture latine pour `sr_Latn`.

## Motifs écrits pour cette bibliothèque

Les motifs TeX du tchèque (GPL 2 ou ultérieure seulement), du hongrois (MPL 1.1, GPL 2 ou LGPL 2.1 seulement), de
l'arménien (LGPL seulement), de l'indonésien (GPL seulement), du letton (LGPL 2.1 ou GPL 2 seulement), du macédonien
(GPL seulement) et du roumain (aucune licence déclarée) ne peuvent pas être fournis dans un paquet MIT. Ces sept
langues ont plutôt des motifs écrits de zéro, à partir des règles orthographiques publiées de chaque langue : aucun
motif, aucune liste d'exceptions ni aucune liste de mots sous une autre licence n'a été lu pour les écrire. Les règles
sont écrites sous forme de données dans `tests/Generator/SyllableRules.php`, `tests/Generator/PatternGenerator.php` les
transforme en motifs de Liang, `php bin/generate-patterns.php` écrit les fichiers `<tag>.pat.txt`, et les tests
vérifient que les fichiers fournis sont ceux que donnent les règles.

Ce sont **des règles, pas un dictionnaire, moins complètes que les motifs TeX**. Une règle de syllabation voit des
lettres, pas des mots : elle ne peut pas distinguer un mot composé ou un préfixe d'une syllabe, ni une diphtongue de
deux voyelles écrites de la même façon. Là où les règles d'une langue en dépendent, les motifs ne permettent aucune
coupure plutôt qu'une mauvaise. Ils manquent donc des coupures que trouvent les motifs TeX, et un mot hors des règles
peut encore se couper là où un dictionnaire ne le ferait pas. Chaque langue est testée sur une liste de mots courants
qui doivent se couper exactement là où ses règles le permettent.

| Étiquette | Règles suivies | Ce que font les motifs | Laissé sans coupure |
|---|---|---|---|
| `cs` | Les règles de coupure des mots de l'Institut de la langue tchèque de l'Académie des sciences | Une consonne entre deux voyelles passe à la ligne suivante ; dans un groupe, la dernière, ou une occlusive ou une fricative suivie de `r`, `l` ou `ř` (`ses-tra`) ; `ch` est une seule lettre ; un `r` ou un `l` à l'initiale d'un mot entre deux consonnes porte une syllabe (`vl-na`, `Br-no`) | Les préfixes `roz`, `bez`, `nad`, `pod` et `před` devant une voyelle ; les groupes de `d` et d'une liquide, qu'un préfixe peut terminer |
| `hu` | Les règles orthographiques de l'Académie hongroise des sciences | Une consonne entre deux voyelles passe à la ligne suivante ; dans un groupe, seulement la dernière (`temp-lom`, `prog-ram`) ; deux voyelles côte à côte sont séparées (`ki-a-dás`) ; `cs`, `dz`, `dzs`, `gy`, `ly`, `ny`, `sz`, `ty` et `zs` sont des lettres simples | Les digrammes doublés, écrits en abrégé et rétablis à la coupure (`asszony`), ce qu'un trait d'union conditionnel ne peut pas faire ; les préfixes `meg`, `fel` et `szét` devant une voyelle ; les mots composés, là où `sz` et `zs` se rencontrent (`egészség`) |
| `hy` | Les règles de coupure de ligne des grammaires scolaires arméniennes, dans l'orthographe réformée | Une consonne entre deux voyelles passe à la ligne suivante ; dans un groupe, la dernière, avec le `յ` qui suit une consonne (`գի-տու-թյուն`) ; `ու` est une seule voyelle | Les coupures voisines de `և` ; les deux premières lettres d'un mot qui commence par deux consonnes, où la voyelle n'est pas écrite |
| `id` | Les directives orthographiques générales de l'Agence de développement et de promotion de la langue | Une consonne entre deux voyelles passe à la ligne suivante ; de deux, la seconde ; de trois ou plus, toutes sauf la première (`in-stru-men`) ; `ng`, `ny`, `kh` et `sy` sont des consonnes simples ; deux voyelles côte à côte sont séparées (`sa-at`) | Les diphtongues `ai`, `au`, `ei` et `oi`, ainsi que `eu` et `ou`, même là où ce sont deux voyelles (`main`) |
| `lv` | Les règles orthographiques de l'Académie lettone des sciences | Une consonne entre deux voyelles passe à la ligne suivante ; dans un groupe, la dernière (`raks-tīt`) ; `dz` et `dž` ne sont pas coupés | Les diphtongues, `o` compris ; les préfixes `aiz` et `pār` devant une voyelle |
| `mk` | L'orthographe de la langue littéraire macédonienne | Une consonne entre deux voyelles passe à la ligne suivante ; dans un groupe, toutes sauf la première (`сес-тра`) | Un `р` syllabique sans voyelle écrite (`срце`) ; les préfixes `без`, `раз`, `под`, `над` et `пред` devant une voyelle |
| `ro` | Le dictionnaire orthographique de l'Académie roumaine | Une consonne entre deux voyelles passe à la ligne suivante ; de deux, la seconde, sauf une occlusive ou `f`, `h`, `v` devant `l` ou `r` (`ta-blou`) ; de trois ou plus, toutes sauf la première, sauf `lpt`, `mpt`, `mpț`, `ncș`, `nct`, `ncț`, `ndv`, `rct`, `rtf` et `stm` (`sculp-tor`) ; `ch` et `gh` sont des consonnes simples | Les voyelles côte à côte, diphtongue et hiatus s'écrivant de la même façon ; un `i` final après une consonne, qui n'est pas une syllabe (`lupi`, `membri`) |

### Langues expérimentales

Les règles de l'arménien et du macédonien sont les moins sûres des sept : elles attendent encore la lecture d'un
locuteur de chaque langue. Leurs motifs sont fournis, mais ils sont **expérimentaux et désactivés par défaut** : un
`Dictionary` ne les sert que lorsqu'il est construit avec leurs étiquettes, listées dans `Dictionary::EXPERIMENTAL`.
Sans elles, `hy` et `mk` n'ont aucun motif : `supports()` vaut false, les préréglages retirent `hyphen` et
`patterns()` lance `UnsupportedLanguage`.

```php
$dictionary = new Dictionary(experimental: ['hy']);    // Armenian served, Macedonian still off
$dictionary = new Dictionary(experimental: Dictionary::EXPERIMENTAL);
Typographer::for('hy_AM', dictionary: $dictionary);
```

Une étiquette hors de `Dictionary::EXPERIMENTAL` donnée à cet endroit ne change rien : les autres langues sont toujours
servies. Dans Symfony, `phil/typography-bundle` règle la liste avec `experimental_hyphenation`.

Une application qui accepte la licence des motifs TeX de l'une de ces langues et veut leur complétude les charge
elle-même, comme le montre [Vos propres motifs](#vos-propres-motifs).

## Coupures de mots du thaï

Le thaï s'écrit sans espace entre les mots et coupe une ligne entre deux mots sans trait d'union visible, ce qu'un
trait d'union conditionnel ne peut pas exprimer. Son préréglage exécute le correcteur `word_break` au lieu de
`hyphen` : `WordBreak` place une espace de largeur nulle (U+200B) à chaque occasion de coupure de ligne qu'ICU trouve
dans une suite de caractères thaïs, par `IntlBreakIterator` et son dictionnaire thaï. Un navigateur sans dictionnaire
propre coupe ainsi quand même la ligne entre deux mots. Le correcteur ne s'exécute que là où la césure est demandée
(`Typographer::for('th', hyphenation: false)` le retire). `ext-intl` est déjà un prérequis de la bibliothèque, pour
`Normalizer` : le correcteur n'a donc besoin de rien de plus. Un correcteur n'ajoute jamais de balisage : la coupure est
donc un caractère, pas un élément `<wbr>`. Les deux se comportent de la même façon dans un navigateur.

```php
Typographer::for('th_TH')->fixText('สวัสดีครับ'); // "สวัสดี\u{200B}ครับ"
```

Les coupures sont celles de l'ICU auquel la construction de PHP est liée, et peuvent varier légèrement d'une version
d'ICU à l'autre.

## Écartées

| Langue | Pourquoi |
|---|---|
| Serbe, motifs cyrilliques propres (`sr-cyrl`) | GPL seulement ; le serbe est servi par `sh-cyrl` et `sh-latn` |
| Zoulou (`zu`) | Aucun motif dans hyph-utf8 |

Pour ces étiquettes, et pour le thaï, `Dictionary::supports()` vaut false, les préréglages retirent `Hyphen`, et
`Hyphenator::for()` lance `UnsupportedLanguage`.

## Vos propres motifs

`new Dictionary($directory)` lit `<tag>.pat.txt` et `<tag>.hyp.txt` dans un autre répertoire, pour les mêmes
étiquettes. Un fichier d'exceptions absent signifie qu'il n'y en a aucune, et `supports()` ne vaut true que lorsque le
fichier de motifs est présent. Les motifs peuvent aussi être donnés directement :

```php
$patterns = Patterns::parse(file_get_contents('hyph-cs.pat.txt'), file_get_contents('hyph-cs.hyp.txt'), 2, 3);
$hyphenator = new Hyphenator($patterns);
```

C'est ainsi qu'une application utilise des motifs sous une licence que cette bibliothèque ne fournit pas, sous sa
propre responsabilité.

## Performance

Un dictionnaire lit les motifs d'une langue une fois et les garde : les motifs allemands, les plus volumineux, prennent
environ quarante millisecondes à lire. `Typographers` partage un seul dictionnaire entre ses typographes ; un processus
de longue durée devrait garder ses typographes plutôt que de les construire à chaque requête.
