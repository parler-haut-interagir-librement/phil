<!-- phil-dev source-sha256=8a144646f19d7853ac438e2cc676d655520d4b3d986d0c82f3ea191dfb6efa6f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/enums` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.1 - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.3.0 - 2026-11-11

### Ajouté

- `Sort\SortDirection` : `asc` ou `desc`, lu avec souplesse, avec `sql()`, `compare()`, `reverse()`,
  `isDescending()` et `descendingIf()` ; `sortBy()`, `sortByName()` et `sortByValue()` l'acceptent aussi bien qu'un
  booléen.
- Des opérations sur les cas : `AsCollectableEnumTrait` (`collect()`, `count()`, `first()`, `last()`, `filter()`,
  `whereMeta()`, `sortBy()`) et la `Collection\CaseCollection` immuable, qui ajoute `reject()`, `only()`,
  `except()`, `contains()`, `sortByName()`, `sortByValue()`, `reverse()`, `map()`, `names()`, `values()`,
  `options()` et `isBacked()`. `AsEnumerableEnumTrait` inclut le nouveau trait.
- `inGroup()` sur l'enum et sur `CaseCollection` : les cas d'au moins un des groupes donnés, lus de chaque
  `#[Group]` que porte un cas ; `Reflection::metaValueList()` donne chaque valeur d'une méta-propriété répétable.
- Le lecteur de métadonnées garde les méta-propriétés de chaque enum et les valeurs de chaque cas après la première
  lecture, et n'instancie que les attributs de méta-propriétés.
- L'extension PHPStan lit les méta-propriétés qu'un trait déclare avec son propre `#[Meta]`, comme le faisait déjà
  l'exécution.
- `groupBy()`, `groupByMeta()`, `partition()`, `keyBy()` et `pluck()` sur `CaseCollection` ; `groupBy()` et
  `groupByMeta()` sur l'enum.
- `random()`, pondéré par la nouvelle méta-propriété `#[Weight]`, avec un `Random\Randomizer` facultatif.
- `Collection\EnumSet` : un ensemble immuable de cas avec union, intersection, différence, complément, inclusion et
  masques de bits pour les enums de drapeaux binaires.
- `Collection\EnumMap` : une table immuable qui garde une valeur pour chaque cas, construite par `total()` ou
  `fromArray()`.
- `AsOrdinalEnumTrait` : `ordinal()`, `next()`, `previous()`, `compareTo()`, `isBefore()`, `isAfter()`,
  `isBetween()`.
- `AsParsableEnumTrait` : `parse()` et `tryParse()` par valeur, par nom ou par le nouveau `#[Alias]` répétable, en
  ignorant au besoin la casse.
- `AsDefaultableEnumTrait` et `#[DefaultCase]` : `default()` et `tryDefault()`.
- Les cas obsolètes : `isDeprecated()`, `withoutDeprecated()` et `deprecated()`, lus du `#[\Deprecated]` de PHP.
- `#[RequiredMeta]`, une règle PHPStan (`philEnums.requiredMeta`) qui signale un cas auquel manque une méta-propriété
  requise, et `Reflection::missingRequiredMeta()`.
- `AsStatefulEnumTrait` et `#[TransitionsTo]` : `canTransitionTo()`, `nextStates()`, `isFinal()`,
  `initialStates()`, `transitions()`.
- `EnumSet::fromValues()`, `EnumMap::fromPartial()` et `#[BitFlags]`.
- `Reflection::parse()` : la lecture souple de `parse()` pour n'importe quel enum.
- Des règles PHPStan : `philEnums.defaultCase`, `philEnums.ambiguousAlias`, `philEnums.negativeWeight`,
  `philEnums.notBitFlags`, `philEnums.requiredMetaUndeclared` et `philEnums.storedOrdinal`.
- `AsEnumerableEnumTrait` inclut les traits ordinal, parsable, defaultable et stateful.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `Reflection::metaPropertyNamed()` : la méta-propriété que lit un accesseur.

### Modifié

- Le paquet est `phil/enums`, renommé depuis `ph-il/enums`, abandonné en sa faveur ; l'espace de noms reste
  `Phil\Enums`.
- PHP 8.5 ou plus récent, et `ext-mbstring` déclaré.
- `Meta\Reflection` est final et son analyseur d'attributs privé ; les traits lisent leurs cas par
  `Internal\Cases`.
- La base des méta-propriétés, les traits invocable, fromamble, comparable et metadatable, les options en chaîne,
  l'attribut `#[Meta]`, `UndefinedCaseError`, la lecture des méta-propriétés activées et les deux extensions PHPStan
  sont réécrits avec la même API publique ; les extensions partagent `PHPStan\VirtualMethod`.
- `AbstractMetaProperty::make()` est statique, si bien que `Label::make('Draft')` construit la valeur que cherche
  `fromMeta()` ; un appel sur une instance marche encore.
- Un accesseur qu'aucune méta-propriété de l'enum ne nomme lance `BadMethodCallException` au lieu de retourner
  `null`.
- `Reflection::metaProperties()` liste chaque accesseur une seule fois : de deux propriétés activées au même
  accesseur, la première listée.

### Corrigé

- `values()`, `options()`, `names()` et les accesseurs invocables sont typés sans surcharges `@var` en ligne.
- `stringOptions()` échappe pour le HTML la valeur et le libellé de ses options par défaut, les joint par un saut de
  ligne (sa colle par défaut était une barre oblique inverse et un `n`), et écrit un enum pur par son nom au lieu de
  retourner une chaîne vide.
- Le `sortBy()` de l'enum accepte un `SortDirection`, comme le fait `CaseCollection::sortBy()`.

## 0.2.2 - 2026-09-13

### Ajouté

- `AbstractMetaProperty` est désormais générique sur le type de sa valeur (`@template TValue`). Les méta-propriétés
  déclarent ce qu'elles contiennent avec `@extends AbstractMetaProperty<string>` (fait pour Description, Group et
  Label)

### Modifié

- L'extension PHPStan déduit désormais le vrai type de retour des accesseurs de méta générés
  (`->label()`, `->description()`, ...) du `TValue` de la méta-propriété, au lieu de toujours signaler `mixed`
- Un accesseur n'est nullable que si au moins un cas de l'enum n'a pas l'attribut et peut donc se rabattre sur
  `defaultValue()` ; un enum qui attribue chaque cas obtient le type de valeur nu
- Les signatures de `Meta`, de `Meta\Reflection` et d'`AsMetadatableEnumTrait` mises à jour pour l'`AbstractMetaProperty`
  générique

## 0.2.1 - 2026-04-15

### Corrigé

- Ajout d'Attribute::TARGET_CLASS_CONSTANT aux attributs de méta (Description, Group, Label)
- Correction d'un lien dans la documentation

## 0.2.0 - 2026-03-01

### Ajouté

- Méta-propriété Label
- Ajout d'equalsOneOf et de notEqualsOneOf dans AsComparableEnumTrait
- Ajout d'AsSelfAwareableEnumTrait : la conscience de soi
  * isPure
  * isBacked
  * isBackedByInteger
  * isBackedByString
- Ajout des extensions PHPStan
- Ajout des tests
- Ajout de Castor comme exécuteur

### Modifié

- Documentation

### Corrigé

- Documentation
- Toutes les erreurs PHPStan
- Correction de tous les problèmes de style de code

## 0.1.0 - 2026-02-19

### Ajouté

- Première implémentation du paquet
- AsComparableEnumTrait
- AsFromambleEnumTrait
- AsInvocableEnumTrait
- AsMetadableEnumTrait
- AsNameableEnumTrait
- AsValuableEnumTrait
- AsSelectableEnumTrait
- AsStringSelectableEnumTrait
- AsEnumerableEnumTrait
- Meta
  - Description
  - Group
