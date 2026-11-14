<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Attribute;

use Attribute;

/**
 * Asks for the active tenant as a controller argument typed `TenantId`:
 *
 * ```php
 * public function invoices(#[CurrentTenant] TenantId $tenant): Response
 * ```
 *
 * With no tenant active, a nullable argument receives `null` and any other is a 403.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentTenant
{
}
