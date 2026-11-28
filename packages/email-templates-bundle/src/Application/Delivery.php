<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Symfony\Component\Mime\Address;

/**
 * Whom an email goes to and what it says, as the application gives it back for the identifier a message carried.
 */
final readonly class Delivery
{
    /**
     * @param non-empty-list<Address> $to
     * @param array<string, mixed>    $variables
     * @param list<string>            $segments
     */
    public function __construct(
        public array $to,
        public array $variables = [],
        public ?string $locale = null,
        public array $segments = [],
        public ?string $site = null,
    ) {
    }
}
