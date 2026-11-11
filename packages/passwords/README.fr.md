<!-- phil-dev source-sha256=ca1638c90eebfc5135c99a3940bab4ccde4e749d80a7de30c39d127a2682d755 -->
# phil/passwords

**Dites aux gens à quel point leur mot de passe se devine, refusez ceux qui ont déjà fui et donnez-leur-en de bons.**

phil/passwords estime combien d'essais il faut pour trouver un mot de passe à un attaquant qui sait comment les gens
les choisissent. La bibliothèque trouve les motifs que les gens utilisent — mots de passe courants, mots, noms,
parcours de clavier comme `qwerty` ou `azerty`, suites, répétitions, dates, années et substitutions comme
`p@ssw0rd` —, prend la façon la moins coûteuse de couvrir le mot de passe avec eux et note ses essais de 0 à 4, avec
un avertissement et des suggestions en anglais et en français. Elle vérifie un mot de passe contre le corpus Pwned
Passwords par k-anonymat : seuls les cinq premiers caractères de son empreinte SHA-1 quittent l'application, par un
port qu'une application peut remplacer par un miroir, une liste en mémoire ou le sien. Elle génère des mots de passe
et des phrases de passe aléatoires à partir de la source cryptographiquement sûre du système. Quand
`symfony/validator` est installé, deux contraintes refusent les mots de passe faibles et ceux qui ont fui dans un
formulaire.

Elle s'adresse à toute application PHP qui laisse les gens choisir un mot de passe : un formulaire d'inscription et
son indicateur de robustesse, un changement de mot de passe, un fournisseur d'identité, une administration qui crée des
comptes.

C'est une simple bibliothèque PHP, écrite de zéro, avec ses propres dictionnaires, dispositions de clavier et listes
de mots. L'estimateur prend pour modèle l'approche publiée de zxcvbn — motifs, essais et recherche de la couverture la
moins coûteuse —, sans rien de son code ni de ses données. La source HTTP utilise `symfony/http-client-contracts`, les
contraintes `symfony/validator` ; le reste n'a besoin ni de l'un ni de l'autre.

La conception est sobre : `Strength\StrengthEstimator` retourne une `Strength` — essais, `Score`, motifs trouvés,
temps de cassage et `Feedback` — ; `Breach\BreachChecker` interroge une `BreachedPasswordSource` et retourne un
`BreachCheck` ; `Generator\PasswordGenerator` et `Generator\PassphraseGenerator` suivent une politique.

## État

| Partie | État |
|---|---|
| Mots du dictionnaire, à l'envers et avec substitutions ; mots de passe, noms et mots courants en anglais, en français, en espagnol et en allemand | Implémenté et testé |
| Parcours de clavier sur QWERTY, AZERTY, QWERTZ et le pavé numérique, avec changements de direction et touches majuscules | Implémenté et testé |
| Suites, répétitions, dates avec et sans séparateurs, années | Implémenté et testé |
| La couverture la moins coûteuse, une note de 0 à 4, des temps de cassage, une rétroaction en anglais et en français | Implémenté et testé |
| Ce que l'application sait de la personne, pris en compte | Implémenté et testé |
| Vérification des fuites par k-anonymat par un port ; API par plages de Pwned Passwords avec remplissage ; une liste en mémoire | Implémenté et testé |
| Mots de passe aléatoires selon une politique, avec leur entropie exacte | Implémenté et testé |
| Phrases de passe à partir de listes de mots en quatre langues ou de celle de l'application | Implémenté et testé |
| Contraintes de validateur Symfony `MinimumStrength` et `NotCompromised` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les services d'une application Symfony. |
| [Estimer la robustesse](doc/fr/strength.md) | Les motifs, leurs essais, la couverture, la note, la rétroaction et ses traductions, les bornes. |
| [Vérifier les fuites](doc/fr/breaches.md) | Le k-anonymat, la source Pwned Passwords, les échecs, les disjoncteurs, les autres sources. |
| [Générer des mots de passe](doc/fr/generating.md) | Mots de passe aléatoires, phrases de passe, politiques, listes de mots et entropie. |
| [Validateur](doc/fr/validator.md) | Les contraintes `MinimumStrength` et `NotCompromised`. |
| [Données](doc/fr/data.md) | Les dictionnaires, dispositions de clavier et listes de mots fournis avec la bibliothèque. |
| [Points d'extension](doc/fr/extension-points.md) | Dictionnaires, dispositions, sources de fuites, listes de mots, générateurs aléatoires, traductions. |
| [Exceptions](doc/fr/exceptions.md) | `PasswordsException` et les exceptions qui l'implémentent. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation, son fuzzing et son banc d'essai, et comment tester du code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

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

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `ctype` et `mbstring` ; `symfony/http-client-contracts` et un client HTTP
pour `PwnedPasswordsSource` ; `symfony/validator` pour les contraintes.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
