<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Tests\Functional;

use Override;
use Phil\Typography\Typographer;
use Phil\TypographyBundle\Tests\Functional\App\TestKernel;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

#[CoversNothing]
final class TypographyBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testTheTemplatesAndUjoAreFixed(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('page.html.twig', [
            'body' => '"Oui" ! <i>',
            'html' => '<b>"Oui" !</b>',
            'title' => "L'été <i>",
            'document' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"\"Bonjour\" : voici..."}}]}',
        ]);

        self::assertSame("<main>«&nbsp;Oui&nbsp;»\u{202F}! &lt;i&gt;</main>\n<h1>L’été &lt;i&gt;</h1>\n<aside><b>«Oui» !</b></aside>\n<article><p class=\"ujo-paragraph\">«&nbsp;Bonjour&nbsp;»&nbsp;: voici…</p></article>\n", $html);
    }

    public function testTheFiltersFollowTheLocaleOfTheRequest(): void
    {
        $twig = $this->service('twig');
        $switcher = $this->service(LocaleSwitcher::class);
        self::assertInstanceOf(Environment::class, $twig);
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);

        $html = $switcher->runWithLocale('en', static fn (): string => $twig->render('page.html.twig', [
            'body' => '"Yes"',
            'html' => '"Ja"',
            'title' => '"Hi"',
            'document' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"\\"Hello\\""}}]}',
        ]));

        self::assertSame("<main>“Yes”</main>\n<h1>“Hi”</h1>\n<aside>«Ja»</aside>\n<article><p class=\"ujo-paragraph\">“Hello”</p></article>\n", $html);
    }

    public function testTheServices(): void
    {
        $ujo = $this->service(Ujo::class);
        self::assertInstanceOf(Ujo::class, $ujo);

        self::assertSame('<p class="ujo-paragraph">«&nbsp;a&nbsp;»</p>', $ujo->toHtml('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"\"a\""}}]}'));
        self::assertInstanceOf(Typographer::class, $this->service(Typographer::class));
        $swiss = $this->service('phil_typography.typographer.de_CH');
        self::assertInstanceOf(Typographer::class, $swiss);
        self::assertSame('«a»', $swiss->fixText('"a"'));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
