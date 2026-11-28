<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;

/**
 * What turns the sources of a template into its parts, in a sandbox: only the tags, filters, functions, tests, methods
 * and properties of its allow-list, nothing read from outside the template.
 */
interface TemplateEngine
{
    /**
     * @param array<string, mixed> $variables the values, already checked against the schema
     *
     * @throws RenderingFailed when a source fails at run time, or the sandbox refuses what it calls
     */
    public function render(TemplateDefinition $definition, array $variables, RenderContext $context): RenderedParts;

    /**
     * What is wrong with the sources without rendering them: a syntax error, what the sandbox refuses, a limit
     * exceeded, a variable the schema does not declare.
     *
     * @return list<string> the reasons, none when the sources compile
     */
    public function check(TemplateDefinition $definition): array;
}
