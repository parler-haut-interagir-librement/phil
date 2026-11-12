<!-- phil-dev source-sha256=e7819cb981225cf5b8ecba92852483176a63afd1204c1fadbaade1bc4225f26c -->
# Validateur

`Validator\AccessibleContent` est une contrainte de `symfony/validator` : la valeur, du HTML dans une chaîne ou un
`Stringable`, ou un `Document` UJO, est accessible. `null` et `''` sont valides, comme pour toute contrainte sauf
`NotBlank`. Toute autre valeur lève `UnexpectedValueException`.

```php
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\Validator\AccessibleContent;

final class ArticleInput
{
    #[AccessibleContent]
    public ?string $body = null;

    #[AccessibleContent(minSeverity: Severity::Warning, language: 'fr', locale: 'fr')]
    public ?Document $content = null;
}
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `minSeverity` | `Severity::Error` | les constats qui deviennent des violations : cette sévérité et les plus graves |
| `language` | `null` | la langue d'un contenu qui n'indique pas la sienne ; celle de la politique si `null` |
| `locale` | `null` | la locale des messages ; celle du validateur si `null` |
| `message` | `{{ message }}` | le message d'une violation |
| `refusedMessage` | `This content cannot be checked for accessibility: {{ reason }}` | le message d'un contenu refusé |

Chaque constat est une violation de code `AccessibleContent::INACCESSIBLE_ERROR`, avec les paramètres `{{ message }}` —
le message du constat —, `{{ rule }}`, `{{ criterion }}`, `{{ severity }}` et `{{ location }}`. Un contenu que le
vérificateur refuse — trop grand, pas en UTF-8 — est une violation de code `REFUSED_ERROR` avec `{{ reason }}`.

`AccessibleContentValidator` reçoit l'`AccessibilityChecker` avec lequel il vérifie, et la locale des messages :
enregistrez-le comme service, étiqueté `validator.constraint_validator`, pour que la contrainte suive la politique de
l'application ([Installation](installation.md#dans-une-application-symfony)). Sans cette déclaration, le validateur est
construit avec la politique par défaut et rédige en anglais.
