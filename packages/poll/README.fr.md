<!-- phil-dev source-sha256=02b80023ebca706109a2cd34a79275df1a11386f9718f408a6c0a939f79a5422 -->
# phil/poll

**Un moteur de vote : des bulletins vérifiés selon les règles de leur scrutin, puis dépouillés en classement.**

phil/poll est le noyau par lequel passe chaque vote d'une application : une question à une seule réponse, un choix de
plusieurs options, un classement de préférences. Les règles d'un scrutin disent ce qu'un bulletin peut contenir — une
option, entre un minimum et un maximum d'options, ou des options classées — et si un bulletin blanc est une
abstention. Un bulletin est vérifié selon ces règles tel qu'un formulaire ou une requête le donne. Une méthode de
dépouillement transforme les bulletins en classement : le compte des choix pour les bulletins uniques et multiples, le
vote alternatif, la méthode Borda et la méthode Schulze pour les bulletins classés. Les options à égalité partagent
leur place, un vote alternatif garde ses tours, et les abstentions sont comptées à part.

Elle s'adresse à toute application PHP où les gens votent : un club qui choisit la date de sa prochaine réunion, une
communauté qui classe les idées de ses membres, une association qui élit son conseil d'administration, un site qui pose
une question à ses lecteurs. Qui peut voter, si les bulletins sont anonymes et où ils sont gardés relèvent de
l'application, ou de `phil/poll-bundle` dans Symfony.

C'est une simple bibliothèque PHP qui n'exige que PHP. Un dépouillement lit les bulletins une seule
fois, à partir de n'importe quel itérable : un stockage peut donc les lire en flux depuis sa table. Les méthodes à
bulletins classés regroupent les classements identiques : leur mémoire croît avec les classements distincts plutôt
qu'avec les bulletins. Les méthodes ne gardent aucun état entre deux dépouillements et peuvent être partagées entre les
requêtes d'un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Les règles d'un scrutin : bulletins uniques, multiples et classés, bornes, abstention, de 2 à 100 options | Implémenté et testé |
| Bulletins vérifiés selon les règles à partir des valeurs d'un formulaire ou d'une requête | Implémenté et testé |
| Le compte des choix : majorité simple et vote par approbation | Implémenté et testé |
| Le vote alternatif, ses tours, ses bulletins épuisés et ses bris d'égalité | Implémenté et testé |
| La méthode Borda et la méthode Schulze | Implémenté et testé |
| `CountingMethods`, les méthodes d'une application par nom, et le port d'une méthode propre à l'application | Implémenté et testé |
| Scrutins, bulletins et leur anonymat dans Symfony et Doctrine | Implémenté dans `phil/poll-bundle` |
| Le vote unique transférable d'une élection à plusieurs sièges, les bulletins par notes et par jugement majoritaire | Non commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, un premier dépouillement. |
| [Règles et bulletins](doc/fr/rules-and-ballots.md) | Les sortes de bulletins, les options, les bornes, l'abstention, les vérifications. |
| [Dépouillement](doc/fr/counting.md) | Les quatre méthodes, leurs scores, places, tours et bris d'égalité, et leur coût. |
| [Points d'extension](doc/fr/extension-points.md) | Une méthode de dépouillement propre à l'application. |
| [Exceptions](doc/fr/exceptions.md) | `PollException` et ses trois exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation et son banc d'essai, et comment tester un dépouillement. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/poll
```

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;

$rules = PollRules::ranked(['tea', 'coffee', 'cocoa'], abstention: true);

$ballots = [
    $rules->ballot(['coffee', 'tea']),
    $rules->ballot(['tea']),
    $rules->ballot(['tea', 'coffee']),
    $rules->ballot(['cocoa', 'tea']),
    $rules->ballot([]),               // an abstention
];

$result = CountingMethods::defaults()->count('instant_runoff', $rules, $ballots);

$result->winners();                   // ['tea']
$result->standings[0]->place;         // 1
\count($result->rounds);              // 2
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
