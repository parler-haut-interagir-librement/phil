<!-- phil-dev source-sha256=ef447c772c0ba6be7316db3ac5f3f193ff654c634ceae4dc662ca0c1a31f20fd -->
# Validateur

`Validator\SafeCss` est une contrainte pour `symfony/validator` : la valeur, une chaîne ou un `Stringable`, doit être
du CSS que l'assainisseur garde en entier. Chaque retrait est une violation. `null` et la chaîne vide sont valides.

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

Les paramètres de chaque violation sont `{{ removed }}` (`RemovalReason::describe()`), `{{ subject }}`, `{{ line }}`
et `{{ reason }}` (la valeur de la raison, comme `import`). Le code est `SafeCss::NOT_ALLOWED_ERROR`.

La politique est celle du `CssSanitizer` donné à `SafeCssValidator`. Sans déclaration de service, la fabrique du
validateur le construit avec la politique par défaut, qui refuse toutes les URL. Déclarez-le comme service pour
utiliser la politique de l'application, comme le montre [Installation](installation.md).

Un formulaire peut valider avec `SafeCss` et enregistrer le CSS assaini. Il peut aussi se passer de la contrainte,
enregistrer ce que renvoie `sanitize()` et dire à l'auteur ce qui a été retiré.
