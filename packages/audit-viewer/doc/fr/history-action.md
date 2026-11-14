<!-- phil-dev source-sha256=17fe2fa8ddf7c2bdf1ce9b20fd575144bd894b72ae0f6170b798e52edce9415a -->
# L'action Historique

Chaque contrôleur CRUD d'un tableau de bord EasyAdmin dont l'entité implémente
`Phil\AuditBundle\Application\Contract\Auditable` reçoit une action « Historique », sur sa page de détail et sur chaque
ligne de son index. Elle mène à la page d'historique de l'enregistrement que nomme l'entité, sous le même tableau de
bord : `admin_audit_history` avec le `type` et l'`id` d'`auditSubject()`. Rien ne se configure par contrôleur.

```php
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;

#[ORM\Entity]
class Order implements Auditable
{
    public function auditSubject(): Subject
    {
        return Subject::of('order', (string) $this->id);
    }
}
```

La même interface permet à `phil/audit-bundle` de consigner les transitions du workflow de la commande au sujet de ce
`Subject` : l'historique que l'action ouvre les contient donc, à côté de tout autre événement consigné au sujet de la
commande. La piste est écrite par l'application — par ses écouteurs Doctrine, ses workflows, ses propres appels à
`AuditTrail::record()` — ; l'action ne fait que la lire.

## Qui la voit

L'action n'est montrée qu'à qui se voit accorder `PHIL_AUDIT_HISTORY` sur l'enregistrement, demandé avec son `Subject`.
Par défaut, ce sont les titulaires du rôle du lecteur et de `history_role` :

```yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER      # the whole log
    history_role: ROLE_ADMIN     # the history of each record, from its CRUD pages
```

Un titulaire de `history_role` seul ouvre l'historique des enregistrements sur lesquels l'action est montrée, et rien
d'autre du journal : les lignes de l'historique ne sont pas des liens vers leurs pages, et le lien vers le journal
filtré n'est pas montré. L'`AuditAccessPolicy` de l'application le restreint davantage, enregistrement par
enregistrement, quelle que soit la stratégie de décision d'accès ([extension-points.md](extension-points.md)).

## La désactiver, ou la remplacer

```yaml
phil_audit_viewer:
    history_action: false
```

Pour la garder partout sauf sur un contrôleur, ou pour la libeller autrement à cet endroit, donnez à ce contrôleur une
action qui lui est propre sous le même nom : l'extension n'ajoute son action à une page que si le contrôleur n'en a
aucune sous ce nom.

```php
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;

public function configureActions(Actions $actions): Actions
{
    return $actions->add(Crud::PAGE_DETAIL, Action::new(AuditHistoryActions::NAME, 'Changes')->linkToUrl(...));
}
```

L'action se nomme `phil_audit_history`, son libellé est `action.history` dans le domaine `phil_audit_viewer`, son icône
`fa fa-clock-rotate-left`; son lien porte la classe CSS `action-phil_audit_history` qu'EasyAdmin donne à chaque action.
