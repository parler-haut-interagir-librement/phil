<!-- phil-dev source-sha256=026909101b833aa9cacac127e7c96395d92112723fa920e3b88cea2ee48ffc31 -->
# Profils

Un `ImportProfile` décrit ce que contient un fichier d'un type importable. C'est une valeur : construite dans le code,
ou relue à partir du JSON qu'elle a écrit.

```php
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Rule\DateOrder;

$profile = new ImportProfile('membership', [
    new Column('email', ColumnType::Email, required: true, aliases: ['courriel', 'e-mail']),
    new Column('club_code', required: true, maxLength: 32, reference: new Reference('club')),
    new Column('level', choices: ['bronze', 'silver', 'gold']),
    new Column('starts_on', ColumnType::Date),
    new Column('ends_on', ColumnType::Date),
], naturalKey: ['email', 'club_code'], rules: [new DateOrder('starts_on', 'ends_on')]);
```

## Types

Le type d'une colonne dit comment une cellule est lue. La cellule est d'abord débarrassée de ses espaces de bordure ;
une cellule vide vaut null.

| Type | Lit | Valeur |
|---|---|---|
| `Text` | n'importe quoi | le texte tel qu'écrit |
| `Integer` | au plus 18 chiffres, un signe facultatif | `int` |
| `Decimal` | des chiffres, un signe et une fraction facultatifs après `.` ou `,` | le texte, avec `.` : aucun chiffre perdu par un nombre à virgule flottante |
| `Boolean` | `1`, `true`, `yes`, `y`, `oui`, `o` et `0`, `false`, `no`, `n`, `non`, quelle que soit la casse | `bool` |
| `Date` | `YYYY-MM-DD`, une date qui existe | `DateTimeImmutable` à minuit UTC |
| `Email` | une adresse d'au plus 254 caractères | l'adresse en minuscules |

## Vérifications

- `required` : une cellule vide est refusée.
- `maxLength` : un texte plus long est refusé, compté en caractères.
- `choices` : une colonne de texte prend l'une des valeurs listées, comparée sans égard à la casse et conservée comme
  listée.
- `reference` : la valeur est la clé naturelle d'un enregistrement du type nommé — voir plus bas.

Une colonne a un `header`, celui qu'écrit son gabarit, son nom par défaut, et jusqu'à 32 `aliases`, les autres en-têtes
par lesquels elle est reconnue.

## La clé naturelle

La clé naturelle nomme les colonnes obligatoires dont les valeurs identifient un enregistrement. Une ligne dont la clé
correspond à un enregistrement existant met cet enregistrement à jour ; toute autre ligne en crée un. Deux lignes d'un
fichier ayant la même clé ne sont pas écrites toutes les deux : la seconde est refusée, avec la ligne de la première. La
clé de plusieurs colonnes est lue dans l'ordre du profil, ses valeurs en texte — une date en `YYYY-MM-DD`, oui et non
en `1` et `0`.

Une mise à jour porte les colonnes que le fichier met en correspondance, une cellule vide valant null : la cible
efface cette valeur. Les colonnes que le fichier ne met pas en correspondance ne sont pas dans la ligne, et
`PlannedRow::has()` permet de les distinguer, afin que la cible les laisse telles quelles.

## Références

Une colonne dotée d'une `Reference` contient la clé naturelle d'un enregistrement d'un autre type, ou du même type : l'unité
d'une personne, le parent d'une unité. Le planificateur cherche cet enregistrement :

1. dans le fichier, quand la référence vise le type du profil — quelle que soit la ligne, plus haut ou plus bas ;
2. parmi les enregistrements que la cible détient déjà, par `KeyLookup::existing()`.

Une référence introuvable refuse la ligne. Il en va de même d'une ligne qui est son propre parent, d'un cycle de
références — chaque ligne du cycle est refusée, avec la suivante —, et d'une ligne dont l'enregistrement du fichier est
refusé, avec sa ligne. Une référence au type même du profil exige une clé naturelle d'une seule colonne.

## Règles portant sur une ligne

Une `RowRule` vérifie ensemble les cellules d'une ligne, une fois chaque cellule lue sans erreur. `DateOrder` refuse une
date de fin antérieure à sa date de début, quand les deux sont données. Une application ajoute ses propres règles :
voir les [Points d'extension](extension-points.md#règles).

## La correspondance

Une `ColumnMapping` dit quel en-tête d'un fichier remplit quelle colonne. Le planificateur utilise, dans cet ordre, la
correspondance qu'on lui donne, celle que garde le profil, et une supposition pour chaque colonne obligatoire ou
facultative encore sans correspondance parmi les en-têtes encore libres. La supposition compare chaque en-tête au nom
de la colonne, à son en-tête et à ses alias, après normalisation : minuscules, accents des lettres latines retirés,
tout caractère qui n'est ni une lettre ni un chiffre supprimé — `Nom de famille`, `nom-de-famille` et `NOMDEFAMILLE` se
lisent pareil. Un en-tête remplit une colonne ; le premier en-tête qui correspond l'emporte. Un en-tête sans colonne est
ignoré.

```php
$mapping = $plan->mapping->with('Mail pro', 'email')->without('Notes');
$plan = $planner->plan($source, $profile, $target, $mapping);
```

Une colonne obligatoire qu'aucun en-tête ne remplit arrête le plan avec `MappingIncomplete`, dont `columns` les liste :
la personne qui importe choisit leurs en-têtes, et le fichier est planifié de nouveau.

## Sérialisation

`toArray()` et `toJson()` écrivent le profil — format, type, colonnes, clé naturelle, règles et correspondance —, et
`fromArray()` et `fromJson()` le relisent à l'identique, en refusant des données de mauvaise forme par une
`InvalidProfile` qui nomme le champ. Le JSON est lu jusqu'à 1 Mio et 16 niveaux de profondeur. Une règle qu'ajoute une
application est relue quand on passe sa classe :

```php
$json = $profile->withMapping($plan->mapping)->toJson();
$profile = ImportProfile::fromJson($json, ruleTypes: [ClubSeasonRule::class]);
```

Un profil garde une correspondance d'en-têtes, jamais une valeur d'un fichier.
