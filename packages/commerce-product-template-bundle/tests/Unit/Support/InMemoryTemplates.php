<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;

final class InMemoryTemplates implements ProductTemplateRepository
{
    /** @var array<string, ProductTemplate> */
    private array $templates = [];

    public function __construct(ProductTemplate ...$templates)
    {
        foreach ($templates as $template) {
            $this->add($template);
        }
    }

    #[Override]
    public function find(string $code): ?ProductTemplate
    {
        return $this->templates[$code] ?? null;
    }

    #[Override]
    public function add(ProductTemplate $template): void
    {
        $this->templates[$template->code] = $template;
    }
}
