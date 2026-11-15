<!-- phil-dev source-sha256=fc171daabd03fa4d54e704706674735118781c7a5b1e8c7dd7b8a8f10c6f6915 -->
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

## Messages et traductions

Les violations portent leurs nombres sous forme d'entiers — `{{ line }}` de chaque retrait et `{{ count }}` des
retraits au-delà des 100 premiers — et les autres paramètres sous forme de chaînes. Les messages par défaut sont les
clés de traduction, dans le domaine `validators`.

La bibliothèque fournit des catalogues en ICU MessageFormat, dans le domaine `validators+intl-icu`, dans
`translations/` :

| Locale | Messages |
|---|---|
| `en` | `moreMessage`, dont le pluriel suit `{{ count }}`. |
| `fr` | Chaque message par défaut : ce qui a été retiré suit `{{ reason }}`, et le pluriel de `moreMessage` suit `{{ count }}`. |

```text
This CSS holds {{ count }} more parts that are not allowed.
→ {count, plural, one {This CSS holds # more part that is not allowed.} other {This CSS holds # more parts that are not allowed.}}
```

`{{ removed }}` est `RemovalReason::describe()`, en anglais : le catalogue français nomme plutôt chaque raison à partir
de `{{ reason }}`, et se rabat sur `{{ removed }}` pour une raison qu'il ne connaît pas.

Les catalogues sont lus dès que l'application donne leur répertoire au traducteur de FrameworkBundle
([Installation](installation.md#traductions)) ; le domaine ICU a besoin de `MessageFormatter`, de `ext-intl` ou de
`symfony/polyfill-intl-messageformatter`. Sans eux, les messages restent tels qu'écrits. Le répertoire `translations/`
de l'application vient après celui de la bibliothèque : un message qu'elle y traduit, dans le domaine `validators` ou
`validators+intl-icu`, remplace celui de la bibliothèque. Un message donné à la contrainte (`moreMessage: '…'`) est
traduit comme n'importe quelle clé du domaine ; écrit en ICU, il appartient à un catalogue ICU de l'application.
