<!-- phil-dev source-sha256=7ab8f5de3b93649cc19159cac1d7c888293274b344061fdaf33030768b916b64 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/passwords` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-24

Documentation et développement seulement.

### Ajouté

- La [correspondance ASVS 5.0](doc/fr/asvs.md).
- Des tests d'un mot de passe de 200 caractères, et de mots de passe hachés et vérifiés exactement tels que tapés :
  espaces autour, capitales, accents composés et décomposés, UTF-8 invalide.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`symfony/http-client-contracts`
  3.8).

## 0.2.0-alpha - 2026-11-15

### Ajouté

- `PassphraseGenerator::accepts()` : si chaque phrase de passe d'une politique redonne ses tirages sur la liste du
  générateur.

### Modifié

- `PassphraseGenerator::generate()` et `entropyBits()` lancent `InvalidPolicy` pour une politique dont les phrases de
  passe ne se relisent pas, au lieu de compter comme différents des tirages qui écrivent la même phrase de passe : des
  mots qui se fondent avec le séparateur (avec un séparateur vide, les mots composés des listes fournies, comme `week` +
  `end` et `weekend` ; ils se relisent encore avec `Capitalization::Words`), vérifiés par le test de Sardinas–Patterson
  sur les formes que la politique écrit, et un chiffre ajouté à un mot qui écrit un autre mot de la liste (`ab` + `1` et
  `ab1`). Un séparateur vide avec `Capitalization::None`, `First` ou `Random` sur une liste fournie est refusé.
- `MinimumStrength` : les paramètres `{{ score }}` et `{{ min_score }}` de sa violation sont des entiers.
- `NotCompromised` : le paramètre `{{ occurrences }}` de sa violation est un entier.

## 0.1.1-alpha - 2026-11-14

### Ajouté

- `Wordlist::bitsPerCapitalisedWord()`, `Wordlist::bitsPerRandomlyCapitalisedWord()` et `Wordlist::capitalise()`.

### Corrigé

- `PassphraseGenerator::entropyBits()` compte les capitales sur les formes qu'il écrit : un mot sans casse, un mot déjà
  en capitale, ou deux mots d'une liste propre qui ne diffèrent que par la casse de leur première lettre n'ajoutent plus
  un bit chacun avec `Random`, et les formes que partagent deux mots ne sont plus comptées deux fois avec `First` et
  `Words`. `Wordlist::of()` calcule l'entropie une fois, comme l'entropie de Shannon sur les tirages équiprobables d'un
  mot ; les listes fournies gardent leurs valeurs.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Strength\StrengthEstimator` : `estimate()` trouve les motifs d'un mot de passe — mots du dictionnaire tels qu'écrits,
  à l'envers et avec des substitutions, parcours de clavier, suites, répétitions, dates et années —, prend la couverture
  la moins coûteuse du mot de passe par ceux-ci, et retourne une `Strength` : les essais, un `Score` de 0 à 4, les
  `PatternMatch` de la couverture, des `CrackTimes` dans quatre situations et un `Feedback`. Les 128 premiers caractères
  sont analysés ; l'UTF-8 invalide est lu avec ses octets remplacés ; ce que l'application sait de la personne, jusqu'à
  32 entrées, devient un dictionnaire à part.
- La recherche de couverture : `l!` fois le produit des essais de `l` motifs plus une pénalité pour chaque motif ; 8
  motifs au plus et des suites de 20 caractères au plus devinées une à une après un motif, les deux bien au-dessus du
  meilleur score.
- `Strength\Dictionary` : des entrées classées, les mots de passe courants fournis, des prénoms, des noms de famille et
  des mots courants en anglais, en français, en espagnol et en allemand, écrits pour la bibliothèque ; `of()` pour ceux
  de l'application ; `ofUserInputs()`.
- `Strength\KeyboardLayout` : des claviers décalés en demi-touches et des pavés alignés, avec QWERTY, AZERTY, QWERTZ et
  le pavé numérique fournis ; `staggered()` et `aligned()` pour les autres.
- `Strength\Warning` et `Strength\Suggestion`, traduits en anglais et en français dans
  `translations/phil_passwords.*.php` ; `Strength\FeedbackMessages` les lit sans traducteur.
- `Breach\BreachChecker` : vérifie un mot de passe d'après une `Breach\Contract\BreachedPasswordSource` et retourne un
  `BreachCheck` — `Compromised`, `NotFound` ou `Unavailable` avec l'échec — sans lancer d'exception.
- `Breach\Adapter\PwnedPasswordsSource` : l'API de plages de Pwned Passwords par k-anonymat, par
  `symfony/http-client-contracts`, avec remplissage, un agent utilisateur, un délai d'attente et une borne sur la
  réponse ; chaque échec est une `BreachCheckUnavailable`. `Breach\RangeResponse` lit une plage ;
  `Breach\InMemoryBreachSource` garde des hachages en mémoire.
- `Generator\PasswordGenerator` et `Generator\PasswordPolicy` : des mots de passe aléatoires de 4 à 512 caractères tirés
  de jeux de caractères, sans les caractères ambigus sur demande, chaque jeu présent quand il est exigé, avec leur
  entropie exacte.
- `Generator\PassphraseGenerator`, `Generator\PassphrasePolicy` et `Generator\Wordlist` : des phrases de passe de 3 à 64
  mots avec un séparateur, des capitales et un chiffre, tirées des listes fournies en anglais, en français, en espagnol
  et en allemand ou de celle de l'application d'au moins 256 mots, avec leur entropie.
- `Validator\MinimumStrength` et `Validator\NotCompromised`, avec leurs validateurs et leurs messages en anglais et en
  français dans `translations/validators.*.php` ; `Contract\PasswordUserInputs` donne à l'estimateur ce que l'objet
  validé sait de la personne.
- `Exception\PasswordsException`, implémentée par `InvalidPolicy`, `InvalidWordlist`, `InvalidDictionary` et
  `BreachCheckUnavailable`.
