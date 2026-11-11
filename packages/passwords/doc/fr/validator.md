<!-- phil-dev source-sha256=b3669b1995a22187d4b6a7fcd0f09cc06a23c59aa8a1993c1009ffb949f3bd32 -->
# Validateur

Quand `symfony/validator` est installé, deux contraintes vérifient un mot de passe dans un formulaire ou un objet.
Les deux acceptent une chaîne ou un `Stringable` ; `null` et la chaîne vide sont valides, comme pour toute contrainte
sauf `NotBlank`. Ni l'une ni l'autre ne met le mot de passe dans sa violation : la valeur invalide est la chaîne vide.

## `MinimumStrength`

```php
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Validator\MinimumStrength;

#[MinimumStrength(Score::Strong)]
public string $plainPassword;
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `minScore` | `Score::Strong` | La plus basse note acceptée. |
| `message` | `This password is too easy to guess.` | La violation. |

La violation, de code `MinimumStrength::TOO_WEAK_ERROR`, a les paramètres `{{ score }}`, `{{ min_score }}`,
`{{ warning }}` et `{{ suggestion }}`. Les deux derniers sont des clés de traduction du domaine `phil_passwords`, ou
sont vides, pour que le formulaire affiche la rétroaction.

Quand l'objet validé implémente `Contract\PasswordUserInputs`, ses `passwordUserInputs()` — nom, adresse courriel, nom
d'utilisateur — sont données à l'estimateur.

## `NotCompromised`

```php
use Phil\Passwords\Validator\NotCompromised;

#[NotCompromised(threshold: 1, skipOnError: false)]
public string $plainPassword;
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `threshold` | 1 | Le nombre d'occurrences à partir duquel un mot de passe est refusé. |
| `skipOnError` | `false` | Accepte le mot de passe quand la source ne peut pas répondre, au lieu de le refuser. |
| `message` | `This password appears in known data breaches: choose another one.` | La violation, de code `COMPROMISED_ERROR`, avec `{{ occurrences }}`. |
| `unavailableMessage` | `This password could not be checked against known data breaches: try again later.` | La violation quand la source ne peut pas répondre, de code `UNAVAILABLE_ERROR`. |

`NotCompromisedValidator` a besoin d'un `BreachChecker` : enregistrez-le comme service, comme le montre
[Installation](installation.md). Les messages sont traduits en anglais et en français dans
`translations/validators.<locale>.php`.
