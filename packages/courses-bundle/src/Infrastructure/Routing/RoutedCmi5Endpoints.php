<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Routing;

use Override;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Infrastructure\Http\Cmi5Controller;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The xAPI endpoint and the fetch URLs, absolute, from the routes of `config/cmi5_routes.php`.
 */
final readonly class RoutedCmi5Endpoints implements Cmi5Endpoints
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function endpoint(): string
    {
        return $this->urls->generate(Cmi5Controller::ENDPOINT_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Override]
    public function fetch(LaunchSessionId $session): string
    {
        return $this->urls->generate(Cmi5Controller::FETCH_ROUTE, ['session' => $session->value], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
