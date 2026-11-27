<!-- phil-dev source-sha256=469ad4c09819e6e4f21c76e1925090ccbbac065656f017810f884923d470bd33 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/url-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.4-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.3-alpha - 2026-11-18

### Ajouté

- La clé de configuration `phil_url.column_type`, `null` par défaut : `null` enregistre le type de colonne `phil_url`
  quand `doctrine/dbal` et DoctrineBundle sont installés, `true` les exige et refuse de construire le conteneur, en
  nommant le paquet manquant, quand l'un est absent, `false` ne l'enregistre jamais.

### Corrigé

- Le bundle démarre sans `doctrine/dbal` : `PhilUrlBundle::boot()` et `shutdown()` ne touchent `UrlColumnType` que si ce
  paquet est installé, et le type de colonne n'est enregistré qu'alors. `doctrine/dbal` reste une suggestion.

## 0.1.2-alpha - 2026-11-15

### Corrigé

- `PhilUrlBundle::shutdown()` remet `UrlColumnType::allowLocalhost()` à `false` : un noyau démarré après un autre qui
  permettait `localhost`, ou un test qui s'exécute après lui dans le même processus, n'hérite plus de son réglage.

## 0.1.1-alpha - 2026-11-11

### Ajouté

- La clé de configuration `phil_url.allow_localhost`, `false` par défaut, aussi un paramètre du conteneur : `true`, en
  développement et en test, laisse `TypedUrlType`, `UrlOfTypeValidator`, `UrlColumnType` et `UrlExtension` lire
  `localhost` et les noms sous `.localhost` ; une adresse IP et tout autre nom seul restent refusés. Chacun la prend
  comme `allowLocalhost`, `TypedUrlTransformer` aussi ; `UrlColumnType::allowLocalhost()` est réglé au démarrage du
  bundle.

### Modifié

- Le paquet exige `phil/url` 0.1.3, qui refuse les noms sous `.localhost` à moins qu'ils soient permis.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AcceptedUrlTypes`, les sortes acceptées en ensembles d'enums.
- `UrlColumnType` (`phil_url`).
- `PhilUrlBundle`, alias d'extension `phil_url`.
- `UrlExtension`.
- `TypedUrlType` et `TypedUrlTransformer`.
- `UrlOfType`, dont le `@param` nomme l'`EnumSet` qu'il accepte, et son validateur.
- Les messages en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
