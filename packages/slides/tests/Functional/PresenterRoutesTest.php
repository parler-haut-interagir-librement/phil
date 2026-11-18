<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional;

use Override;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The presenter routes of `config/routes.php` in a booted application: the speaker who holds the configured role sees
 * the presenter view and downloads the PDF with the notes; a visitor signed in is refused (403), one not signed in is
 * asked to sign in (401), before any deck is looked for.
 */
#[CoversClass(PresenterController::class)]
final class PresenterRoutesTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $environment = $options['environment'] ?? 'test';
        \assert(\is_string($environment));

        // Without debug, an error page shows no source: the suites read what a visitor would.
        return new TestKernel($environment, false);
    }

    public function testTheSpeakerSeesThePresenterViewInTheLayout(): void
    {
        $client = $this->client('test', 'speaker');
        $page = $client->request('GET', '/presenter/talk-7');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('Vue présentateur – La présentation', $page->filter('title')->text());
        self::assertSame('phil--slides--presenter', $page->filter('main.phil-slides-page > section')->attr('data-controller'));
        self::assertSame('talk-7', $page->filter('section.phil-slides-presenter')->attr('data-phil--slides--presenter-channel-value'));
        self::assertSame('1200', $page->filter('section.phil-slides-presenter')->attr('data-phil--slides--presenter-duration-value'));
        self::assertStringContainsString('Remercier les bénévoles.', $page->filter('.phil-slides-presenter__notes')->first()->text());
        self::assertSame('/talks/7', $page->filter('.phil-slides-presenter__audience a')->attr('href'));

        $pdf = $page->filter('.phil-slides-presenter__pdf a');
        self::assertSame('/presenter/talk-7/notes.pdf', $pdf->attr('href'));
        self::assertSame('', $pdf->attr('download'));
        self::assertSame('Télécharger le PDF avec les notes', trim($pdf->text()));
    }

    public function testTheSpeakerDownloadsThePdfWithTheNotes(): void
    {
        $client = $this->client('test', 'speaker');
        $client->request('GET', '/presenter/talk-7/notes.pdf');

        $response = $client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=talk-7-notes.pdf');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('@page { size: 612pt 792pt; margin: 36pt }', (string) $response->getContent());
        self::assertStringContainsString('bénévoles', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function routes(): iterable
    {
        yield 'presenter view' => ['/presenter/talk-7'];
        yield 'PDF with the notes' => ['/presenter/talk-7/notes.pdf'];
        yield 'a deck that does not exist' => ['/presenter/talk-8'];
    }

    #[DataProvider('routes')]
    public function testAVisitorIsRefusedBeforeAnyDeckIsLookedFor(string $path): void
    {
        $client = $this->client('test', 'visitor');
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('bénévoles', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('routes')]
    public function testSomeoneNotSignedInIsAskedToSignIn(string $path): void
    {
        $client = $this->client('test', null);
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(401);
    }

    public function testADeckThatDoesNotExistIsNotFoundForTheSpeaker(): void
    {
        $client = $this->client('test', 'speaker');
        $client->request('GET', '/presenter/talk-8');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/presenter/talk-8/notes.pdf');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/presenter/talk%207');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheRoleIsTheOneTheConfigurationNames(): void
    {
        $client = $this->client(TestKernel::SPEAKER_ROLE, 'organiser');
        $client->request('GET', '/presenter/talk-7');
        self::assertResponseIsSuccessful();

        $client->setServerParameters(['PHP_AUTH_USER' => 'speaker', 'PHP_AUTH_PW' => 'speaker']);
        $client->request('GET', '/presenter/talk-7');
        self::assertResponseStatusCodeSame(403);
    }

    private function client(string $environment, ?string $user): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = self::createClient(['environment' => $environment]);

        if (null !== $user) {
            $client->setServerParameters(['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $user]);
        }

        return $client;
    }
}
