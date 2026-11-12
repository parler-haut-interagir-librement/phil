<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Rendering;

use LogicException;

/**
 * The Twig template that renders a row, chosen by its template key (`phil_content.templates`), or the
 * default template for a row without a declared key.
 *
 * The templates are the application's. A key nobody declared, without a default, is a mistake of the
 * application's configuration, not of the request.
 */
final readonly class TemplateResolver
{
    /**
     * @param array<string, string> $templates template key => Twig template name
     */
    public function __construct(
        private array $templates,
        private ?string $default = null,
    ) {
    }

    /**
     * @throws LogicException when neither the key nor a default is declared
     */
    public function resolve(?string $templateKey): string
    {
        $template = null === $templateKey ? $this->default : ($this->templates[$templateKey] ?? $this->default);

        return $template ?? throw new LogicException(\sprintf('No template is declared for the template key "%s" under "phil_content.templates", and no "phil_content.default_template".', $templateKey ?? ''));
    }
}
