<!-- phil-dev source-sha256=9fb67572d7327eb8f64a696a316985c6c8dbedcb4fecdbe77fd01e7dba62f666 -->
# Initiales

`Initials::of($name, $max)` prend les initiales d'un nom ; le style des initiales l'appelle avec le nom et le
`maxLetters` des options.

## Les règles

1. Le nom est coupé en mots aux espaces, à la ponctuation et aux symboles, de toutes les écritures : un trait d'union,
   une apostrophe, un maqaf hébreu, une espace insécable. Les émojis et les autres symboles sont écartés.
2. Chaque mot donne son premier groupe de graphèmes qui commence par une lettre ou un chiffre : la lettre avec ses
   signes combinants, une syllabe hangul entière, une consonne devanagari avec son signe vocalique.
3. Cette lettre est mise en majuscule une pour une : `é` donne `É`, `ǆ` donne `Ǆ`, `ß` reste `ß` au lieu de devenir deux
   lettres.
4. Les premiers mots et le dernier donnent les initiales : deux lettres prennent le premier et le dernier mot, trois
   prennent les deux premiers et le dernier ; une lettre prend le premier mot.
5. Un nom dont la première lettre est han, hiragana, katakana ou hangul donne ce seul caractère : ces écritures notent
   un nom sans espaces, et un caractère remplit la place de deux lettres.
6. Les initiales en arabe, hébreu, syriaque, thaana, n'ko, samaritain, mandéen ou adlam vont de droite à gauche :
   `isRightToLeft()` l'indique, et le style les dispose ainsi.

| Nom | Deux lettres | Trois lettres |
|---|---|---|
| Ada Lovelace | AL | AL |
| Jean-Luc de la Tour | JT | JLT |
| Mary O'Brien | MB | MB |
| émile zola | ÉZ | ÉZ |
| 山田太郎 | 山 | 山 |
| 김민준 | 김 | 김 |
| محمد علي | مع, de droite à gauche | مع, de droite à gauche |
| 😀 🎉 | aucune | aucune |

## Les noms sans initiales

Un nom vide, fait seulement d'espaces, de ponctuation ou d'émojis, ou qui n'est pas de l'UTF-8 valide, ne donne aucune
initiale : `isEmpty()` est vrai, et le style des initiales dessine à la place le style des formes. Un nom de plus de
1 Kio est lu à partir de son premier kilo-octet, coupé à une frontière de caractère.

L'extraction ne lève jamais d'exception sur un nom, quels que soient ses octets ; une cible de fuzzing l'y tient, de
même qu'à des initiales de trois groupes de graphèmes au plus, chacun commençant par une lettre ou un chiffre.
