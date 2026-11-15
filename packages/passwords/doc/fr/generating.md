<!-- phil-dev source-sha256=e646f36d42e62eac9e8bb51e0a023fb8a2af1db8c56d6a119609a6aa4952aab9 -->
# Générer des mots de passe

Les deux générateurs tirent de `Random\Randomizer` sur le moteur `Secure` — la source cryptographiquement sûre du
système, comme `random_int()` —, sauf si un test leur en donne un avec germe.

## Mots de passe aléatoires

```php
$generator = new PasswordGenerator();
$generator->generate();                                                        // the default policy
$generator->generate(new PasswordPolicy(32, [CharacterSet::Lowercase, CharacterSet::Digits]));
$generator->generate(new PasswordPolicy()->withLength(16)->withoutAmbiguous());
```

| Membre de `PasswordPolicy` | Valeur par défaut | Effet |
|---|---|---|
| `length` | 20 | De 4 à 512 caractères. |
| `sets` | les quatre `CharacterSet` | Minuscules, majuscules, chiffres, symboles ; un ensemble répété compte une fois. |
| `excludeAmbiguous` | `false` | Omet `I`, `l`, `1`, `\|`, `O`, `0` et `o`. |
| `requireEachSet` | `true` | Chaque mot de passe contient au moins un caractère de chaque ensemble. |

Les symboles sont `!#$%&()*+,-./:;<=>?@[]^_{|}~` : ni espace, ni guillemet, ni barre oblique inverse, que d'autres
systèmes traitent souvent mal. Chaque caractère est tiré uniformément de l'ensemble des jeux réunis. Quand chaque
ensemble est exigé, un tirage qui en manque un est refait en entier : chaque mot de passe de la politique reste ainsi
également probable.

`entropyBits()` est le logarithme en base 2 du nombre de mots de passe que la politique permet, sans compter ceux qui
manquent un ensemble exigé : environ 130 bits pour la politique par défaut.

## Phrases de passe

```php
$generator = new PassphraseGenerator(Wordlist::french());
$generator->generate();                                                        // seven words joined by dashes
$generator->generate(new PassphrasePolicy(5, ' ', Capitalization::Words, includeDigit: true));
$generator->entropyBits(new PassphrasePolicy());
```

| Membre de `PassphrasePolicy` | Valeur par défaut | Effet |
|---|---|---|
| `words` | 7 | De 3 à 64 mots, chacun tiré uniformément et indépendamment. |
| `separator` | `-` | Jusqu'à 8 octets et aucune lettre, pour qu'il ne se confonde jamais avec les mots ; peut être vide quand les mots se relisent encore ([plus bas](#phrases-de-passe-qui-se-relisent)). |
| `capitalization` | `Capitalization::None` | `First` met une majuscule au premier mot, `Words` à chaque mot, `Random` à chaque mot au hasard. |
| `includeDigit` | `false` | Ajoute un chiffre aléatoire à un mot aléatoire. |

`entropyBits()` compte les mots pour un attaquant qui connaît la liste et la politique : `words × log2(list size)`, et
`log2(10 × words)` avec le chiffre. Sept mots des listes fournies donnent de 66 à 70 bits. Les deux méthodes
refusent, avec `InvalidPolicy`, une politique dont les phrases de passe ne se relisent pas ; `accepts($policy)` le dit
d'avance.

Les majuscules sont comptées sur les formes qu'elles écrivent, en entropie de Shannon sur les tirages équiprobables d'un
mot. Sur `n` tirages, une forme écrite par `c` d'entre eux donne une entropie de `log2(n) − Σ c·log2(c) / n` :

| Majuscules | Tirages d'un mot | Chaque forme |
|---|---|---|
| `None` | Les `n` mots | Écrite une fois |
| `First` (premier mot), `Words` | Les `n` mots, avec une majuscule | Écrite par chaque mot qui donne cette forme avec une majuscule |
| `Random` | Les `2n` mots, tels quels ou avec une majuscule | Écrite par chaque tirage qui la donne |

Une liste dont chaque mot donne avec une majuscule une forme qui n'appartient qu'à lui, comme les listes fournies,
garde `log2(list size)` avec `First` et `Words`, et gagne exactement un bit par mot avec `Random`. Un mot sans casse
(chiffres, symboles, écriture sans casse), un mot qui a déjà sa majuscule, ou deux mots qui ne diffèrent que par la casse
de leur première lettre donnent moins. `Wordlist::bitsPerCapitalisedWord()` et `bitsPerRandomlyCapitalisedWord()`
portent le résultat, que `Wordlist::of()` calcule une fois, à la lecture de la liste.

### Phrases de passe qui se relisent

L'entropie compte les tirages — chaque mot tel qu'il est écrit et, avec le chiffre, le mot qui le prend et le chiffre.
Elle ne tient que si deux tirages différents n'écrivent jamais la même phrase de passe : sinon, un attaquant a moins de
phrases de passe à essayer que ne le disent les tirages. Le générateur n'accepte donc une politique que si chaque phrase
de passe qu'elle écrit redonne ses tirages, et refuse les autres plutôt que de les compter plus bas, puisqu'aucune borne
inférieure au compte ne se lit dans la liste seule.

| Politique | Ce qui pourrait écrire la même phrase de passe | Acceptée |
|---|---|---|
| Un séparateur qu'aucun mot ne contient | Rien : le séparateur ferme chaque mot. | Toujours. |
| Un séparateur qu'un mot contient, comme `-` avec `well-known` | `well` + `-` + `known` et `well-known`. | Quand les mots se relisent encore. |
| Aucun séparateur | Les mots composés : `week` + `end` et `weekend`, les trois dans la liste anglaise. | Quand les mots se relisent encore : avec les listes fournies, seulement avec `Capitalization::Words`, dont les majuscules commencent chaque mot (`WeekEnd`, `Weekend`). |
| Le chiffre, sur une liste qui contient `ab` et `ab1` | `ab` + `1` et `ab1` : la phrase de passe ne dit pas quel mot a pris le chiffre. | Jamais : `InvalidPolicy` nomme le chiffre. |
| Le chiffre, sur un mot qui finit par un chiffre à lui, comme `r2` sans `r` | Rien : le chiffre ajouté à `r2` écrit `r27`, relu comme `r2` et `7`. | Quand les mots se relisent encore. |

« Les mots se relisent encore » est le test de Sardinas–Patterson, exécuté sur les formes que la politique écrit — les
mots tels quels, avec une majuscule, ou les deux pour `First` et `Random` —, chacune suivie du séparateur et, avec le
chiffre, d'un chiffre et du séparateur. Il suit les suffixes qui restent quand une forme est coupée au début d'une autre ;
chacun est un suffixe d'une forme et n'est suivi qu'une fois, si bien que le travail est borné par la longueur de la
liste. Un séparateur qu'aucune forme ne contient n'a pas besoin du test, ni un chiffre que ni les formes ni le séparateur
ne contiennent, puisqu'il marque la fin de son mot.

| Liste fournie, sept mots | `-` (par défaut) | `''`, `None`, `First` ou `Random` | `''`, `Words` |
|---|---|---|---|
| Anglais | 70,4 bits | Refusée (`week` + `end`, `butter` + `fly`…) | 70,4 bits |
| Français | 68,3 bits | Refusée | 68,3 bits |
| Espagnol | 66,5 bits | Refusée | 66,5 bits |
| Allemand | 66,0 bits | Refusée | 66,0 bits |

Le chiffre ajoute `log2(70)`, soit 6,1 bits, partout où il est accepté.

## Listes de mots

| Liste de mots | Mots |
|---|---|
| `Wordlist::english()` (`en`) | 1 068 |
| `Wordlist::french()` (`fr`) | 866 |
| `Wordlist::spanish()` (`es`) | 723 |
| `Wordlist::german()` (`de`) | 689 |

Les mots fournis sont des mots courants de trois à dix lettres ASCII minuscules, sans accents, pour qu'une phrase de
passe se saisisse sur n'importe quel clavier. `Wordlist::of($name, $words)` prend celle de l'application : au moins
256 mots, chacun de 1 à 32 caractères, sans espace, aucun répété.
