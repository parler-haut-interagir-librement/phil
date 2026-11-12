<!-- phil-dev source-sha256=1f9d94ba722ca98b9deb7f7dcefc35742bf7e50dda5c668ff8f93615bd5f4fc4 -->
# Le thème de formulaire

`@PhilUx/form/phil_ux_layout.html.twig` est la mise en page en `div` de Symfony, dont les rangées sont écrites comme
les champs du kit PHIL UX, les classes que phil/ux-css habille. Il reprend de `form_div_layout.html.twig` chaque bloc
qu'il n'écrit pas.

## Appliqué à chaque formulaire

Le bundle ajoute le thème en tête de `twig.form_themes`, comme Symfony le fait pour son thème par défaut. La liste que
Symfony lit est `form_div_layout.html.twig`, puis le thème PHIL UX, puis les thèmes que l'application liste : un thème
plus tardif l'emporte, si bien qu'un bloc que l'application écrit remplace celui du bundle. Une balise
`{% form_theme %}` dans un gabarit l'emporte sur tous, et `{% form_theme form with [...] only %}` les écarte.

```yaml
# config/packages/phil_ux.yaml
phil_ux:
    form_theme: false    # the forms keep the themes of twig.form_themes only
```

Désactivez le thème quand l'application utilise un autre thème complet, comme `bootstrap_5_layout.html.twig` : des blocs
mêlés de deux thèmes donnent un balisage que ni l'un ni l'autre n'attend.

## Le balisage

| Bloc | Ce que le thème écrit |
|---|---|
| `form_row` | `<div class="isField" data-slot="field">`, l'étiquette avec `isFieldLabel`, les erreurs, le widget et l'aide |
| `form_row` d'un champ composé | `<fieldset class="isFieldSet" data-slot="field-set">` et son `<legend class="isFieldLegend">` : choix étendus, une date de trois listes, un sous-formulaire |
| `form_row` avec des erreurs | `isDanger` et `data-invalid="true"` sur la rangée ; Symfony marque le contrôle `aria-invalid="true"` |
| `form_help` | `<div id="…_help" class="isFieldDescription" data-slot="field-description">` |
| `form_errors` | `<div class="isFieldError" data-slot="field-error">` contenant un `p` pour une erreur, un `ul` pour plusieurs, chacune avec l'identifiant que Symfony nomme dans `aria-describedby` |
| `choice_widget_expanded` | `isRadioGroup` sur les boutons radio d'un choix unique ; les cases à cocher gardent la mise en page de phil/ux-css |

Tout autre bloc est celui de Symfony. Les classes que l'application donne dans `row_attr`, `label_attr`, `help_attr` et
`attr` sont gardées après celles du thème.

```html
<div class="isField isDanger" data-slot="field" data-invalid="true">
    <label class="isFieldLabel required" for="event_title">Title</label>
    <div class="isFieldError" data-slot="field-error"><p id="event_title_error1">This value is too short.</p></div>
    <input type="text" id="event_title" name="event[title]" required="required"
        aria-describedby="event_title_error1" aria-invalid="true">
</div>
```

## Accessibilité

- L'étiquette nomme le contrôle par `for` ; un champ composé est un `fieldset` nommé par sa `legend`, si bien que chaque
  bouton radio est lu avec la question à laquelle il répond.
- L'aide et les erreurs sont liées au contrôle par `aria-describedby`, que Symfony écrit, et le contrôle est marqué
  `aria-invalid`.
- Les erreurs arrivent avec la page : elles ne sont donc pas une région dynamique, qui lirait toutes les erreurs du
  formulaire d'un coup au chargement de la page.
- Une rangée invalide ne repose pas sur la couleur seule : le message est du texte, et phil/ux-css double la bordure de
  danger.
