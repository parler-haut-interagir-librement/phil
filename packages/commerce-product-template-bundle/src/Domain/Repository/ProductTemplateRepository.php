<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Repository;

use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;

/**
 * Finds and stores product templates. `Infrastructure\Doctrine\DoctrineProductTemplateRepository` is the Doctrine
 * adapter.
 */
interface ProductTemplateRepository
{
    public function find(string $code): ?ProductTemplate;

    public function add(ProductTemplate $template): void;
}
