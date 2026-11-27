<!-- phil-dev source-sha256=2b66338cbfb106553710f73d67e6a968c71d77a8a3c16054db70ec1eb5dce6fb -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/typography-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/translation-contracts` 3.8), and of each development
  requirement (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-18

Une clé facultative, sur `phil/typography` 0.2.

### Ajouté

- La configuration `max_bytes` : le plus long texte que corrigent les typographes et les filtres, en octets, 1 Mio par
  défaut — la valeur par défaut de `phil/typography` —, un entier positif vérifié à la construction du conteneur, donné
  à `Typographers`.
- La section [Plafond d'un texte](doc/fr/configuration.md#plafond-dun-texte) de la page sur la configuration.

### Modifié

- `phil/typography` 0.2 est exigé : un texte plus long que son plafond est refusé par `InputTooLarge`.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- Les services `Typographers`, `Typographer` pour la locale par défaut, et `phil_typography.typographer.<locale>` pour
  chaque locale configurée, aussi injectés dans un argument nommé d'après elle.
- `PhilTypographyBundle` et la configuration `phil_typography` : `default_locale`, `hyphenation`,
  `experimental_hyphenation`, `protected_tags`, les noms de correcteurs par locale vérifiés d'après les préréglages, et
  l'intégration `ujo` avec sa locale, la locale courante quand elle est nulle.
- Les filtres Twig `typography`, sur du HTML avec ce qui n'est pas marqué sûr échappé d'abord, et `typography_text`, sur
  du texte brut, chacun avec une locale facultative ; sans elle, la locale courante du `LocaleSwitcher` de Symfony —
  celle de la requête, ou celle que fixe `runWithLocale()` —, et `default_locale` quand le traducteur n'est pas
  installé.
- `TypographyHtmlFilter` et `UjoFilterPass` : le typographe d'une locale, ou de la locale courante, exécuté sur le HTML
  de chaque document UJO que rend `phil/ujo-bundle`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
