<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Attribute;

use Attribute;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;

/**
 * Changes the policy of the responses of a controller: on its class, then on its method, in that order.
 *
 * ```php
 * #[ContentSecurityPolicy(add: ['img-src' => ['https://images.example.com']])]
 * #[ContentSecurityPolicy(policy: 'preview')]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ContentSecurityPolicy
{
    /**
     * @param string|null                 $policy     a named policy enforced instead of the default one
     * @param array<string, list<string>> $add        sources added, by directive
     * @param array<string, list<string>> $replace    sources replacing those of the directive, by directive
     * @param list<string>                $remove     directives removed
     * @param bool|null                   $reportOnly true sends the policy as report-only, false enforces it
     */
    public function __construct(
        public ?string $policy = null,
        public array $add = [],
        public array $replace = [],
        public array $remove = [],
        public ?bool $reportOnly = null,
    ) {
    }

    public function toOverride(): PolicyOverride
    {
        return new PolicyOverride($this->policy, $this->add, $this->replace, $this->remove, $this->reportOnly);
    }
}
