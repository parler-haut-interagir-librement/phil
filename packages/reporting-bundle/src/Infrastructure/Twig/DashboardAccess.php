<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Twig;

use Phil\ReportingBundle\Application\Dashboard\Dashboard;
use Phil\ReportingBundle\Application\Dashboard\Dashboards;
use Phil\ReportingBundle\Application\Exception\AccessDenied;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * A dashboard given to the viewer only when it names no role, or the viewer has its role; a dashboard that names a
 * role is refused when no authorization checker is there to ask. Asked at every rendering, a live one's included.
 */
final readonly class DashboardAccess
{
    public function __construct(
        private Dashboards $dashboards,
        private ?AuthorizationCheckerInterface $authorization = null,
    ) {
    }

    /**
     * @throws AccessDenied
     */
    public function get(string $name): Dashboard
    {
        $dashboard = $this->dashboards->get($name);

        if (null !== $dashboard->role && true !== $this->authorization?->isGranted($dashboard->role)) {
            throw AccessDenied::to($name);
        }

        return $dashboard;
    }
}
