<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Functional;

use Override;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Infrastructure\BrowserSecurity\BrowserSecurityFraming;
use Phil\EmbedBundle\Infrastructure\Console\IssueTokenCommand;
use Phil\EmbedBundle\Infrastructure\DependencyInjection\FramingPass;
use Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener;
use Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController;
use Phil\EmbedBundle\Infrastructure\Http\HeaderFraming;
use Phil\EmbedBundle\Infrastructure\Twig\EmbedExtension;
use Phil\EmbedBundle\PhilEmbedBundle;
use Phil\EmbedBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bundle in an application, with its own header and through phil/browser-security-bundle: the page of an embed
 * answered for a valid token and framed by its origin alone, refused framing for a token that is not, untouched
 * without one; the script under its stable address; the code of an embed; the token command.
 */
#[CoversClass(PhilEmbedBundle::class)]
#[CoversClass(EmbedRequestListener::class)]
#[CoversClass(HeaderFraming::class)]
#[CoversClass(EmbedScriptController::class)]
#[CoversClass(EmbedExtension::class)]
#[CoversClass(BrowserSecurityFraming::class)]
#[CoversClass(IssueTokenCommand::class)]
#[CoversClass(FramingPass::class)]
final class EmbedBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testAValidTokenIsAnsweredAndFramedByItsOriginAlone(): void
    {
        $token = $this->issue('calendar', 'https://partner.example', 'dark', 'calendar/7');
        $response = $this->get('/embed/calendar/7?embed_token='.$token);
        $page = new Crawler((string) $response->getContent());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['frame-ancestors https://partner.example'], $response->headers->all('content-security-policy'));
        self::assertSame('calendar https://partner.example dark calendar/7', $page->filter('#context')->text());
        $html = $page->filter('html');
        self::assertSame(['phil--embed-bundle--frame', 'https://partner.example', '["light","dark"]', 'dark'], [$html->attr('data-controller'), $html->attr('data-phil--embed-bundle--frame-origin-value'), $html->attr('data-phil--embed-bundle--frame-themes-value'), $html->attr('data-theme')]);
    }

    public function testThePageAsksForAThemeTheEmbedHas(): void
    {
        $token = $this->issue('calendar', 'https://partner.example');

        self::assertSame('calendar https://partner.example light -', new Crawler((string) $this->get('/embed/calendar/7?embed_token='.$token)->getContent())->filter('#context')->text());
        self::assertSame('calendar https://partner.example dark -', new Crawler((string) $this->get('/embed/calendar/7?embed_token='.$token.'&embed_theme=dark')->getContent())->filter('#context')->text());
        self::assertSame('calendar https://partner.example light -', new Crawler((string) $this->get('/embed/calendar/7?embed_token='.$token.'&embed_theme[]=dark')->getContent())->filter('#context')->text());
    }

    public function testAControllerMarkedByItsAttribute(): void
    {
        $response = $this->get('/embed/form?embed_token='.$this->issue('form', 'https://anyone.example'));
        $page = new Crawler((string) $response->getContent());

        self::assertSame(['frame-ancestors https://anyone.example'], $response->headers->all('content-security-policy'));
        self::assertSame('form https://anyone.example - -', $page->filter('#context')->text());
        self::assertNull($page->filter('html')->attr('data-theme'));
    }

    public function testATokenThatIsNotValidMayBeFramedByNone(): void
    {
        foreach (['pe1.hs256.2026-11.e30.AAAA', $this->issue('form', 'https://partner.example')] as $token) {
            $response = $this->get('/embed/calendar/7?embed_token='.$token);

            self::assertSame(["frame-ancestors 'none'"], $response->headers->all('content-security-policy'));
            self::assertSame('none', new Crawler((string) $response->getContent())->filter('#context')->text());
            self::assertSame('', (string) new Crawler((string) $response->getContent())->filter('html')->attr('data-controller'));
        }
    }

    public function testWithoutATokenOrOutsideAnEmbedNothingChanges(): void
    {
        foreach (['/embed/calendar/7', '/embed/calendar/7?embed_token=', '/plain?embed_token='.$this->issue('calendar', 'https://partner.example')] as $path) {
            $response = $this->get($path);

            self::assertSame([], $response->headers->all('content-security-policy'), $path);
            self::assertSame('none', new Crawler((string) $response->getContent())->filter('#context')->text(), $path);
        }
    }

    public function testThroughBrowserSecurityTheFramingReplacesFrameAncestorsAndDropsFrameOptions(): void
    {
        $kernel = self::bootKernel(['environment' => 'security']);
        $token = $this->issue('calendar', 'https://partner.example');

        $allowed = $kernel->handle(Request::create('/embed/calendar/7?embed_token='.$token));
        $refused = $this->handle($kernel, '/embed/calendar/7?embed_token=pe1.hs256.2026-11.e30.AAAA');
        $plain = $this->handle($kernel, '/plain');

        self::assertStringContainsString('frame-ancestors https://partner.example', (string) $allowed->headers->get('Content-Security-Policy'));
        self::assertStringNotContainsString("'self'; frame-ancestors 'self'", (string) $allowed->headers->get('Content-Security-Policy'));
        self::assertFalse($allowed->headers->has('X-Frame-Options'));
        self::assertStringContainsString("frame-ancestors 'none'", (string) $refused->headers->get('Content-Security-Policy'));
        self::assertSame('SAMEORIGIN', $refused->headers->get('X-Frame-Options'));
        self::assertStringContainsString("frame-ancestors 'self'", (string) $plain->headers->get('Content-Security-Policy'));
        self::assertSame('SAMEORIGIN', $plain->headers->get('X-Frame-Options'));

        $script = $this->handle($kernel, '/_embed/phil-embed.js');
        self::assertSame('cross-origin', $script->headers->get('Cross-Origin-Resource-Policy'));
    }

    public function testTheScriptUnderItsStableAddress(): void
    {
        $response = $this->get('/_embed/phil-embed.js');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame((string) file_get_contents(PhilEmbedBundle::CONTROLLERS_DIR.'/phil-embed.js'), $response->getContent());
        self::assertSame(['text/javascript; charset=utf-8', '*', 'cross-origin', 'nosniff'], [$response->headers->get('Content-Type'), $response->headers->get('Access-Control-Allow-Origin'), $response->headers->get('Cross-Origin-Resource-Policy'), $response->headers->get('X-Content-Type-Options')]);
        self::assertSame('max-age=86400, public', $response->headers->get('Cache-Control'));
        self::assertSame(200, $this->get('/_embed/lib/message.js')->getStatusCode());
        self::assertSame(404, $this->get('/_embed/frame_controller.js')->getStatusCode());
        self::assertSame(404, $this->get('/_embed/../composer.json')->getStatusCode());

        $kernel = self::bootKernel();
        $again = Request::create('/_embed/phil-embed.js');
        $again->headers->set('If-None-Match', (string) $response->getEtag());
        self::assertSame(304, $kernel->handle($again)->getStatusCode());
    }

    public function testTheCodeOfAnEmbed(): void
    {
        $code = new Crawler((string) $this->get('/codes')->getContent())->filter('#calendar')->text();

        self::assertMatchesRegularExpression('#^<phil-embed src="http://localhost/embed/calendar/7\?embed_token=pe1\.hs256\.2026-11\.[^"]+" title="Our calendar" theme="dark">#', $code);
        self::assertStringContainsString('<a href="https://site.example/calendar/7">Our calendar</a>', $code);
        self::assertStringContainsString('<script type="module" src="http://localhost/_embed/phil-embed.js" crossorigin="anonymous"></script>', $code);
    }

    public function testTheTokenCommand(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('phil:embed:token'));

        self::assertSame(0, $tester->execute(['embed' => 'calendar', 'origin' => 'https://partner.example', '--theme' => 'dark', '--subject' => 'calendar/7']));
        $claims = self::getContainer()->get(EmbedTokens::class);
        self::assertInstanceOf(EmbedTokens::class, $claims);
        $context = $claims->context('calendar', trim($tester->getDisplay()));
        self::assertSame(['https://partner.example', 'dark', 'calendar/7'], [$context->origin, $context->theme, $context->subject]);

        self::assertSame(0, $tester->execute(['embed' => 'calendar', 'origin' => 'http://localhost:9188']));
        self::assertSame(1, $tester->execute(['embed' => 'calendar', 'origin' => 'https://elsewhere.example']));
        self::assertStringContainsString('The embed "calendar" does not allow https://elsewhere.example.', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        self::assertSame(1, $tester->execute(['embed' => 'calendar', 'origin' => 'https://partner.example/path']));
    }

    private function issue(string $embed, string $origin, ?string $theme = null, ?string $subject = null): string
    {
        self::bootKernel();
        $tokens = self::getContainer()->get(EmbedTokens::class);
        self::assertInstanceOf(EmbedTokens::class, $tokens);

        return $tokens->issue($embed, Origin::fromString($origin), $theme, $subject);
    }

    private function get(string $path): Response
    {
        return $this->handle(self::bootKernel(), $path);
    }

    private function handle(KernelInterface $kernel, string $path): Response
    {
        $kernel->shutdown();
        $kernel->boot();

        return $kernel->handle(Request::create($path));
    }
}
