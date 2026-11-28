<!-- phil-dev source-sha256=f97dc076d1a56d8368df016b08ec7c3b80df91634ebacf294b1be8310ea88328 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter une vérification | Implémentez `Phil\EmailPreview\Contract\Rule` et donnez-la à `EmailChecker` avec les règles par défaut. |
| Changer une limite | Donnez `new MaxWidth(480)` ou `new SubjectLength(70)` à la place de la règle par défaut. |
| Retirer une règle | Donnez à `EmailChecker` les règles que vous gardez. |
| Vérifier les aperçus avec vos propres données | Donnez à `PreviewChecks` votre propre `PreviewRenderer` de phil/email-templates-bundle. |

## Écrire une règle

Une règle a un nom en kebab case, que porte chacun de ses constats, et produit ce qu'elle trouve dans l'ordre du
courriel. Elle lit le HTML par `CheckedEmail::$document`, analysé une fois pour toutes les règles, jamais par des
expressions régulières sur le HTML. Elle ne garde aucun état entre deux courriels : un seul vérificateur sert toutes les
requêtes d'un *worker*.

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

final readonly class ImageAlt implements Rule
{
    public function name(): string
    {
        return 'image-alt';
    }

    public function check(CheckedEmail $email): iterable
    {
        foreach ($email->document->getElementsByTagName('img') as $image) {
            if (!$image->hasAttribute('alt')) {
                yield Finding::error('image-alt', 'An image has no alternative text.', $image->getAttribute('src'));
            }
        }
    }
}
```

Le vérificateur exécute les règles dans l'ordre où il les reçoit, qui est l'ordre du rapport :

```php
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Rule\DefaultRules;

$checker = new EmailChecker([...DefaultRules::all(), new ImageAlt()]);
```

Dans une application Symfony, étiquetez les règles et donnez au vérificateur l'itérateur des services étiquetés :

```yaml
services:
    _instanceof:
        Phil\EmailPreview\Contract\Rule:
            tags: ['app.email_rule']

    Phil\EmailPreview\EmailChecker:
        arguments:
            $rules: !tagged_iterator app.email_rule
```

Une règle qui a besoin de ce que le rendu ne dit pas — un en-tête, si le courriel part en masse, les valeurs du
destinataire — le lit dans `CheckedEmail::$sending`.
