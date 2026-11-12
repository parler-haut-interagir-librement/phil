<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Rendering;

use LogicException;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateResolver::class)]
final class TemplateResolverTest extends TestCase
{
    public function testADeclaredKeyNamesItsTemplate(): void
    {
        self::assertSame('content/news.html.twig', new TemplateResolver(['news' => 'content/news.html.twig'], 'content/default.html.twig')->resolve('news'));
    }

    public function testAnUndeclaredOrAbsentKeyFallsToTheDefault(): void
    {
        $resolver = new TemplateResolver(['news' => 'content/news.html.twig'], 'content/default.html.twig');

        self::assertSame('content/default.html.twig', $resolver->resolve('page'));
        self::assertSame('content/default.html.twig', $resolver->resolve(null));
    }

    public function testWithoutADefaultAnUndeclaredKeyIsAMistakeOfConfiguration(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('No template is declared for the template key "page" under "phil_content.templates", and no "phil_content.default_template".');

        new TemplateResolver(['news' => 'content/news.html.twig'])->resolve('page');
    }
}
