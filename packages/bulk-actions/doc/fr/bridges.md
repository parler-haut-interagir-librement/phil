<!-- phil-dev source-sha256=51429f62a3d7e56db35372ee335bea2ae61b87046d7ab921a787aa9cd05a1494 -->
# Passerelles

`Bridge\ExternalSelection` construit une sélection à partir de ce qu'un autre outil lui remet. Aucun de ces outils
n'est une dépendance.

## Tables de données

Une action groupée d'une table de données, comme celles de `phil/data-table-bundle`, reçoit les lignes relues depuis sa
source de données, après sa propre confirmation par `<dialog>`, sa vérification CSRF et son voter sur chaque ligne. Le
*handler* lance directement une exécution ; les versions que les lignes ont maintenant sont conservées, et
l'exécuteur les vérifie de nouveau.

```php
use Phil\BulkActions\Bridge\ExternalSelection;
use Phil\BulkActions\BulkConfirmer;
use Phil\BulkActions\Change\ChangeSet;
use Phil\DataTableBundle\Application\Definition\Action;

$table->action(Action::bulk('archive', 'products.archive', 'PRODUCT_ARCHIVE', function (array $rows): void {
    $this->confirmer->start(
        $this->security->getUser()->getUserIdentifier(),
        'products',
        'archive',
        $this->selections->fromRows('products', $rows),
        ChangeSet::none(),
        new DateTimeImmutable(),
    );
})->destructive());
```

`fromRows()` refuse une ligne qui n'est pas un objet de la classe de la cible, comme la ligne en tableau d'une requête.

## Actions groupées d'une administration

Une action groupée d'une administration, comme le `BatchActionDto` d'EasyAdmin, donne des identifiants et une classe
d'entité. La classe est comparée à celle de la cible — la même classe ou une sous-classe — et n'est jamais utilisée pour
trouver les éléments. Le contrôleur envoie ensuite la personne à l'aperçu.

```php
public function bulkEdit(BatchActionDto $batch, BulkSelector $selector, ExternalSelection $selections): Response
{
    $selection = $selections->fromBatch('products', $batch->getEntityFqcn(), $batch->getEntityIds());
    $token = $selector->select($this->getUser()->getUserIdentifier(), 'products', 'edit', $selection, new DateTimeImmutable());

    return $this->redirectToRoute('admin_bulk_preview', ['token' => $token->value]);
}
```

Le jeton CSRF de l'action groupée est vérifié par l'administration avant l'exécution du contrôleur.

## La corbeille

Une suppression groupée est une action dont `apply()` déplace l'élément à la corbeille, par `phil/trash-bundle` quand
l'application l'a, et qui dit `isDestructive()`. La bibliothèque n'offre aucune annulation par délai : la corbeille est
le chemin du retour.

## La piste d'audit

`Contract\AuditTrail` est informée de la confirmation, de la fin, de l'arrêt et de la nouvelle tentative de chaque
exécution, avec l'exécution : qui, quelle action, sur combien d'éléments. Un adaptateur vers `phil/audit-bundle` écrit
une entrée par événement ; voyez [Points d'extension](extension-points.md).
