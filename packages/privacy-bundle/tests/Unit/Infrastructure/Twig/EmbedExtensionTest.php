<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentState;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigExportReadme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\Node\Nodes;
use Twig\TwigFunction;

#[CoversClass(EmbedExtension::class)]
#[CoversClass(TwigExportReadme::class)]
final class EmbedExtensionTest extends TestCase
{
    private const string TEMPLATE = '{{ granted ? "granted" : "blocked" }}|{{ source.url }}|{{ category.name }}|{{ title }}|{{ preview }}|{{ width }}x{{ height }}|{{ allow }}|{{ provider }}|{{ csrf_token }}|{{ target }}|{{ locale }}';

    public function testABlockedFrameGetsWhatItsPlaceholderNeeds(): void
    {
        $request = Request::create('/videos?page=2');
        $request->setLocale('fr');

        self::assertSame(
            'blocked|https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ|videos|A talk|/media/still.webp|640x360|'.EmbedExtension::DEFAULT_ALLOW.'|YouTube|tok|/videos?page=2|fr',
            $this->render(new RequestStack([$request]), 'https://youtu.be/dQw4w9WgXcQ', 'videos', ['title' => 'A talk', 'preview' => '/media/still.webp', 'width' => 640, 'height' => 360]),
        );
    }

    public function testAGrantedFrameIsSaidSoAndTheOptionsAreKept(): void
    {
        $request = Request::create('/');
        $request->attributes->set(ConsentContext::ATTRIBUTE, new ConsentState($this->policy(), new ConsentChoice(str_repeat('a', 32), '1', ['videos'], new DateTimeImmutable(), false), false));

        self::assertSame(
            'granted|https://maps.example.org/embed|videos|A map||x|fullscreen|Our maps|tok|/|en',
            $this->render(new RequestStack([$request]), 'https://maps.example.org/embed', 'videos', ['title' => 'A map', 'allow' => 'fullscreen', 'provider' => 'Our maps']),
        );
    }

    public function testANecessaryCategoryIsAlwaysGrantedAndOutsideARequestNothingIsATarget(): void
    {
        self::assertStringStartsWith('granted|', $this->render(new RequestStack(), 'https://example.org/', 'session', ['title' => 't']));
        self::assertStringEndsWith('|tok||en', $this->render(new RequestStack(), 'https://example.org/', 'videos', ['title' => 't']));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'unknown option' => ['videos', ['title' => 't', 'autoplay' => true], 'privacy_embed() has no option "autoplay"'];
        yield 'unknown category' => ['marketing', ['title' => 't'], 'names the category "marketing", which is not a consent category'];
        yield 'no title' => ['videos', [], 'needs a "title"'];
        yield 'empty title' => ['videos', ['title' => ' '], 'needs a "title"'];
        yield 'title not text' => ['videos', ['title' => 1], 'needs a "title"'];
        yield 'remote preview' => ['videos', ['title' => 't', 'preview' => 'https://i.ytimg.com/vi/x/0.jpg'], 'The preview of privacy_embed() is a path of this site'];
        yield 'protocol-relative preview' => ['videos', ['title' => 't', 'preview' => '//i.ytimg.com/x.jpg'], 'The preview of privacy_embed() is a path of this site'];
        yield 'preview not text' => ['videos', ['title' => 't', 'preview' => 3], 'The preview of privacy_embed() is a path of this site'];
        yield 'zero width' => ['videos', ['title' => 't', 'width' => 0], 'The width of privacy_embed() is a number of pixels from 1 to 10000.'];
        yield 'huge height' => ['videos', ['title' => 't', 'height' => 10001], 'The height of privacy_embed() is a number of pixels from 1 to 10000.'];
        yield 'width as text' => ['videos', ['title' => 't', 'width' => '640'], 'The width of privacy_embed() is a number of pixels from 1 to 10000.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refused')]
    public function testWhatIsRefused(string $category, array $options, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->render(new RequestStack(), 'https://example.org/', $category, $options);
    }

    public function testTheLimitsOfTheSizesAreInclusive(): void
    {
        self::assertStringContainsString('|1x10000|', $this->render(new RequestStack(), 'https://example.org/', 'videos', ['title' => 't', 'width' => 1, 'height' => 10000]));
    }

    public function testTheFunctionIsDeclaredSafeWithTheEnvironment(): void
    {
        $functions = new AttributeExtension(EmbedExtension::class)->getFunctions();

        self::assertSame(['privacy_embed'], array_map(static fn (TwigFunction $function): string => $function->getName(), $functions));
        self::assertSame(['html'], $functions[0]->getSafe(new Nodes()));
        self::assertTrue($functions[0]->needsEnvironment());
    }

    public function testTheReadmeIsItsTemplate(): void
    {
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/export/README.txt.twig' => '{% for module, count in modules %}{{ module }}={{ count }};{% endfor %}{{ made_at|date("Y") }}']));

        self::assertSame('a=1;b=0;2026', new TwigExportReadme($twig)->render(['a' => 1, 'b' => 0], new DateTimeImmutable('2026-11-10')));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function render(RequestStack $stack, string $url, string $category, array $options): string
    {
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/embed/frame.html.twig' => self::TEMPLATE]), ['autoescape' => false]);
        $context = new ConsentContext($stack, new ConsentCookie(new ConsentToken('s'), new MockClock(), 'c', 'P1D'), $this->policy());

        return new EmbedExtension($context, $this->policy(), new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'l'), $stack)->embed($twig, $url, $category, $options);
    }

    private function policy(): ConsentPolicy
    {
        return new ConsentPolicy('1', [new ConsentCategory('session', true), new ConsentCategory('videos')]);
    }
}
