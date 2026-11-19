<!-- phil-dev source-sha256=a300556a27e9d5106ee916c714ea2f2d94c6a622c98df804ad04767a1a696abc -->
# Juridictions

## Les règles d'une juridiction

Une `Jurisdiction` contient ce dont la bibliothèque a besoin de la loi d'un lieu sur le consentement des mineurs :

| Règle | Bornes | Rôle |
|---|---|---|
| `code` | Un code ISO 3166 : un pays, `FR`, ou une subdivision, `CA-QC` | Nomme la juridiction dans l'enregistrement d'un mineur |
| `consentAge` | De 1 à l'âge de la majorité | L'âge à partir duquel une personne consent pour elle-même |
| `majorityAge` | Jusqu'à 21 | L'âge de la majorité, pour la tranche `Adult` |
| `timeZone` | Un `DateTimeZone` | Le fuseau dans lequel un anniversaire commence |
| `leapDayRule` | `FirstOfMarch` par défaut, ou `TwentyEighthOfFebruary` | Le jour où une personne née le 29 février a son anniversaire une année commune |

Un anniversaire commence à minuit dans le fuseau horaire de la juridiction : à 03 h 30 UTC le jour de l'anniversaire
d'un membre, un membre au Québec est encore à un jour de celui-ci, un membre en France l'a déjà.

## La juridiction par défaut

`Jurisdiction::quebec()` — code `CA-QC`, âge du consentement 14, majorité 18, fuseau `America/Toronto`, anniversaire du
29 février le 1er mars — est la juridiction que contient un `Jurisdictions` quand on ne lui en donne aucune. La
[Loi sur la protection des renseignements personnels dans le secteur privé](https://www.legisquebec.gouv.qc.ca/en/document/cs/P-39.1)
du Québec exige le consentement du titulaire de l'autorité parentale ou du tuteur pour un mineur de moins de 14 ans.

## Les autres juridictions

La bibliothèque ne fournit aucune autre table d'âges : ils changent avec la loi, et l'application qui sert un lieu lit
sa loi. Un `Jurisdictions` contient la juridiction par défaut et les autres juridictions servies :

```php
use Phil\GuardianConsent\Jurisdiction;
use Phil\GuardianConsent\Jurisdictions;

$jurisdictions = new Jurisdictions(
    Jurisdiction::quebec(),
    new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris')),
    new Jurisdiction('DE', 16, 18, new DateTimeZone('Europe/Berlin')),
);

$consent->register($member, $birthDate, 'FR');
```

Selon le [Règlement général sur la protection des données](https://eur-lex.europa.eu/eli/reg/2016/679/oj), l'article 8
fixe à 16 ans l'âge à partir duquel un enfant consent à un service de la société de l'information, et laisse chaque
État membre en fixer un plus bas, sans descendre sous 13 ans : configurez l'âge que fixe l'État membre. Une juridiction
n'est servie qu'une fois par un `Jurisdictions` : un code donné deux fois est refusé, et 300 juridictions au plus sont
servies. L'enregistrement d'un mineur nomme sa juridiction par son code ; un code retiré de la configuration alors que
des enregistrements le nomment encore lève `UnknownJurisdiction` quand on les lit.

## Un anniversaire le 29 février

Une personne née le 29 février complète une année, une année commune, le 1er mars avec `FirstOfMarch` et le 28 février
avec `TwentyEighthOfFebruary`. Une année bissextile, l'anniversaire tombe le 29 février avec les deux règles. Choisissez
la règle que donne la loi de la juridiction.
