<!-- phil-dev source-sha256=1b1f944b941b9e0506f2fcbcd6e2c9208e689a20baf825b9311fec09569f5c6d -->
# Suggestions

`TypoSuggester::suggest(EmailAddress|string $address): ?EmailAddress` donne l'adresse qu'une personne voulait sans
doute écrire quand son domaine est à une faute de frappe d'un domaine bien connu. Il ne corrige jamais rien : un
formulaire demande « Vouliez-vous dire `ada@gmail.com` ? », et la personne décide.

## Comment

Le domaine est mis en minuscules ; un domaine qui n'est pas en ASCII, ou qui est l'un des domaines connus, ne reçoit
aucune suggestion. Sinon :

1. il est comparé à chaque domaine connu par la distance d'alignement optimal de chaînes : une insertion, une
   suppression, une substitution ou l'échange de deux voisins coûtent chacun un ; `gmial.com` est à un de
   `gmail.com`, `gmailcom` aussi ;
2. un domaine connu de moins de huit caractères est sauté : `aol.com` est à une faute de `aon.com`, un vrai domaine ;
3. une faute est permise pour un domaine connu de huit ou neuf caractères, deux à partir de dix ;
4. le domaine connu le plus proche l'emporte, le premier de la liste en cas d'égalité ;
5. à défaut, un domaine de premier niveau qui est une faute courante de `com`, `net` ou `org` et qui n'est pas lui-même
   délégué (`con`, `cmo`, `nte`, `ogr`…) est remplacé : `ada@example.con` donne `ada@example.com`.

Un domaine connu dont la longueur diffère de plus que la limite, ou dont la distance de Levenshtein (qui compte un
échange pour deux) dépasse le double de la limite, est écarté avant la construction de toute table. Sinon, seules les
cellules à moins de la limite de la diagonale sont calculées, et la distance s'arrête dès qu'une ligne ne peut que
dépasser la limite : le travail est borné par les 253 octets d'un domaine et par la limite, quelques cellules par
caractère.

La suggestion est analysée avec la `SyntaxPolicy` du suggesteur : un texte dont la partie avant le @ n'est pas valide
n'en reçoit aucune.

## Ajuster

| Argument | Valeur par défaut |
|---|---|
| `$domains` | `TypoSuggester::DOMAINS` : les plus grands fournisseurs de boîtes de courriel, mondiaux, canadiens et français, les plus utilisés d'abord |
| `$slips` | `TypoSuggester::TOP_LEVEL_SLIPS` |
| `$policy` | `new SyntaxPolicy()` |

Une application dont les membres utilisent d'autres fournisseurs (une université, une région) passe sa propre liste,
les plus utilisés d'abord, en ASCII et en minuscules ; elle peut partir de `TypoSuggester::DOMAINS`.
