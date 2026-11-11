# Validator

With `symfony/validator` installed, two constraints check a password in a form or an object. Both accept a string or a
`Stringable`; `null` and the empty string are valid, as for every constraint but `NotBlank`. Neither puts the password
in its violation: the invalid value is the empty string.

## `MinimumStrength`

```php
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Validator\MinimumStrength;

#[MinimumStrength(Score::Strong)]
public string $plainPassword;
```

| Option | Default | Effect |
|---|---|---|
| `minScore` | `Score::Strong` | The lowest score accepted. |
| `message` | `This password is too easy to guess.` | The violation. |

The violation, coded `MinimumStrength::TOO_WEAK_ERROR`, has the parameters `{{ score }}`, `{{ min_score }}`,
`{{ warning }}` and `{{ suggestion }}`: the last two are translation keys of the `phil_passwords` domain, or empty, for
the form to show the feedback.

When the object validated implements `Contract\PasswordUserInputs`, its `passwordUserInputs()` — name, email address,
username — are given to the estimator.

## `NotCompromised`

```php
use Phil\Passwords\Validator\NotCompromised;

#[NotCompromised(threshold: 1, skipOnError: false)]
public string $plainPassword;
```

| Option | Default | Effect |
|---|---|---|
| `threshold` | 1 | The occurrences from which a password is refused. |
| `skipOnError` | `false` | Accepts the password when the source cannot answer, instead of refusing it. |
| `message` | `This password appears in known data breaches: choose another one.` | The violation, coded `COMPROMISED_ERROR`, with `{{ occurrences }}`. |
| `unavailableMessage` | `This password could not be checked against known data breaches: try again later.` | The violation when the source cannot answer, coded `UNAVAILABLE_ERROR`. |

`NotCompromisedValidator` needs a `BreachChecker`: register it as a service, as [Installation](installation.md)
shows. The messages are translated in English and French in `translations/validators.<locale>.php`.
