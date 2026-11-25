<!-- phil-dev source-sha256=fe0b1e31e8c99f1514f6be6a9a14db9631ece51dfedb0b6eacd06026f0482dab -->
# Analyse

`PhoneNumber::parse($input, $region = null)` lit un numéro comme les gens le saisissent et garde son indicatif de
pays, son numéro national et son poste. `$region` est le code ISO 3166-1 alpha-2 du pays dans lequel est écrit un
numéro sans indicatif de pays.

| Entrée | Région | Numéro |
|---|---|---|
| `+1 514-555-0123` | — | `+15145550123` |
| `(514) 555-0123` | `CA` | `+15145550123` |
| `1 800 555 0199` | `CA` | `+18005550199` : le préfixe national `1` est retiré |
| `06 12 34 56 78` | `FR` | `+33612345678` |
| `+33 (0)6 12 34 56 78` | — | `+33612345678` |
| `011 33 6 12 34 56 78` | `US` | `+33612345678` : composé depuis l'Amérique du Nord |
| `+39 06 6982 1234` | — | `+390669821234` : l'Italie garde son zéro initial |
| `011 15 2345-6789` | `AR` | `+5491123456789` : le préfixe de l'Argentine réécrit comme le dit son plan |
| `tel:+1-514-555-0123;ext=12` | — | `+15145550123`, poste `12` |

**Ce qui est toléré** : les espaces, les espaces insécables, les points, les tirets, les barres obliques et les
parenthèses ; un `+` seulement au début. **Ce qui est lu comme un poste**, après le numéro : `;ext=`, `ext`, `ext.`,
`extn`, `extension`, `x`, `#`, `poste`, `anexo`, `int`, suivis de 1 à 7 chiffres.

## Refus

`parse()` lance `InvalidPhoneNumber` pour :

- des lettres, ou un `+` ailleurs qu'au début : `1-800-FLOWERS` ;
- une entrée de plus de 100 caractères, `PhoneNumber::MAX_INPUT`, refusée avant qu'un motif ne s'y applique, pour que
  la lecture d'un champ coûte la même chose quoi qu'on envoie ;
- plus de 50 caractères une fois le poste mis de côté, ou un numéro national de moins de 2 ou de plus de 17 chiffres ;
- un numéro sans indicatif de pays et sans région ;
- un indicatif de pays qu'aucun plan ne connaît ;
- une région sans plan.

Le message nomme la raison, jamais l'entrée : un numéro mal saisi est le plus souvent le vrai numéro, et un message peut
aboutir dans un journal. `InvalidPhoneNumber::$input` garde l'entrée, coupée à 100 caractères, pour un appelant qui la
montre à la personne qui l'a saisie. Une région sans plan, qui n'est pas un renseignement personnel, est nommée dans le
message.

Un numéro que `parse()` lit n'existe pas toujours : `isValid()` le dit, comme l'explique
[Validation et types](validation.md).
