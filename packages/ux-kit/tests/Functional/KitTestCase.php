<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Override;
use Phil\UxKit\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Renders a template through TwigComponent with the kit's recipes installed, the whitespace between tags removed.
 */
abstract class KitTestCase extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(FrameworkBundle::class)) {
            self::markTestSkipped('FrameworkBundle is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function render(string $template, array $context = []): string
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return (string) preg_replace(['/>\s+</', '/\s+/'], ['><', ' '], trim($twig->createTemplate($template)->render($context)));
    }
}
