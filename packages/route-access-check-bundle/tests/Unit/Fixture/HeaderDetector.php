<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit\Fixture;

use Override;
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\RuleScope;

/**
 * A detector of the application's own: the routes whose option `signed` is true are protected by a signature.
 */
final readonly class HeaderDetector implements AccessRuleDetector
{
    #[Override]
    public function detect(AnalysedRoute $route): iterable
    {
        if (true === $route->route->getOption('signed')) {
            yield new AccessRule('signature', AccessEffect::Protected, RuleScope::Route, 'signed URL');
        }
    }
}
