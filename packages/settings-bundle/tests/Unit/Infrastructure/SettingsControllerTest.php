<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Form\SettingsForm;
use Phil\SettingsBundle\Infrastructure\Http\PreferenceCookie;
use Phil\SettingsBundle\Infrastructure\Http\Routes;
use Phil\SettingsBundle\Infrastructure\Http\SettingsController;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\Forms;
use Phil\SettingsBundle\Tests\Unit\Fixture\Roles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(SettingsController::class)]
final class SettingsControllerTest extends TestCase
{
    private const string PAGE = '{{ scope }}|{{ section.key }}|{% for link in sections %}{{ link.key }}:{{ link.label }}@{{ link.translation_domain }}:{{ link.url }}:{{ link.current ? 1 : 0 }},{% endfor %}|{{ saved ? 1 : 0 }}|{% for field in form.children %}{{ field.vars.name }}={{ field.vars.errors|length }},{% endfor %}';

    private FixedUser $user;

    private Roles $roles;

    private InMemorySettingRepository $repository;

    private RequestStack $requests;

    private Settings $settings;

    protected function setUp(): void
    {
        $this->user = new FixedUser('ada');
        $this->roles = new Roles(['ROLE_ADMIN']);
        $this->repository = new InMemorySettingRepository();
        $this->requests = new RequestStack();
        $this->settings = new Settings(Catalogs::sample(), $this->repository, $this->user, new PreferenceCookie($this->requests, new PreferenceCookieFormat(Catalogs::sample())), new MockClock());
    }

    public function testWithoutSectionThePageGoesToItsFirst(): void
    {
        $response = $this->controller()->preferences(Request::create('/preferences'));

        self::assertSame([302, '/preferences/author'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame('/admin/settings/site', $this->controller()->site(Request::create('/admin/settings'))->headers->get('Location'));
    }

    public function testASectionShowsItsFormAmongTheOthers(): void
    {
        $response = $this->controller()->preferences(Request::create('/preferences/author'), 'author');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(
            'user|author|author:section.author@messages:/preferences/author:1,notifications:section.notifications@phil_settings:/preferences/notifications:0,display:section.display@phil_settings:/preferences/display:0,|0|author-signature=0,author-columns=0,author-public=0,author-genres=0,',
            $response->getContent(),
        );
        self::assertStringContainsString('|1|', (string) $this->controller()->preferences(Request::create('/preferences/author?saved=1'), 'author')->getContent());
    }

    public function testAValidFormSavesAndRedirects(): void
    {
        $response = $this->controller()->preferences($this->post('/preferences/author', ['author-signature' => 'Ada', 'author-columns' => '3', 'author-genres' => ['horror']]), 'author');

        self::assertSame([303, '/preferences/author?saved=1'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame(['author.signature' => '"Ada"', 'author.columns' => '3', 'author.genres' => '["horror"]'], $this->repository->values(Subject::user('ada')));
        self::assertNull($response->headers->getCookies()[0]->getValue(), 'author.public, kept for visitors, is at its default');

        $notifications = $this->controller()->preferences($this->post('/preferences/notifications', ['notifications-digest' => 'daily', 'notifications-quiet_start' => '', 'notifications-quiet_end' => '']), 'notifications');
        self::assertSame(303, $notifications->getStatusCode());
        self::assertSame([], $notifications->headers->getCookies(), 'no setting of the section is kept for visitors');
        self::assertSame('"daily"', $this->repository->values(Subject::user('ada'))['notifications.digest']);
    }

    public function testTheSettingsKeptForVisitorsAlsoGoToTheCookie(): void
    {
        $request = $this->post('/preferences/display', ['display-theme' => 'dark', 'display-motion' => 'system', 'display-contrast' => 'more', 'display-text_size' => 'system']);
        $request->cookies->set('phil_preferences', 'author.public=1&display.motion=reduce');

        $this->requests->push($request);

        $response = $this->controller()->preferences($request, 'display');
        $cookies = $response->headers->getCookies();

        self::assertSame(303, $response->getStatusCode());
        self::assertCount(1, $cookies);
        self::assertSame(['author.public=1&display.contrast=more&display.theme=dark', 1_000_000 + PreferenceCookie::LIFETIME], [$cookies[0]->getValue(), $cookies[0]->getExpiresTime()]);
        self::assertSame(['display.theme' => '"dark"', 'display.contrast' => '"more"'], $this->repository->values(Subject::user('ada')));
    }

    public function testAVisitorSeesOnlyTheSettingsKeptForThemAndSavesThemInTheCookie(): void
    {
        $this->user->id = null;
        $controller = $this->controller();

        self::assertSame('/preferences/author', $controller->preferences(Request::create('/preferences'))->headers->get('Location'));
        self::assertStringEndsWith('|author-public=0,', (string) $controller->preferences(Request::create('/preferences/author'), 'author')->getContent());

        $response = $controller->preferences($this->post('/preferences/display', ['display-theme' => 'light', 'display-motion' => 'reduce', 'display-contrast' => 'system', 'display-text_size' => 'large']), 'display');

        self::assertSame('display.motion=reduce&display.text_size=large&display.theme=light', $response->headers->getCookies()[0]->getValue());
        self::assertSame([], $this->repository->values(Subject::user('ada')));

        try {
            $controller->preferences(Request::create('/preferences/notifications'), 'notifications');
            self::fail('A visitor sees the notifications.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('The section "notifications" is not shown.', $notFoundHttpException->getMessage());
        }
    }

    public function testARefusedValueShowsTheFormAgain(): void
    {
        $response = $this->controller()->preferences($this->post('/preferences/author', ['author-columns' => '9']), 'author');

        self::assertSame(422, $response->getStatusCode());
        self::assertStringEndsWith('|author-signature=0,author-columns=1,author-public=0,author-genres=0,', (string) $response->getContent());
        self::assertSame([], $this->repository->values(Subject::user('ada')));
    }

    public function testTheSiteSettingsAreShownByRole(): void
    {
        self::assertStringEndsWith('|site-open=0,', (string) $this->controller()->site(Request::create('/admin/settings/site'), 'site')->getContent());
        self::assertStringStartsWith('site|site|site:', (string) $this->controller()->site(Request::create('/admin/settings/site'), 'site')->getContent());

        $this->roles->granted = ['ROLE_ADMIN', 'ROLE_EDITOR'];
        self::assertStringEndsWith('|site-open=0,site-motto=0,', (string) $this->controller()->site(Request::create('/admin/settings/site'), 'site')->getContent());

        $response = $this->controller()->site($this->post('/admin/settings/site', ['site-motto' => 'Welcome']), 'site');
        self::assertSame('/admin/settings/site?saved=1', $response->headers->get('Location'));
        self::assertSame(['site.open' => 'false', 'site.motto' => '"Welcome"'], $this->repository->values(Subject::site()));

        $this->roles->granted = ['ROLE_EDITOR'];
        self::assertStringEndsWith('|site-motto=0,', (string) $this->controller('ROLE_OWNER')->site(Request::create('/admin/settings/site'), 'site')->getContent());
    }

    public function testThePreviewButtonShowsWhatSavingWouldDoAndSavesNothing(): void
    {
        $this->repository->save(Subject::user('ada'), ['author.signature' => '"Ada"', 'author.genres' => '["horror","science"]'], [], new DateTimeImmutable());
        $request = $this->post('/preferences/author', ['author-signature' => '', 'author-columns' => '4', 'author-public' => '1', 'author-genres' => []]);
        $request->request->set(SettingsController::PREVIEW, '1');

        $response = $this->controller(page: '{{ preview|json_encode }}|{{ form.children["author-columns"].vars.value }}')->preferences($request, 'author');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->headers->getCookies());
        self::assertSame(['author.signature' => '"Ada"', 'author.genres' => '["horror","science"]'], $this->repository->values(Subject::user('ada')), 'nothing is saved');
        [$preview, $columns] = explode('|', (string) $response->getContent());
        self::assertSame('4', $columns, 'the form keeps what was submitted');
        self::assertSame([
            ['key' => 'author.signature', 'label' => 'author.signature.label', 'translation_domain' => 'messages', 'current' => [['message' => 'Ada', 'domain' => null]], 'proposed' => [['message' => 'phil_settings.empty', 'domain' => 'phil_settings']], 'changed' => true],
            ['key' => 'author.columns', 'label' => 'author.columns.label', 'translation_domain' => 'messages', 'current' => [['message' => '2', 'domain' => null]], 'proposed' => [['message' => '4', 'domain' => null]], 'changed' => true],
            ['key' => 'author.public', 'label' => 'author.public.label', 'translation_domain' => 'messages', 'current' => [['message' => 'phil_settings.no', 'domain' => 'phil_settings']], 'proposed' => [['message' => 'phil_settings.yes', 'domain' => 'phil_settings']], 'changed' => true],
            ['key' => 'author.genres', 'label' => 'author.genres.label', 'translation_domain' => 'messages', 'current' => [['message' => 'author.genres.choices.horror', 'domain' => 'messages'], ['message' => 'author.genres.choices.science', 'domain' => 'messages']], 'proposed' => [['message' => 'phil_settings.none', 'domain' => 'phil_settings']], 'changed' => true],
        ], json_decode(html_entity_decode($preview), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testThePreviewOfAChoiceAndOfAnUnchangedValue(): void
    {
        $request = $this->post('/preferences/notifications', ['notifications-digest' => 'never', 'notifications-email' => '1', 'notifications-quiet_start' => '', 'notifications-quiet_end' => '']);
        $request->request->set(SettingsController::PREVIEW, '1');

        $response = $this->controller(page: '{% for row in preview %}{{ row.key }}:{{ row.current|map(p => p.message)|join("+") }}>{{ row.proposed|map(p => p.message ~ "@" ~ p.domain)|join("+") }}:{{ row.changed ? 1 : 0 }},{% endfor %}')->preferences($request, 'notifications');

        self::assertStringStartsWith('notifications.digest:notifications.digest.choices.never>notifications.digest.choices.never@phil_settings:0,notifications.email:phil_settings.yes>phil_settings.yes@phil_settings:0,', (string) $response->getContent());
    }

    public function testAnInvalidFormIsNotPreviewed(): void
    {
        $request = $this->post('/preferences/author', ['author-columns' => '9']);
        $request->request->set(SettingsController::PREVIEW, '1');

        self::assertSame(422, $this->controller(page: '{{ preview|length }}')->preferences($request, 'author')->getStatusCode());
    }

    public function testAPageWithNothingToShowIsDenied(): void
    {
        $this->roles->granted = [];

        try {
            $this->controller()->site(Request::create('/admin/settings'));
            self::fail('The site settings are shown.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame('No setting of this page is shown to the current user.', $accessDeniedException->getMessage());
        }

        $this->expectException(AccessDeniedException::class);

        $this->controller(authorization: false)->site(Request::create('/admin/settings'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function post(string $path, array $fields): Request
    {
        return Request::create($path, 'POST', [SettingsForm::NAME => $fields]);
    }

    private function controller(string $siteRole = 'ROLE_ADMIN', bool $authorization = true, string $page = self::PAGE): SettingsController
    {
        $routes = new RouteCollection();
        $routes->add(Routes::PREFERENCES, new Route('/preferences/{section}'));
        $routes->add(Routes::SITE, new Route('/admin/settings/{section}'));

        return new SettingsController(
            Catalogs::sample(),
            $this->settings,
            new SettingsForm(Forms::factory()),
            new PreferenceCookie($this->requests, new PreferenceCookieFormat(Catalogs::sample())),
            new Environment(new ArrayLoader(['page' => $page]), ['strict_variables' => true]),
            new UrlGenerator($routes, new RequestContext()),
            new MockClock('@1000000'),
            $this->user,
            'page',
            $siteRole,
            $authorization ? $this->roles : null,
        );
    }
}
