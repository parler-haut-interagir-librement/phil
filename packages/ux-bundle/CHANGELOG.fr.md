<!-- phil-dev source-sha256=67fdbe2ee1a02f561cea32d461e7bdfeb37dfbfc58a5a8021c09307e0fe80f6b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/ux-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31).

### Corrigé

- `phil/ux-css` 0.6 est accepté à côté de 0.5 : le paquet ne s'installait pas à côté de la version publiée le
  2026-11-21.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `PhilUxBundle`, alias d'extension `phil_ux`.
- Le thème de formulaire PHIL UX `@PhilUx/form/phil_ux_layout.html.twig` : la disposition en div de Symfony avec ses
  rangées écrites comme les champs du kit PHIL UX (`isField`, `isFieldSet` et sa légende pour les champs composés,
  `isFieldDescription`, `isFieldError`, `isRadioGroup`), ajouté en tête de `twig.form_themes` pour que chaque formulaire
  soit rendu avec lui et que les thèmes de l'application l'emportent ; `phil_ux.form_theme: false` le désactive.
- Le dossier `dist/` de phil/ux-css associé à `phil-ux/` avec AssetMapper, servi comme `phil-ux/phil-ux.css`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
