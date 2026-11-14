<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;
use Phil\AuditViewer\Tests\Functional\App\Entity\ShopOrder;

/**
 * The orders of the back office, their detail page shown; the index has an action of its own by the name of the
 * "History" action, which it keeps.
 *
 * @extends AbstractCrudController<ShopOrder>
 */
final class ShopOrderCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return ShopOrder::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::new(AuditHistoryActions::NAME, 'Changes')->linkToUrl('/changes'));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('id');
        yield TextField::new('label');
    }
}
