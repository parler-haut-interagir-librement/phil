<!-- phil-dev source-sha256=45e7f6f55dec758cffba25515784426bbe06307d2032d1517f56cd64800e5462 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/ux-easyadmin-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- La feuille de style servie porte les jetons de `phil/ux-css` 0.6 : `--phil-neutral-15`, et la surface sombre sur lui.
- Exige la dernière mineure de chaque dépendance (`phil/ux-css` 0.5), and of each development requirement
  (`doctrine/doctrine-bundle` 3.4, `symfony/ux-twig-component` 3.8, `twig/extra-bundle` 3.31).

### Sécurité

- Exige `easycorp/easyadmin-bundle` 5.10.4 ou plus récent, la version qui corrige huit avis sur l'interface
  d'administration que ce thème habille : GHSA-rrpv-c5fp-ppxr, GHSA-x7m7-8grf-r6m2, GHSA-v773-mfpm-hqgq,
  GHSA-4jgw-cjw7-8j52, GHSA-jqfc-9phg-ghr7, GHSA-f7rw-7wx5-3c54, GHSA-rjcw-39g5-9hqr et GHSA-63v8-jq78-cmg3.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `PhilUxEasyAdminBundle`, alias d'extension `phil_ux_easy_admin`, et sa clé `theme`.
- La feuille de style `assets/styles/phil-ux-easyadmin.css`, associée à `phil-ux-easyadmin/` avec AssetMapper : les
  jetons de phil/ux-css sous la couche `vendor` d'EasyAdmin, et la couche `phil-easyadmin`, déclarée après `ea`, qui
  donne aux variables d'EasyAdmin — accent, surfaces, barre latérale, texte, liens, bordures, tableaux, formulaires,
  alertes, pastilles, coins, largeur du focus et polices — les rôles de PHIL UX, en clair et en sombre.
- `Infrastructure\EasyAdmin\ThemeStylesheet`, qui lie la feuille de style sur chaque page de chaque tableau de bord,
  après les feuilles de style propres au tableau de bord et une seule fois.
- `Infrastructure\Stylesheet\ThemeSheet` et `bin/build.php`, qui écrivent la feuille de style à partir de
  `assets/src/phil-ux-easyadmin.css` et des fichiers de jetons de phil/ux-css.
- Les tests qui tiennent le thème à EasyAdmin 5.10 et à phil/ux-css 0.5 : chaque variable qu'il fixe déclarée par
  EasyAdmin, chaque jeton qu'il lit déclaré par phil/ux-css, et ses couleurs calculées par Chrome dans les deux schémas.
- La recette Flex `recipe/0.1/`, qui enregistre le bundle et écrit sa configuration.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
