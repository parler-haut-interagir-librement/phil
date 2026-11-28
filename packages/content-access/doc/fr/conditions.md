<!-- phil-dev source-sha256=cc83ab8f77b6e3299cb86947be4b9444848054687670f6fc6210f2686b3cd3fd -->
# Conditions

La règle d'un contenu restreint est une règle de `phil/rules`, lue sur un registre qui contient les types de conditions
d'accès :

```php
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\Rules\Json\RuleReader;

$reader = new RuleReader(AccessConditions::types());          // beside the built-in comparisons
$reader = new RuleReader(AccessConditions::types($registry)); // beside a registry the application built
```

## Les faits

Le décideur construit les faits de chaque décision avec `AccessFacts::for()`. La bibliothèque fixe ces noms ; une
application qui passe un fait sous l'un d'eux reçoit `ReservedFact`, pour que personne ne se substitue au lecteur ni à
l'horloge.

| Fait | Contient |
|---|---|
| `viewer.id` | l'identifiant stable du lecteur, ou `null` |
| `viewer.roles` | les rôles du lecteur, une liste d'identifiants |
| `viewer.email_domain` | le domaine de l'adresse courriel vérifiée du lecteur, ou `null` |
| `viewer.ip` | l'adresse réseau de la requête, ou `null` |
| `viewer.grants` | les droits du lecteur, lus dans `Entitlements` seulement quand une condition les demande |
| `now` | l'instant de la décision |

L'application ajoute ses propres faits en troisième argument de `decide()` : une leçon terminée, un pointage, un champ
du profil, donnés comme valeurs ou comme fermetures calculées à la première lecture d'une condition. Les comparaisons
génériques de `phil/rules` les lisent : `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`, `fact.present` et
`date.compare`.

## `access.member`

Le lecteur est connecté. Aucun opérande.

| Raison | Quand |
|---|---|
| `access.member` | le lecteur est connecté |
| `access.anonymous` | le lecteur est un visiteur |

## `access.role`

Le lecteur détient au moins un des rôles, comparés exactement.

| Opérande | Genre | |
|---|---|---|
| `roles` | liste d'identifiants | requis |

| Raison | Détails |
|---|---|
| `access.role-held` | `role` : le premier rôle de l'opérande que le lecteur détient |
| `access.role-missing` | — |

## `access.feature`

Le lecteur détient un droit actif de la fonctionnalité : de son début, inclus, à sa fin, exclue.

| Opérande | Genre | |
|---|---|---|
| `feature` | identifiant, comme `tier:gold` | requis |
| `after_days` | entier, de 0 à 36 500 | facultatif : le contenu s'ouvre ce nombre de jours après le début du droit |
| `for_days` | entier, de 1 à 36 500 | facultatif : le contenu se ferme ce nombre de jours après le début du droit |

Les jours sont des jours civils dans le fuseau horaire du début du droit. `after_days` ouvre un contenu selon
l'ancienneté — la troisième leçon une semaine après l'inscription — ; `for_days` donne une durée d'accès glissante —
un an à partir de l'achat. Une fin d'accès fixe est un `date.compare` sur le fait `now` :

```json
{"all": [
    {"type": "access.feature", "operands": {"feature": "course:intro", "for_days": 365}},
    {"type": "date.compare", "operands": {"fact": "now", "operator": "before", "date": "2027-06-30"}}
]}
```

| Raison | Détails |
|---|---|
| `access.feature-held` | `feature` |
| `access.feature-not-yet` | `feature`, `opens_at` : le plus tôt qu'un droit actif l'ouvre, au format ATOM |
| `access.feature-elapsed` | `feature` : un droit actif le détenait au-delà de `for_days` |
| `access.feature-missing` | `feature` : aucun droit actif de la fonctionnalité |
| `fact.type` | `fact`, `expected` : l'instant ou les droits ne sont pas du bon type |

Quand plusieurs droits de la fonctionnalité sont actifs, un seul qui ouvre le contenu suffit ; sinon, le contenu pas
encore ouvert est signalé avant celui dont la durée est dépassée.

## `access.institution`

Le lecteur appartient à une institution, par le domaine vérifié de son adresse courriel — le domaine lui-même ou l'un
de ses sous-domaines — ou par l'adresse réseau de la requête à l'intérieur d'une plage déclarée.

| Opérande | Genre | |
|---|---|---|
| `domains` | liste de noms d'hôtes, 100 au plus, comme `library.example` | facultatif |
| `networks` | liste d'adresses IPv4 ou IPv6 avec un préfixe facultatif, 100 au plus, comme `192.0.2.0/24` | facultatif |

L'application ne passe le domaine du courriel à `Viewer::member()` qu'après avoir vérifié l'adresse. Un domaine est
comparé en minuscules, sans `@` initial ni point final, ses étiquettes internationalisées sous leur forme `xn--` ;
`evillibrary.example` ne correspond pas à `library.example`.

| Raison | Détails |
|---|---|
| `access.institution-domain` | — |
| `access.institution-network` | — |
| `access.institution-none` | — |
| `access.institution-empty` | — : ni domaines ni réseaux ne sont donnés |
| `access.institution-invalid` | `operand`, `index` : un domaine ou une plage déclarés sont mal formés, et la condition échoue |

Le verdict dit comment le lecteur correspond, jamais le domaine ni l'adresse, pour qu'une trace puisse être journalisée.
