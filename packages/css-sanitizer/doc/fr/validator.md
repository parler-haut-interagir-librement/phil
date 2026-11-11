<!-- phil-dev source-sha256=66f627bb70e8a8c04dfcb9a408b5a62315b8eec34de077583cf4ffa42e3dc669 -->
# Validateur

`Validator\SafeCss` est une contrainte pour `symfony/validator` : la valeur, une chaîne ou un `Stringable`, doit être
du CSS que l'assainisseur garde en entier. Chaque retrait listé est une violation, et les retraits au-delà des 100
premiers en font une de plus. `null` et la chaîne vide sont valides.

```php
use Phil\CssSanitizer\Validator\SafeCss;

final class Theme
{
    #[SafeCss]
    public string $styleSheet = '';

    #[SafeCss(declarations: true)]
    public string $titleStyle = '';
}
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `declarations` | `false` | Vérifier le contenu d'un attribut `style` plutôt qu'une feuille de style |
| `message` | `This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.` | Le message de chaque violation |
| `moreMessage` | `This CSS holds {{ count }} more parts that are not allowed.` | Le message des retraits au-delà des 100 premiers |

Les paramètres de chaque violation sont `{{ removed }}` (`RemovalReason::describe()`), `{{ subject }}`, `{{ line }}`
et `{{ reason }}` (la valeur de la raison, comme `import`). La violation des retraits au-delà des 100 premiers a
`{{ count }}` pour seul paramètre et le même nombre pour son pluriel : une source d'un mégaoctet donne au plus 101
violations. Le code est `SafeCss::NOT_ALLOWED_ERROR`.

La politique est celle du `CssSanitizer` donné à `SafeCssValidator`. Sans déclaration de service, la fabrique du
validateur le construit avec la politique par défaut, qui refuse toutes les URL. Déclarez-le comme service pour
utiliser la politique de l'application, comme le montre [Installation](installation.md).

Un formulaire peut valider avec `SafeCss` et enregistrer le CSS assaini. Il peut aussi se passer de la contrainte,
enregistrer ce que renvoie `sanitize()` et dire à l'auteur ce qui a été retiré.
