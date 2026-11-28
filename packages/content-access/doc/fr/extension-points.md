<!-- phil-dev source-sha256=4fd72c106646ec91a9439f04ddebe605838c7effe37091b930174969b0ad75ed -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Donner les droits d'une personne | Implémentez `Entitlement\Entitlements` ; joignez-en plusieurs avec `Entitlement\ChainEntitlements` |
| Laisser une règle lire les faits de l'application | Passez-les en troisième argument de `AccessDecider::decide()` |
| Ajouter une condition qu'une règle peut nommer | Implémentez `Phil\Rules\Type\ConditionType`, et passez le registre à `AccessConditions::types()` |
| Marquer la coupure d'un document avec un autre type de bloc | Passez-le à `PreviewCut` |
| Stocker des politiques | Utilisez `Json\AccessPolicyReader` et `Json\AccessPolicyWriter` |

Aucune implémentation ne garde quoi que ce soit d'une requête à l'autre, pour qu'une seule instance serve toutes les
requêtes d'un *worker*.

## Une source de droits

Une source répond les droits d'une personne selon son identifiant stable, jamais selon une adresse courriel. Elle peut
retourner des droits échus : la bibliothèque garde ceux qui sont actifs à l'instant de la décision.

```php
use Phil\ContentAccess\Entitlement\Entitlements;
use Phil\ContentAccess\Entitlement\Grant;

final readonly class SubscriptionEntitlements implements Entitlements
{
    public function __construct(private Subscriptions $subscriptions)
    {
    }

    public function grantsOf(string $subject): array
    {
        $grants = [];

        foreach ($this->subscriptions->activeFor($subject) as $subscription) {
            foreach ($subscription->features() as $feature) {
                $grants[] = new Grant($feature, $subscription->startedAt(), $subscription->paidUntil(), 'subscription');
            }
        }

        return $grants;
    }
}
```

Un `Grant` contient un nom de fonctionnalité et une source facultative — des identifiants : une lettre ou un chiffre,
puis des lettres, des chiffres, `_`, `.`, `:`, `/` ou `-`, 128 octets au plus —, un début, et une fin facultative
postérieure au début ; `InvalidGrant` refuse tout le reste.

Le décideur ne lit une source que pour un lecteur connecté, et seulement quand une condition demande une
fonctionnalité. Une source qui lit une base de données le fait une fois par décision ; une application qui décide d'une
liste de contenus pour un même lecteur enveloppe sa source pour lire les droits une seule fois pour toute la liste.

## Les faits de l'application

```php
$decider->decide($policy, $viewer, [
    'lesson.done' => static fn (): bool => $progress->completed($viewer->id, 'lesson-2'),
]);
```

Une règle les lit avec les comparaisons génériques de `phil/rules`. Les noms des [faits de la bibliothèque](conditions.md)
sont réservés.

## Les lecteurs

`Viewer::anonymous($ip)` et `Viewer::member($id, $roles, $verifiedEmailDomain, $ip)` construisent le lecteur ;
`InvalidViewer` refuse un identifiant vide, un identifiant de plus de 255 octets ou contenant un caractère de
contrôle, plus de 100 rôles, un rôle qui n'est pas un identifiant, un domaine qui n'est pas un nom d'hôte et une
adresse qui n'est pas une adresse IP, sans répéter la valeur dans son message.
