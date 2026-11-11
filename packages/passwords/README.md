# phil/passwords

**Tell people how guessable their password is, refuse the ones already leaked, and give them good ones.**

phil/passwords estimates how many guesses an attacker who knows how people choose passwords needs to find one. It
finds the patterns people use — common passwords, words, names, keyboard walks such as `qwerty` or `azerty`,
sequences, repeats, dates, years and substitutions such as `p@ssw0rd` —, takes the cheapest way to cover the password
with them, and scores its guesses from 0 to 4, with a warning and suggestions in English and French. It checks a
password against the Pwned Passwords corpus by k-anonymity: only the first five characters of its SHA-1 hash leave the
application, through a port an application can swap for a mirror, a list in memory or its own. It generates random
passwords and passphrases from the cryptographically secure source of the system. With `symfony/validator` installed,
two constraints refuse weak and leaked passwords in a form.

It is for any PHP application that lets people choose a password: a sign-up form and its strength meter, a change of
password, an identity provider, an administration that creates accounts.

It is a plain PHP library with its own dictionaries, keyboard layouts and wordlists. The
estimator takes zxcvbn's published approach as its model — patterns, guesses and the search for the cheapest cover —,
and none of its code nor its data. The HTTP source uses `symfony/http-client-contracts`, the constraints
`symfony/validator`; the rest needs neither.

The design is small: `Strength\StrengthEstimator` returns a `Strength` — guesses, `Score`, the patterns found, crack
times and `Feedback` —; `Breach\BreachChecker` asks a `BreachedPasswordSource` and returns a `BreachCheck`;
`Generator\PasswordGenerator` and `Generator\PassphraseGenerator` follow a policy.

## Status

| Part | State |
|---|---|
| Dictionary words, reversed and with substitutions; common passwords, names and words in English, French, Spanish and German | Implemented and tested |
| Keyboard walks on QWERTY, AZERTY, QWERTZ and the keypad, with turns and shifted keys | Implemented and tested |
| Sequences, repeats, dates with and without separators, years | Implemented and tested |
| The cheapest cover, a score from 0 to 4, crack times, feedback in English and French | Implemented and tested |
| What the application knows of the person taken into account | Implemented and tested |
| Breach checks by k-anonymity through a port; Pwned Passwords range API with padding; a list in memory | Implemented and tested |
| Random passwords by policy, with their exact entropy | Implemented and tested |
| Passphrases from wordlists in four languages or the application's own | Implemented and tested |
| Symfony validator constraints `MinimumStrength` and `NotCompromised` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the services of a Symfony application. |
| [Estimating strength](doc/strength.md) | The patterns, their guesses, the cover, the score, the feedback and its translations, the bounds. |
| [Breach checks](doc/breaches.md) | k-anonymity, the Pwned Passwords source, failures, circuit breakers, other sources. |
| [Generating passwords](doc/generating.md) | Random passwords, passphrases, policies, wordlists and entropy. |
| [Validator](doc/validator.md) | The `MinimumStrength` and `NotCompromised` constraints. |
| [Data](doc/data.md) | The dictionaries, keyboard layouts and wordlists the library ships. |
| [Extension points](doc/extension-points.md) | Dictionaries, layouts, breach sources, wordlists, randomizers, translations. |
| [Exceptions](doc/exceptions.md) | `PasswordsException` and the exceptions that implement it. |
| [Testing](doc/testing.md) | The library's tests, mutation bar, fuzzing and benchmark, and how to test code that uses it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/passwords
```

```php
use Phil\Passwords\Strength\FeedbackMessages;
use Phil\Passwords\Strength\StrengthEstimator;

$estimator = new StrengthEstimator();
$strength = $estimator->estimate('P@ssw0rd1987', ['jean.dupont@example.com']);

$strength->score;            // Score::Weak: a common password with substitutions, then a year
$strength->guessesLog10();   // about 4
foreach (FeedbackMessages::forLocale('fr_CA')->describe($strength->feedback) as $sentence) {
    echo $sentence, "\n";
}
// Il ressemble à un mot de passe courant.
// Ajoutez un mot ou deux, de préférence peu courants.
// …
```

```php
use Phil\Passwords\Breach\Adapter\PwnedPasswordsSource;
use Phil\Passwords\Breach\BreachChecker;
use Symfony\Component\HttpClient\HttpClient;

$check = new BreachChecker(new PwnedPasswordsSource(HttpClient::create()))->check('P@ssw0rd');
$check->isCompromised();     // true
```

```php
use Phil\Passwords\Generator\PassphraseGenerator;
use Phil\Passwords\Generator\PasswordGenerator;
use Phil\Passwords\Generator\Wordlist;

new PasswordGenerator()->generate();                         // 20 characters of four sets
new PassphraseGenerator(Wordlist::french())->generate();     // seven words joined by dashes
```

## Requirements

PHP 8.5 or later, with the `ctype` and `mbstring` extensions; `symfony/http-client-contracts` and an HTTP client for
`PwnedPasswordsSource`; `symfony/validator` for the constraints.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
