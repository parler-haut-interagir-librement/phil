<!-- phil-dev source-sha256=d3f74292405c23351736a1530c94536d25997b16f325ffce01bff0ddfc8cdb73 -->
# Twig

```twig
<select name="status">
    {% for status in enum_cases('App\\OrderStatus', {group: 'customer'}) %}
        <option value="{{ status.value }}" title="{{ status|enum_description }}">{{ status|enum_label }}</option>
    {% endfor %}
</select>

{{ status|enum_meta('App\\Color') }}
{{ status|enum_badge }}
```

| Nom | Genre | Retourne |
|---|---|---|
| `enum_cases(enum, options = {})` | fonction | Les cas de l'enum sous forme de `CaseCollection`, restreints par `only`, `except`, `group` et `where_meta`, les options du champ de formulaire ([Formulaires](forms.md)). |
| `enum_label` | filtre | Le libellé traduit lorsque l'enum est traduisible et qu'un traducteur est présent ; sinon le `Label` du cas, ou son nom. |
| `enum_description` | filtre | La description traduite dans le même cas de figure ; sinon la `Description` du cas ; `null` s'il n'en a pas. |
| `enum_meta(metaProperty)` | filtre | La valeur d'une méta-propriété sur le cas, ou sa valeur par défaut. |
| `enum_badge` | filtre | Le cas sous forme de badge : son libellé, avec sa `Color` et son `Icon`, rendu par `@PhilEnums/badge.html.twig`. |

Les fonctions et les filtres sont enregistrés comme extension à attributs et runtime de Twig ; le runtime reçoit le
service `translator` si l'application en a un.

## Badges

`enum_badge` affiche un cas comme un statut s'affiche dans une liste : son libellé, traduit comme le fait `enum_label`,
dans la couleur et avec l'icône que porte le cas. Ce sont deux méta-propriétés fournies avec le bundle :

```php
use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Application\Presentation\Icon;

enum OrderStatus: string
{
    #[Label('Pending')] #[Color('warning')] #[Icon('clock')]
    case PENDING = 'pending';

    #[Label('Delivered')] #[Color('success')] #[Icon('check')]
    case DELIVERED = 'delivered';
}
```

```html
<span class="enum-badge enum-badge--warning" data-enum-case="PENDING"><span class="enum-badge__icon" data-icon="clock" aria-hidden="true"></span>Pending</span>
```

- **Une couleur et une icône sont des noms**, jamais des valeurs CSS ni du balisage : `success`, `clock`. La feuille de
  style et le jeu d'icônes de l'application leur donnent une apparence, et un thème change donc sans toucher à une enum.
- **L'application peut remplacer le gabarit** : copiez-le dans `templates/bundles/PhilEnumsBundle/badge.html.twig` et
  écrivez le balisage de votre système de design, que ce soit un badge Bootstrap, une pastille Tailwind ou un composant
  Twig. Il reçoit `case`, `label`, ainsi que `color` et `icon`, chacun valant `null` lorsque le cas n'en porte pas.
- **La sortie est du HTML** : le gabarit échappe ce qu'il affiche, et le filtre est marqué sûr.

Le badge exige TwigBundle, qui enregistre le répertoire `templates/` du bundle sous `@PhilEnums`.
