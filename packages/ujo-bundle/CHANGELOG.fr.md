<!-- phil-dev source-sha256=ea342b8a70e666576fe14cb323a6e9dd625a6323a6265db062436e678d32ab4d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/ujo-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-18

`max_bytes` borne aussi le HTML en ligne, sur `phil/ujo` 0.1.3.

### Ajouté

- Le service `Phil\Ujo\Html\InlineSanitizer`, construit avec `max_bytes`, par lequel le `Normalizer`, le `HtmlRenderer`
  et `PlainText` lisent le HTML en ligne.

### Modifié

- `max_bytes` borne chaque fragment HTML en ligne aussi bien que le JSON que lit le décodeur : un document construit en
  PHP, qu'aucun décodeur ne voit, est refusé quand même à son rendu ou à sa conversion en texte. `phil/ujo` 0.1.3 ou
  plus récent est exigé.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilUjoBundle`, configuré sous `phil_ujo` : `allow_raw`, `max_bytes`, `max_depth`, `max_blocks`, `max_html_length` et
  `max_raw_length`.
- Les services de `phil/ujo` — `BlockTypeRegistry`, `Normalizer`, `HtmlRenderer`, `PlainText`, `JsonDecoder` et le `Ujo`
  public —, chacun sous son nom de classe, par `UjoServices`.
- `UjoExtension` : les filtres et les fonctions Twig `ujo_html` et `ujo_text`, enregistrés quand Twig est installé.
- `UjoType` et `UjoTransformer` : un champ qui contient du JSON UJO, gardé comme JSON canonique ou comme `Document`, un
  document invalide échouant avec le message de la bibliothèque.
- `ValidUjo` et `ValidUjoValidator` : un document valide au niveau de validation de la contrainte — sa forme par défaut,
  ou ce qu'il lui faut pour être publié —, de types de blocs acceptés quand ils sont nommés, avec des messages en
  anglais et en français.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
