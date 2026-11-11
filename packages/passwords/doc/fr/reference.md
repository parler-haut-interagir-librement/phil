<!-- phil-dev source-sha256=01061fb994c08e947a6859dd8310076ebfe7c0b8c8db898846b897c511adb064 -->
# Référence

## Robustesse

| Type | Rôle |
|---|---|
| `Strength\StrengthEstimator` | Estime les essais d'un mot de passe et retourne une `Strength`. |
| `Strength\Strength` | Essais, note, motifs, temps de cassage, rétroaction, longueurs. |
| `Strength\Score` | De 0 à 4, selon l'ordre de grandeur des essais. |
| `Strength\PatternMatch` | Une partie du mot de passe qui suit un motif, avec ses essais et les champs de son motif. |
| `Strength\Pattern` | Dictionnaire, spatial, suite, répétition, date, année, force brute. |
| `Strength\CrackTimes` | Les secondes nécessaires pour faire les essais dans quatre situations. |
| `Strength\Feedback` | Un avertissement et des suggestions. |
| `Strength\Warning`, `Strength\Suggestion` | Ce qui rend un mot de passe faible et ce qui aiderait, avec leurs clés de traduction. |
| `Strength\FeedbackMessages` | Les phrases de la rétroaction dans une locale, sans traducteur. |
| `Strength\Dictionary` | Des entrées classées, cherchées dans les mots de passe ; celles qui sont fournies et les données de l'utilisateur. |
| `Strength\KeyboardLayout` | Des touches et leurs voisines ; les dispositions fournies. |

## Fuites

| Type | Rôle |
|---|---|
| `Breach\BreachChecker` | Vérifie un mot de passe contre une source, sans lancer d'exception. |
| `Breach\BreachCheck`, `Breach\BreachStatus` | Le résultat d'une vérification. |
| `Breach\Contract\BreachedPasswordSource` | Le port des sources de fuites. |
| `Breach\Adapter\PwnedPasswordsSource` | L'API par plages de Pwned Passwords, par k-anonymat. |
| `Breach\InMemoryBreachSource` | Des empreintes gardées en mémoire. |
| `Breach\RangeResponse` | Lit une réponse de l'API par plages. |

## Génération

| Type | Rôle |
|---|---|
| `Generator\PasswordGenerator`, `Generator\PasswordPolicy`, `Generator\CharacterSet` | Mots de passe aléatoires. |
| `Generator\PassphraseGenerator`, `Generator\PassphrasePolicy`, `Generator\Capitalization` | Phrases de passe. |
| `Generator\Wordlist` | Les mots des phrases de passe. |

## Validation

| Type | Rôle |
|---|---|
| `Validator\MinimumStrength`, `Validator\MinimumStrengthValidator` | Un mot de passe qui atteint au moins une note minimale. |
| `Validator\NotCompromised`, `Validator\NotCompromisedValidator` | Un mot de passe absent des fuites connues. |
| `Contract\PasswordUserInputs` | Ce qu'un objet validé sait de la personne. |

## Exceptions

`Exception\PasswordsException`, `InvalidPolicy`, `InvalidWordlist`, `InvalidDictionary`, `BreachCheckUnavailable` :
voyez [Exceptions](exceptions.md).

Les classes sous `Strength\Internal` sont internes : leurs noms et leurs signatures peuvent changer dans n'importe
quelle version.
