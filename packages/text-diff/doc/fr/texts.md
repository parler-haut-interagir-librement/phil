<!-- phil-dev source-sha256=2e0e010c272f9fbc74f2a918cc5b3b32593065d6ad707cc1e41bfa9c04a91a44 -->
# Comparer des textes

`TextDiff` compare deux textes et retourne un `Diff` : les opérations qui produisent le nouveau texte à partir de
l'ancien.

```php
$differ = new TextDiff();

$diff = $differ->words('one two three', 'one 2 three');
$diff = $differ->lines($before, $after);
$diff = $differ->paragraphs($before, $after);
$diff = $differ->html($beforeHtml, $afterHtml);
$diff = $differ->compare($before, $after, Tokenizer::Lines);   // the same, by its tokenizer
```

## Découpeurs

Un texte est comparé jeton par jeton. Les jetons d'un texte, mis bout à bout, le redonnent octet pour octet : les
espaces et les sauts de ligne sont aussi des jetons. Rien n'est donc perdu, et les deux textes peuvent toujours être
reconstruits à partir de la différence.

| `Tokenizer` | Jetons | Pour |
|---|---|---|
| `Words` | les suites de lettres, de signes diacritiques, de chiffres et de `_` ; les suites d'espaces ; chaque autre caractère seul (un graphème entier : un émoji avec son modificateur reste un seul jeton) ; chaque caractère d'une écriture sans espaces entre les mots seul (voir plus bas) | la prose, les titres, les champs courts |
| `Lines` | chaque ligne sans son saut ; chaque saut (`\r\n`, `\r` ou `\n`) | le code, la configuration, la poésie |
| `Paragraphs` | chaque paragraphe, avec ses sauts de ligne simples ; chaque suite de lignes vides entre deux paragraphes | les longs textes en Markdown ou en texte brut, quand une vue par mot est trop fine |
| `Html` | comme `Words`, mais une balise avec ses attributs, un commentaire ou une référence de caractère (`&amp;`, `&#233;`) forme un seul jeton | le HTML : les pages rendues, le HTML en ligne des champs UJO |

Le chinois, le japonais, le thaï, le lao, le khmer et le birman ne mettent pas d'espace entre les mots : une suite
de lettres y forme une phrase entière. Dans `Words` et `Html`, chaque caractère des écritures han, hiragana, katakana,
thaï, lao, khmer et birmane — et la marque d'allongement `ー` du japonais — forme un jeton seul, avec les signes qui
lui appartiennent : un graphème, tel qu'Unicode les découpe. Un tel texte se compare caractère par caractère, un mot
modifié se lit comme ses caractères modifiés, et les mots latins, les chiffres et le hangeul qui l'entourent restent
entiers.

```php
$differ->words('私は猫が好き', '私は犬が好き')->changes();   // one replacement, '猫' → '犬', at 2/2
```

Un texte qui n'est pas de l'UTF-8 valide ne peut pas être découpé : `InvalidText` est levée.

## Opérations

`Diff::$operations` est une liste d'`Operation`, chacune composée d'une `kind` — `OperationKind::Equal`, `Delete` ou
`Insert` — et des `tokens` qu'elle conserve, supprime ou insère ; `text()` les met bout à bout. La liste est
normalisée :

- **les suites conservées et les changements alternent** : deux suites conservées ne sont jamais voisines, ni deux
  changements ;
- **dans chaque changement, les jetons supprimés précèdent les jetons insérés** ;
- **les espaces seuls entre deux changements en font partie** : `a b` → `c d` est un seul remplacement de `a b` par
  `c d`, et non deux remplacements autour d'un espace conservé ; c'est ainsi qu'un lecteur voit une phrase réécrite.
  Les espaces conservés au début ou à la fin, ou à côté d'un mot conservé, restent conservés.

`Diff::old()` et `Diff::new()` redonnent les deux textes ; `isIdentical()` indique s'ils sont identiques.

## Changements

`Diff::changes()` énumère ce qui a changé, dans l'ordre de lecture, sous forme de `Change` :

| `ChangeKind` | `old` | `new` |
|---|---|---|
| `Insertion` | `''` | le texte inséré |
| `Deletion` | le texte supprimé | `''` |
| `Replacement` | le texte supprimé | le texte inséré à sa place |

`oldOffset` et `newOffset` indiquent où le changement se produit dans chaque texte, en points de code depuis le début :
on peut donc les utiliser avec `mb_substr()`, ou comme indices de `String` dans un navigateur pour les caractères du
plan multilingue de base.

```php
foreach ($differ->words('Été: un deux trois quatre', 'Été: zéro un trois cinq')->changes() as $change) {
    // insertion '' → 'zéro ' at 5/5, deletion 'deux ' → '' at 8/13, replacement 'quatre' → 'cinq' at 19/19
}
```

## L'algorithme et sa borne

Les jetons par lesquels les deux textes commencent, et ceux par lesquels ils finissent, sont d'abord mis de côté. Sur
ce qui reste, l'algorithme d'Eugene W. Myers (« An O(ND) Difference Algorithm and Its Variations », 1986) suit, pour
0, 1, 2… modifications, le chemin le plus avancé sur chaque diagonale du graphe des modifications, garde chaque
frontière, puis revient en arrière à travers elles. Le résultat est un script le plus court possible : aucun autre ne
transforme l'ancien texte en nouveau avec moins de jetons supprimés et insérés. Quand plusieurs scripts sont aussi
courts, celui qui est trouvé garde les correspondances les plus précoces de l'ancien texte.

Son temps croît avec la longueur des textes multipliée par le nombre de modifications, et sa mémoire avec le carré du
nombre de modifications. Le `$maxEdits` du constructeur borne ce nombre : quand les milieux des deux textes exigent
plus de modifications, l'ancien milieu est donné comme supprimé et le nouveau comme inséré, en entier. Un texte réécrit
d'un bout à l'autre se lit de toute façon ainsi. La valeur par défaut, mille, maintient une comparaison sous quelques
mégaoctets ; zéro met quand même de côté le début et la fin communs. Une borne négative lève `InvalidConfiguration`.

Le script est le plus court en jetons, pas en caractères : dans une comparaison par mots, un mot modifié d'une seule
lettre est un mot remplacé.
