<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;

/**
 * `ProductTemplateRepository` on the Doctrine ORM: a template is one row of `phil_commerce_product_template`.
 */
final readonly class DoctrineProductTemplateRepository implements ProductTemplateRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?ProductTemplate
    {
        return $this->entityManager->find(ProductTemplate::class, $code);
    }

    #[Override]
    public function add(ProductTemplate $template): void
    {
        $this->entityManager->persist($template);
    }
}
