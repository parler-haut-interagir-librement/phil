<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional;

use DateTimeImmutable;
use Override;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Http\SettingsController;
use Phil\SettingsBundle\PhilSettingsBundle;
use Phil\SettingsBundle\Tests\Functional\App\SecuredKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The site's settings behind SecurityBundle: the firewall refuses a visitor and a member, each setting shows only to
 * its role, a field of another role is refused, the preview saves nothing, Symfony Security names whose preferences
 * are saved, and the migrations run from the console once.
 */
#[CoversClass(PhilSettingsBundle::class)]
final class SiteSettingsSecurityTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return SecuredKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testAVisitorIsAskedToSignInAndAMemberIsRefused(): void
    {
        $visitor = $this->handle(Request::create('/admin/settings/site'));
        self::assertSame(401, $visitor->getStatusCode());
        self::assertStringStartsWith('Basic', (string) $visitor->headers->get('WWW-Authenticate'));

        self::assertSame(403, $this->handle($this->as('bob', Request::create('/admin/settings/site')))->getStatusCode());
        self::assertSame(401, $this->handle($this->as('ada', Request::create('/admin/settings/site'), 'wrong'))->getStatusCode());
    }

    public function testEachSettingShowsOnlyToItsRole(): void
    {
        self::assertSame('/admin/settings/site', $this->handle($this->as('ada', Request::create('/admin/settings')))->headers->get('Location'));

        $admin = (string) $this->handle($this->as('ada', Request::create('/admin/settings/site')))->getContent();
        self::assertStringContainsString('name="phil_settings[site-open]"', $admin);
        self::assertStringContainsString('name="phil_settings[site-motto]"', $admin);
        self::assertStringContainsString('<title>Site settings</title>', $admin);

        $editor = (string) $this->handle($this->as('eve', Request::create('/admin/settings/site')))->getContent();
        self::assertStringNotContainsString('site-open', $editor);
        self::assertStringContainsString('name="phil_settings[site-motto]"', $editor);
    }

    public function testAnAdministratorSavesAndAnEditorCannotReachAnotherRolesSetting(): void
    {
        $saved = $this->handle($this->as('ada', $this->post('/admin/settings/site', ['site-motto' => 'Welcome'])));
        self::assertSame([303, '/admin/settings/site?saved=1'], [$saved->getStatusCode(), $saved->headers->get('Location')]);

        $refused = $this->handle($this->as('eve', $this->post('/admin/settings/site', ['site-motto' => 'Mine', 'site-open' => '1'])));
        self::assertSame(422, $refused->getStatusCode());

        $settings = $this->settings();
        self::assertFalse($settings->bool('site.open'));
        self::assertSame('Welcome', $settings->string('site.motto'));
    }

    public function testThePreviewShowsTheChangesAndSavesNothing(): void
    {
        $request = $this->post('/admin/settings/site', ['site-motto' => 'Bonjour', 'site-open' => '1']);
        $request->request->set(SettingsController::PREVIEW, '1');

        $response = $this->handle($this->as('ada', $request));
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode(), $html);
        self::assertStringContainsString('<h3 id="phil-settings-preview">Before saving</h3>', $html);
        self::assertStringContainsString('<td>Hello</td>', $html);
        self::assertStringContainsString('<td>Bonjour</td>', $html);
        self::assertStringContainsString('<strong>(changed)</strong>', $html);
        self::assertStringContainsString('value="Bonjour"', $html);
        self::assertSame('Hello', $this->settings()->string('site.motto'));
    }

    public function testSecurityNamesWhosePreferencesAreSaved(): void
    {
        $response = $this->handle($this->as('bob', $this->post('/preferences/display', ['display-theme' => 'dark', 'display-motion' => 'system', 'display-contrast' => 'system', 'display-text_size' => 'system'])));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('dark', $this->settings()->string('display.theme', 'bob'));
        self::assertSame('system', $this->settings()->string('display.theme', 'eve'));
    }

    public function testTheMigrationsRunFromTheConsoleOnce(): void
    {
        $repository = self::getContainer()->get(SettingRepository::class);
        self::assertInstanceOf(SettingRepository::class, $repository);
        $repository->save(Subject::site(), ['site.tagline' => '"Hi there"'], [], new DateTimeImmutable());
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester(new Application($kernel)->find('phil:settings:migrate'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 migration(s) applied.', $tester->getDisplay());
        self::assertSame(['site.motto' => '"Hi there"'], $repository->values(Subject::site()));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Every migration of the settings is applied.', $tester->getDisplay());
    }

    private function settings(): Settings
    {
        $settings = self::getContainer()->get(Settings::class);
        self::assertInstanceOf(Settings::class, $settings);
        $settings->reset();

        return $settings;
    }

    private function as(string $user, Request $request, ?string $password = null): Request
    {
        $request->headers->set('PHP_AUTH_USER', $user);
        $request->headers->set('PHP_AUTH_PW', $password ?? $user.'-password');
        $request->server->set('PHP_AUTH_USER', $user);
        $request->server->set('PHP_AUTH_PW', $password ?? $user.'-password');

        return $request;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function post(string $path, array $fields): Request
    {
        return Request::create($path, 'POST', ['phil_settings' => [...$fields, '_token' => 'csrf-token']], server: ['HTTP_ORIGIN' => 'http://localhost']);
    }

    /**
     * Each request as a worker serves it: handled, terminated, then the services reset.
     */
    private function handle(Request $request): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $response = $kernel->handle($request);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }
}
