<!-- phil-dev source-sha256=a7d68ae2699197954fa2e271dece9e874921bb18f110be75818db64ae51b048a -->
# Points d'extension

## La politique d'accès

Les pages demandent `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` et `PHIL_AUDIT_EXPORT`; `AuditViewerVoter` les accorde à
des rôles. Une application qui en refuse certains aux titulaires de ces rôles — des exports hors des heures de bureau,
l'historique d'une commande au gestionnaire d'une autre boutique — implémente
`Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy` et la nomme dans `access_policy`. Le *voter* la
interroge une fois qu'un rôle est détenu, avec le sujet avec lequel l'attribut est demandé :

| Attribut | Sujet | Demandé pour |
|---|---|---|
| `PHIL_AUDIT_VIEW` | `null` | la page du journal, la page de la chaîne |
| `PHIL_AUDIT_VIEW` | `AuditRow` | la page d'une ligne, et chaque ligne que la page du journal liste |
| `PHIL_AUDIT_VIEW` | le nom du segment | la vérification d'un segment, et chaque segment que la page de la chaîne liste |
| `PHIL_AUDIT_HISTORY` | `Subject` | l'historique d'un enregistrement, et son action « Historique » |
| `PHIL_AUDIT_EXPORT` | `null` | l'export dans son ensemble, et son bouton sur la page du journal |
| `PHIL_AUDIT_EXPORT` | `AuditRow` | chaque ligne que l'export lit |

Ce que la politique refuse, le *voter* le refuse. Une page demandée avec `null` est refusée en entier; une ligne ou un
segment refusé dans une liste en est retiré, de sorte qu'une page du journal peut contenir moins de lignes que
`page_size` et garder son lien vers la suivante, et l'export lit au plus `export_limit` lignes, en écrivant celles qui
sont permises. Une politique qui décide par site répond à l'`AuditRow` par son locataire (`$row->facts->tenant`) et au
segment par `SiteSegment::belongsTo()` :

```php
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final readonly class ShopAuditPolicy implements AuditAccessPolicy
{
    public function __construct(private Orders $orders) {}

    public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (AuditViewerVoter::HISTORY !== $attribute || !$subject instanceof Subject || 'order' !== $subject->type) {
            return true;
        }

        $user = $token->getUser();

        return $user instanceof ShopManager && $this->orders->shopOf($subject->id) === $user->shop();
    }
}
```

```php
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\SiteSegment;

final readonly class SiteAuditPolicy implements AuditAccessPolicy
{
    public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof SiteAuditor) {
            return false;
        }

        return match (true) {
            $subject instanceof AuditRow => $subject->facts->tenant === $user->site(),
            \is_string($subject) => SiteSegment::belongsTo($subject, $user->site()),
            default => true,
        };
    }
}
```

L'exemple de l'historique ci-dessus, configuré :

```yaml
phil_audit_viewer:
    history_role: ROLE_SHOP_MANAGER
    access_policy: App\Audit\ShopAuditPolicy
```

La politique restreint sous toutes les stratégies de décision d'accès, parce que le *voter* la consulte avant
d'accorder. Un *voter* de l'application qui refuse ne le fait pas : sous `affirmative`, la valeur par défaut de Symfony,
il suffit qu'un *voter* accorde l'accès, et `AuditViewerVoter` accorde au rôle. Restreignez par la politique, jamais par
un second *voter*.

## Vos propres *voters*

Un *voter* de l'application pour les mêmes attributs élargit l'accès sous `affirmative` : il accorde ce que les rôles
n'accordent pas, comme les exports à un responsable de la conformité qui ne détient aucun rôle du lecteur. Sous
`consensus`, la majorité décide, et sous `unanimous`, `AuditViewerVoter` refuse ce que le rôle n'a pas : un tel *voter*
n'accorde rien de plus à cet endroit.

```php
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;

/** @extends Voter<string, mixed> */
final class ComplianceExportVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return AuditViewerVoter::EXPORT === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof ComplianceOfficer;
    }
}
```

## Le lecteur de charge utile

La charge utile d'une ligne, ce sont les octets du `CanonicalSerializer` de l'application. Quand l'application fait de
`Phil\AuditBundle\Application\Contract\PayloadReader` l'alias d'un service qui les relit, les pages d'une ligne et d'un
historique montrent ses champs; sans lui, ou pour une version qu'il ne lit pas (`null`), elles montrent les octets tels
qu'ils sont stockés. Le port est celui de `phil/audit-bundle`, décrit dans son `doc/extension-points.md`.

## L'acteur d'un export

L'export nomme son acteur par l'`ActorResolver` de `phil/audit-bundle`. Avec SecurityBundle, c'est l'`auditActorId()`
de l'utilisateur quand sa classe implémente `AuditActor`; une application fait d'`ActorResolver` l'alias de son propre
service pour en décider autrement.

## L'action Historique

`AuditHistoryActions` est une extension d'actions d'EasyAdmin; désactivez-la avec `history_action: false`, ou gardez-la
et donnez à un contrôleur une action qui lui est propre sous le même nom, `AuditHistoryActions::NAME`, que l'extension
laisse alors tranquille ([history-action.md](history-action.md)).

## Les gabarits

Chaque page étend `@EasyAdmin/page/content.html.twig` : la mise en page, le menu et le thème du tableau de bord
s'appliquent donc. Pour en changer un, surchargez-le dans `templates/bundles/PhilAuditViewerBundle/` :

| Gabarit | Page | Variables |
|---|---|---|
| `log.html.twig` | Le journal | `filters` (`AuditFilters`), `page` (`AuditPage` ou `null`), `timezone`, `exportable`, `csrf_id` |
| `row.html.twig` | Une ligne | `row` (`AuditRow`), `fields` (tableau ou `null`), `timezone` |
| `history.html.twig` | Un historique | `subject` (`Subject`), `page`, `fields` (un par ligne), `timezone`, `viewer` (si le journal peut aussi être lu) |
| `chain.html.twig` | La chaîne | `sites` (des listes de `SegmentStatus` par locataire, `''` pour les segments partagés, en premier), `timezone` |
| `segment.html.twig` | Un segment | `status` (`SegmentStatus`, vérifié), `timezone` |
| `_rows.html.twig`, `_fields.html.twig` | Le tableau des lignes, une charge utile | `rows`; `fields` et `row` |

## Les traductions

Les mots sont dans le domaine `phil_audit_viewer`, en anglais et en français. Une application les traduit dans une
autre langue, ou les change, avec un fichier `translations/phil_audit_viewer.<locale>` qui lui est propre.
