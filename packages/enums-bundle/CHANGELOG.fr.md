<!-- phil-dev source-sha256=388ec889cccd84346617e9d608510360dd863845e09d136ff154b9e985a5d317 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/enums-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.3-alpha - 2026-11-25

### Modifié

- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31, `symfony/translation-contracts` 3.8), et de
  chaque prérequis de développement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `doctrine/persistence` 4.2, …). Les types de colonnes `EnumSet` entrent dans le registre de types que fournit une
  configuration DBAL, sur lequel le fournisseur de types de chaque connexion se rabat, au lieu du `Type::addType()`
  statique, que DBAL 4.6 marque obsolète.

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-15

### Modifié

- `EnumSetOf` : les paramètres `{{ count }}` et `{{ limit }}` de ses violations sont des entiers.
- Les messages des contraintes sont traduits par des catalogues en ICU MessageFormat (`validators+intl-icu`), que le
  bundle remet au traducteur de FrameworkBundle quand `MessageFormatter` est installé : en anglais, `minMessage` et
  `maxMessage` d'`EnumSetOf`, dont le pluriel suit `{{ limit }}` ; en français, chaque message par défaut
  d'`EnumCase`, d'`EnumSetOf` et de `Transition`. Les messages par défaut, qui sont les clés de traduction, ne
  changent pas.

## 0.1.1-alpha - 2026-11-14

### Ajouté

- `EnumsExpressionLanguagesPass` : les fonctions d'expression `enum_meta`, `enum_in_group` et `enum_from` dans les
  expressions des attributs de contrôleur (`controller.expression_language`) et des gardes de workflow
  (`workflow.security.expression_language`), pour que le `lint:expressions` de Symfony vérifie les expressions qui
  les y appellent, et qu'une fonction mal écrite fasse échouer la vérification.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `CaseSelection` : des sous-ensembles par `only`, `except`, groupes et valeurs de méta.
- La traduction : `AsTranslatableEnumTrait`, `Translatable`, `TranslatableText`, des clés tirées de `Label` et de
  `Description` ; `EnumTranslation` lit l'attribut `Translatable` d'un enum une fois par processus, et construit une
  seule fois la clé de libellé générée d'un cas ; un extracteur de traductions pour `translation:extract`.
- Doctrine : des types de colonnes `EnumSet`, en masque de bits ou en JSON.
- Les formulaires : les options `group`, `only`, `except`, `where_meta`, `without_deprecated`, `group_by_meta`,
  `preferred_group` d'`EnumType`, des libellés tirés des métadonnées ; `EnumSetType`.
- La validation : la contrainte `EnumCase`, qui lit un entier écrit en chaîne pour un enum adossé à des entiers et ne
  trouve aucun cas, sans erreur, dans une valeur d'un autre type ; la contrainte `EnumSetOf` ; la contrainte
  `Transition`, d'après le `#[TransitionsTo]` du cas précédent, lu de l'objet ou de Doctrine.
- Twig : `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge` avec `Color` et `Icon`.
- Les commandes : `debug:enums` ; `phil:enums:typescript`, avec les descriptions, les groupes et la valeur par
  défaut, et `--check` ; `phil:enums:icu`, qui refuse, en nommant le cas, une clé qu'ICU n'accepte pas comme mot-clé
  (une espace ou une ponctuation comme `-` ou `.`), une clé `other`, une clé qui revient deux fois et un nom
  d'argument qu'ICU n'accepte pas ; `phil:enums:check-sets`, qui s'exécute aussi quand Doctrine ORM est installé sans
  DoctrineBundle ; `make:enum`, qui rend l'enum avec `EnumBlueprint::source()`, son gabarit ne faisant que
  l'afficher, et refuse deux noms qui donnent la même valeur à un enum de chaînes, et un nom qui finit par un saut de
  ligne.
- `EnumSchema` (JSON Schema) et `EnumsNormalizer` (sérialiseur), avec les ensembles et les tables d'enums relus dans
  le sérialiseur ; les schémas des enums dans API Platform (métadonnées des propriétés) et NelmioApiDoc (descripteur
  de modèles).
- Les arguments de contrôleur `#[ParseEnum]`, les fonctions d'expression `enum_meta`, `enum_in_group` et `enum_from`,
  et les machines à états de Symfony construites d'après `#[TransitionsTo]` (configuration `workflows`).
- Une suite fonctionnelle qui exécute chaque intégration dans un noyau.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
