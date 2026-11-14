<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Phil\AuditViewer\Tests\Functional\App\Entity\Note;

/**
 * The notes of the back office, which are not audited.
 *
 * @extends AbstractCrudController<Note>
 */
final class NoteCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Note::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('id');
    }
}
