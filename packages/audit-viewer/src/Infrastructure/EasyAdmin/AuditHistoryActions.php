<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use InvalidArgumentException;
use Override;
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A "History" action on the detail page and on each line of the index of every CRUD controller whose entity
 * implements `Auditable`: it links to the history page of the record the entity names, under the same dashboard, and
 * shows only to whoever is granted `PHIL_AUDIT_HISTORY` on that record. A controller that already has an action of
 * that name keeps its own. The trail itself is written by the application — by Doctrine, by a workflow —, never by
 * this action, which only reads.
 */
final readonly class AuditHistoryActions implements ActionsExtensionInterface
{
    public const string NAME = 'phil_audit_history';

    /** The pages the action is added to. */
    public const array PAGES = [Crud::PAGE_DETAIL, Crud::PAGE_INDEX];

    public function __construct(
        private UrlGeneratorInterface $urls,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[Override]
    public function supports(AdminContext $context): bool
    {
        return $context->getCrud() instanceof CrudDto && is_a($context->getEntity()->getFqcn(), Auditable::class, true);
    }

    #[Override]
    public function extend(Actions $actions, AdminContext $context): void
    {
        $route = $context->getDashboardRouteName().'_audit_history';

        foreach (self::PAGES as $page) {
            try {
                $actions->add($page, $this->action($route));
            } catch (InvalidArgumentException) {
                // The controller has an action of that name already: it is the controller's.
            }
        }
    }

    /**
     * The action, linked to the history page of the dashboard's route.
     */
    public function action(string $route): Action
    {
        return Action::new(self::NAME, new TranslatableMessage('action.history', [], 'phil_audit_viewer'), 'fa fa-clock-rotate-left')
            ->linkToUrl(fn (Auditable $entity): string => $this->urls->generate($route, ['type' => $entity->auditSubject()->type, 'id' => $entity->auditSubject()->id]))
            ->displayIf(fn (Auditable $entity): bool => $this->authorization->isGranted(AuditViewerVoter::HISTORY, $entity->auditSubject()));
    }
}
