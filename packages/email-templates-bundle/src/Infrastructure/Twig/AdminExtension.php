<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `phil_email_templates_kit('Button')`: the name of a recipe of the kit, as the pages of the administration call it.
 */
final class AdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly KitComponents $kit,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [new TwigFunction('phil_email_templates_kit', $this->kit->name(...))];
    }
}
