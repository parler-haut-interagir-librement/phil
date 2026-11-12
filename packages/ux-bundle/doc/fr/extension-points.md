<!-- phil-dev source-sha256=d1d7f0a57d8428b8b5c8f8a1bd5df4ae30cc73a948aaa37ab1b7580830bf9458 -->
# Points d'extension

Ce qu'une application configure, remplace ou utilise. Le bundle ne déclare aucun port PHP et n'enregistre aucun
service : son contrat, ce sont sa clé de configuration, les blocs de son thème de formulaire et le chemin logique de la
feuille de style.

## Le thème de formulaire

| Point | Comment |
|---|---|
| Le désactiver | `phil_ux.form_theme: false` ([Le thème de formulaire](form-theme.md#appliqué-à-chaque-formulaire)) |
| Un bloc changé | Un thème de l'application listé dans `twig.form_themes`, qui vient après celui du bundle et l'emporte pour les blocs qu'il écrit |
| Un formulaire, un autre thème | `{% form_theme form 'theme.html.twig' %}` dans son gabarit, ou `with [...] only` pour écarter chaque thème global |
| Les classes | `row_attr`, `label_attr`, `help_attr` et `attr` d'un champ : leurs classes sont ajoutées après celles du thème |
| L'apparence | Les classes `isField`, `isFieldSet`, `isFieldLabel`, `isFieldLegend`, `isFieldDescription`, `isFieldError` et `isRadioGroup`, habillées par phil/ux-css, réécrites dans les styles de l'application hors de toute couche |

## La feuille de style

| Point | Comment |
|---|---|
| Le chemin logique | `phil-ux/phil-ux.css`, `PhilUxBundle::STYLESHEET`, pour `asset()` ou une entrée de la table d'importation |
| Le répertoire | `PhilUxBundle::stylesheetDirectory()`, le répertoire `dist/` de phil/ux-css là où Composer l'a installé |
| L'apparence | Les propriétés personnalisées `--phil-*` et les couches de cascade de phil/ux-css, décrites dans sa documentation |
