<!-- phil-dev source-sha256=2e0bbc3e33531244e903f82ce72545c733e1b302593b6e1cd7d4185e432aab91 -->
# Référence

| Type | Rôle |
|---|---|
| `Traits\AsInvocableEnumTrait` | `__invoke()` et les appels statiques de cas, qui renvoient tous deux la primitive du cas. |
| `Traits\AsNameableEnumTrait` | `names()` : les noms des cas. |
| `Traits\AsValuableEnumTrait` | `values()` : les valeurs des cas, ou les noms d'une enum pure. |
| `Traits\AsSelectableEnumTrait` | `options()` : du nom à la valeur pour une enum adossée, les noms pour une enum pure. |
| `Traits\AsStringSelectableEnumTrait` | `stringOptions()` : les options mises en forme par une fonction de rappel et jointes par un séparateur, des éléments HTML `<option>` par défaut, échappés et un par ligne. |
| `Traits\AsComparableEnumTrait` | `is()`, `isNot()`, `in()`, `notIn()`, `has()`, `doesntHave()`, `equalsOneOf()`, `notEqualsOneOf()`. |
| `Traits\AsFromambleEnumTrait` | `from()` et `tryFrom()` par nom pour les enums pures, `fromName()` et `tryFromName()` pour toutes les enums. |
| `Traits\AsMetadatableEnumTrait` | Les accesseurs de métadonnées, `fromMeta()` et `tryFromMeta()` ; un accesseur qu'aucune propriété méta activée ne nomme lève `BadMethodCallException`. |
| `Traits\AsSelfAwareableEnumTrait` | `isPure()`, `isBacked()`, `isBackedByInteger()`, `isBackedByString()`. |
| `Traits\AsCollectableEnumTrait` | `collect()`, `count()`, `first()`, `last()`, `filter()`, `whereMeta()`, `inGroup()`, `sortBy()` sur l'enum. |
| `Traits\AsOrdinalEnumTrait` | `ordinal()`, `next()`, `previous()`, `compareTo()`, `isBefore()`, `isAfter()`, `isBetween()` ([Ordre, analyse et valeurs par défaut](order-and-parsing.md)). |
| `Traits\AsParsableEnumTrait` | `parse()` et `tryParse()` : par valeur, par nom ou par alias, en tenant compte ou non de la casse. |
| `Traits\AsDefaultableEnumTrait` | `default()` et `tryDefault()` : le cas marqué `#[DefaultCase]`. |
| `Traits\AsStatefulEnumTrait` | `canTransitionTo()`, `nextStates()`, `isFinal()`, `initialStates()`, `transitions()` : les passages que déclare `#[TransitionsTo]`. |
| `Traits\AsEnumerableEnumTrait` | Tous les traits sauf `AsStringSelectableEnumTrait`. |
| `Sort\SortDirection` | `asc` ou `desc`, analysé avec souplesse ; son mot-clé SQL, sa comparaison, son inverse ([Opérations](operations.md)). |
| `Collection\CaseCollection` | Une liste immuable de cas d'une même enum, avec les opérations ([Opérations](operations.md)). |
| `Collection\EnumSet` | Un ensemble immuable de cas d'une même enum, avec les opérations d'ensemble et les masques de bits ([Ensembles et tables associatives](sets-and-maps.md)). |
| `Collection\EnumMap` | Une table associative immuable qui contient une valeur pour chaque cas d'une même enum ([Ensembles et tables associatives](sets-and-maps.md)). |
| `Attribute\Meta` | Les propriétés méta que déclare une enum, ou un trait. |
| `Attribute\Description`, `Attribute\Group`, `Attribute\Label` | Des propriétés méta à valeur de chaîne, fournies avec la bibliothèque. |
| `Attribute\Alias` | Une propriété méta répétable : une autre graphie qu'accepte `parse()`. |
| `Attribute\Weight` | Une propriété méta : la fréquence à laquelle `random()` tire le cas, 1 par défaut. |
| `Attribute\DefaultCase` | Marque le cas que renvoie `default()`. |
| `Attribute\TransitionsTo` | Les cas vers lesquels un cas peut passer. |
| `Attribute\BitFlags` | Déclare une enum d'indicateurs binaires, vérifiée par PHPStan. |
| `Attribute\RequiredMeta` | Les propriétés méta que chaque cas doit porter, vérifiées par PHPStan et par `Reflection::missingRequiredMeta()`. |
| `Meta\AbstractMetaProperty` | La base d'une propriété méta : sa valeur, `transform()`, `defaultValue()`, `customMethodName()`, `method()` et la méthode statique `make()`. |
| `Meta\Reflection` | Lit les propriétés méta d'une enum, et la valeur — ou chaque valeur, `metaValueList()` — de l'une d'elles sur un cas, une fois par enum et par cas ; la propriété méta que lit un accesseur, `metaPropertyNamed()` ; si un cas est obsolète, le cas par défaut, les métadonnées requises qui manquent aux cas, les transitions d'un cas, et `parse()` pour n'importe quelle enum. |
| `Exceptions\UndefinedCaseError` | Un appel statique ne nomme aucun cas de l'enum. |
| `Internal\Cases` | Interne : ce que les traits lisent des cas — noms, primitives, options — et la vérification que des cas appartiennent à une enum. |
| `PHPStan\InvocableEnum\ReflectionExtension`, `PHPStan\MetadatableEnum\ReflectionExtension` | Les extensions PHPStan ([PHPStan](phpstan.md)). |
| `PHPStan\VirtualMethod` | La façon dont les deux extensions décrivent à PHPStan une méthode qu'un trait sert par `__call()` ou `__callStatic()`. |
| `PHPStan\RequiredMeta\RequiredMetaRule` | La règle PHPStan de `#[RequiredMeta]`. |
| `PHPStan\Declaration\EnumDeclarationRule` | La règle PHPStan de ce que les attributs d'une enum déclarent à tort. |
| `PHPStan\Ordinal\StoredOrdinalRule` | La règle PHPStan d'un ordinal qui quitte le processus. |
