<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional;

use Override;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\PhilSettingsBundle;
use Phil\SettingsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The preferences in a real kernel: in French, a visitor's display preferences kept in their cookie and written on
 * the page, a user's saved, a form from another site refused.
 */
#[CoversClass(PhilSettingsBundle::class)]
final class SettingsBundleTest extends KernelTestCase
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

    public function testAVisitorReachesTheDisplayInFrench(): void
    {
        self::assertSame('/preferences/display', $this->handle(Request::create('/preferences'))->headers->get('Location'));

        $html = (string) $this->handle(Request::create('/preferences/display'))->getContent();

        self::assertStringContainsString('<html lang="fr" >', $html);
        self::assertStringContainsString('<title>Préférences</title>', $html);
        self::assertStringContainsString('<h2 id="phil-settings-section">Affichage</h2>', $html);
        self::assertStringContainsString('Réduire les animations', $html);
        self::assertStringContainsString('data-controller="phil--settings-bundle--document"', $html);
        self::assertStringContainsString('data-attribute="motion"', $html);
        self::assertStringContainsString('>Enregistrer</button>', $html);
        self::assertStringNotContainsString('class="', $html, 'no class of its own');
    }

    public function testAVisitorsChoiceIsKeptInTheirCookieAndWrittenOnThePage(): void
    {
        $response = $this->handle($this->post('/preferences/display', ['display-theme' => 'dark', 'display-motion' => 'reduce', 'display-contrast' => 'system', 'display-text_size' => 'large']));

        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('/preferences/display?saved=1', $response->headers->get('Location'));
        $cookie = $response->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertSame(['phil_preferences', 'display.motion=reduce&display.text_size=large&display.theme=dark', true, 'lax'], [$cookie->getName(), $cookie->getValue(), $cookie->isHttpOnly(), $cookie->getSameSite()]);

        $html = (string) $this->handle(Request::create('/preferences/display?saved=1', cookies: ['phil_preferences' => (string) $cookie->getValue()]))->getContent();
        self::assertStringContainsString('<html lang="fr" data-theme="dark" data-motion="reduce" data-text-size="large">', $html);
        self::assertStringContainsString('Vos réglages sont enregistrés.', $html);
    }

    public function testAUserSavesTheirPreferences(): void
    {
        self::assertSame('/preferences/author', $this->handle(Request::create('/preferences', server: ['HTTP_X_USER' => 'ada']))->headers->get('Location'));

        $response = $this->handle($this->post('/preferences/notifications', ['notifications-digest' => 'weekly', 'notifications-email' => '1', 'notifications-quiet_start' => '', 'notifications-quiet_end' => ''], 'ada'));
        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());

        $refused = $this->handle($this->post('/preferences/author', ['author-signature' => str_repeat('x', 21)], 'ada'));
        self::assertSame(422, $refused->getStatusCode());
        self::assertStringContainsString('Cette valeur compte au plus 20 caractères.', (string) $refused->getContent());

        $settings = self::getContainer()->get(Settings::class);
        self::assertInstanceOf(Settings::class, $settings);
        self::assertSame('weekly', $settings->string('notifications.digest', 'ada'));
        self::assertFalse($settings->bool('notifications.web_push', 'ada'));
        self::assertTrue($settings->bool('notifications.email', 'ada'));
    }

    public function testAFormFromAnotherSiteIsRefused(): void
    {
        $request = Request::create('/preferences/display', 'POST', ['phil_settings' => ['display-motion' => 'reduce', '_token' => 'csrf-token']], server: ['HTTP_ORIGIN' => 'https://evil.example']);
        $response = $this->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $response->headers->getCookies());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function post(string $path, array $fields, ?string $user = null): Request
    {
        $server = ['HTTP_ORIGIN' => 'http://localhost'];

        if (null !== $user) {
            $server['HTTP_X_USER'] = $user;
        }

        return Request::create($path, 'POST', ['phil_settings' => [...$fields, '_token' => 'csrf-token']], server: $server);
    }

    private function handle(Request $request): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return $kernel->handle($request);
    }
}
