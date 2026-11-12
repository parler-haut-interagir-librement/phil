<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Override;
use Phil\UxKit\Tests\Functional\App\TestKernel;
use Psr\Link\LinkInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;
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

    /**
     * Renders a template while a request of https://www.example.org is handled, and returns the links WebLink would
     * send in its header: each relation, address and attributes.
     *
     * @param array<string, mixed> $context
     *
     * @return array{string, list<array{array<string>, string, array<mixed>}>}
     */
    protected function renderInRequest(string $template, array $context = []): array
    {
        $requests = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $request = Request::create('https://www.example.org/page');
        $requests->push($request);

        try {
            $html = $this->render($template, $context);
        } finally {
            $requests->pop();
        }

        $provider = $request->attributes->get('_links');
        $links = [];

        foreach ($provider instanceof GenericLinkProvider ? $provider->getLinks() : [] as $link) {
            if ($link instanceof LinkInterface) {
                $links[] = [$link->getRels(), $link->getHref(), $link->getAttributes()];
            }
        }

        return [$html, $links];
    }
}
