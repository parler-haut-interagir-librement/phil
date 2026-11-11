<!-- phil-dev source-sha256=83c1bdea0ef513df59ddb83cc3ce8f0ec4dcf1a7b8b369218f69855ea80780a3 -->
# Formulaires

## Choisir les avertissements

`ContentWarningsType` choisit les avertissements d'un contenu parmi les thèmes du catalogue. Ses données sont un
`ContentWarnings`, ou `null` lorsque rien n'est coché.

```php
$builder->add('warnings', ContentWarningsType::class);
$builder->add('warnings', ContentWarningsType::class, ['expanded' => false, 'locale' => 'fr']);
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `expanded` | `true` | Des cases à cocher ; `false` donne une liste à choix multiples avec un groupe d'options par catégorie |
| `locale` | la locale courante | La locale des libellés |
| `label` | `form.warnings` | Traduit dans le domaine `phil_content_warnings` |

Le champ est toujours à choix multiples. Ses choix sont les thèmes regroupés par catégorie, libellés dans la locale et
les locales de repli. Un avertissement conservé garde sa sévérité et sa note, et un avertissement dont le catalogue n'a
plus le thème est conservé tel quel : le champ ajoute et retire seulement les avertissements des thèmes qu'il énumère.
Une sévérité ou une note est définie par les propres champs ou le propre code de l'application, avec
`ContentWarnings::with()`.

Le thème de formulaire `@PhilContentWarnings/form/theme.html.twig`, ajouté aux thèmes de formulaire de Twig, produit le
rendu des cases à cocher de chaque catégorie dans un `fieldset` dont la `legend` est le libellé de la catégorie.

## Choisir les préférences

`WarningPreferencesType` est le formulaire où un lecteur choisit ce qui arrive à un contenu visé par un avertissement.
Ses données sont un `WarningPreferences`.

```php
$form = $this->createForm(WarningPreferencesType::class, $store->current());
// on a valid submission
$store->save($form->getData());
```

| Enfant | Ce qu'il contient |
|---|---|
| `default` | L'affichage de tout avertissement qu'aucun autre choix ne couvre |
| `categories` | Un affichage par catégorie, vide pour suivre la valeur par défaut |
| `themes` | Un champ composé par catégorie, avec un affichage par thème, vide pour suivre sa catégorie |

| Option | Valeur par défaut | Signification |
|---|---|---|
| `themes` | `true` | `false` laisse de côté les choix par thème |
| `locale` | la locale courante | La locale des libellés des catégories et des thèmes |

Les affichages sont libellés `display.show`, `display.fold` et `display.hide`, et le choix vide `preferences.inherit`,
dans le domaine `phil_content_warnings`. Une préférence dont l'affichage est conservé garde la sévérité à partir de
laquelle elle s'appliquait ; les préférences des thèmes et des catégories que le formulaire n'énumère pas sont
conservées telles quelles.
