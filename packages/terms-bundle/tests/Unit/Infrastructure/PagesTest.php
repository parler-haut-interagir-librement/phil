<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceController;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\Infrastructure\Http\PolicyController;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\PhilTermsBundle;
use Phil\TermsBundle\Tests\Unit\Fixture\FixedMember;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * The acceptance page and the public pages, rendered by Twig with the bundle's templates and translations.
 */
#[CoversClass(Pages::class)]
#[CoversClass(AcceptanceController::class)]
#[CoversClass(PolicyController::class)]
final class PagesTest extends TestCase
{
    public function testTheAcceptancePageShowsEachVersionWithABoxNotTicked(): void
    {
        $kit = $this->kit();
        $response = $this->acceptance($kit)->accept(Request::create('/accept?target=/forum'));
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<h2 id="phil-terms-terms">terms of use</h2>', $html);
        self::assertStringContainsString('<h2 id="phil-terms-privacy">privacy policy</h2>', $html);
        self::assertStringContainsString('<p>Terms <b></p>', str_replace('&lt;b&gt;', '<b>', $html));
        self::assertStringContainsString('<input type="checkbox" data-slot="checkbox" id="phil-terms-accept-terms" name="accept[terms]" value="1" required', $html);
        self::assertStringContainsString('<input type="checkbox" data-slot="checkbox" id="phil-terms-accept-privacy" name="accept[privacy]" value="2" required', $html);
        self::assertStringContainsString('<label data-slot="field-label" class="isFieldLabel" for="phil-terms-accept-terms">I have read and I accept version 1 of the terms of use</label>', $html);
        self::assertStringContainsString('type="submit">Accept and continue</button>', $html);
        self::assertStringNotContainsString('data-slot="alert"', $html);
        self::assertStringNotContainsString('checked', $html);
        self::assertStringContainsString('href="/terms/v1"', $html);
        self::assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $html, $token));
        self::assertTrue($kit->csrf->isTokenValid(new CsrfToken(Pages::CSRF_TOKEN_ID, $token[1])));
        self::assertStringNotContainsString('role="alert"', $html);
    }

    public function testTheVersionsAreAcceptedWhenEveryBoxIsTicked(): void
    {
        $kit = $this->kit();
        $request = $kit->post('/accept?target=/forum?page=2', ['accept' => ['terms' => '1', 'privacy' => '2']]);
        $request->server->set('REMOTE_ADDR', '192.0.2.7');

        $response = $this->acceptance($kit)->accept($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/forum?page=2', $response->headers->get('Location'));
        self::assertSame([['42', 'terms', 1, AcceptanceChannel::Form], ['42', 'privacy', 2, AcceptanceChannel::Form]], array_map(static fn (Acceptance $acceptance): array => [$acceptance->member, $acceptance->policy, $acceptance->version, $acceptance->channel], $kit->acceptances->acceptances));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function missing(): iterable
    {
        yield 'none' => [[]];
        yield 'one' => [['accept' => ['terms' => '1']]];
        yield 'the other' => [['accept' => ['privacy' => '2']]];
        yield 'a version shown before' => [['accept' => ['terms' => '1', 'privacy' => '1']]];
    }

    /**
     * @param array<string, mixed> $form
     */
    #[DataProvider('missing')]
    public function testAFormWithABoxNotTickedIsShownAgain(array $form): void
    {
        $kit = $this->kit();
        $response = $this->acceptance($kit)->accept($kit->post('/accept', $form));

        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesRegularExpression('~<div data-slot="alert" data-variant="destructive" class="isAlert isDanger" role="alert">\s*<p data-slot="alert-title" class="isAlertTitle"><strong>Tick the box of each version to accept it.</strong></p>~', (string) $response->getContent());
        self::assertSame([], $kit->acceptances->acceptances);
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $kit = $this->kit();

        $this->expectException(BadRequestHttpException::class);

        $this->acceptance($kit)->accept($kit->post('/accept', ['accept' => ['terms' => '1', 'privacy' => '2']], 'bad'));
    }

    public function testAMemberInOrderGoesBackAtOnce(): void
    {
        $kit = $this->kit();
        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('42', 'privacy', 2, AcceptanceChannel::Form);

        $response = $this->acceptance($kit)->accept(Request::create('/accept'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testAVisitorIsDenied(): void
    {
        $kit = $this->kit();

        $this->expectException(AccessDeniedException::class);

        $this->acceptance($kit, null)->accept(Request::create('/accept'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function targets(): iterable
    {
        yield 'a path' => ['/forum?a=1', '/forum?a=1'];
        yield 'another host' => ['https://evil.example/', '/'];
        yield 'a protocol-relative address' => ['//evil.example/', '/'];
        yield 'a backslash' => ['/\\evil.example/', '/'];
        yield 'a relative path' => ['forum', '/'];
        yield 'a new line' => ["/forum\nx", '/'];
        yield 'delete' => ["/forum\x7F", '/'];
        yield 'the longest' => ['/'.str_repeat('a', 2047), '/'.str_repeat('a', 2047)];
        yield 'too long' => ['/'.str_repeat('a', 2048), '/'];
    }

    #[DataProvider('targets')]
    public function testTheTargetIsAPathOfTheSite(string $target, string $kept): void
    {
        self::assertSame($kept, Pages::target($target));
    }

    public function testThePageOfAPolicyShowsTheVersionInForce(): void
    {
        $kit = $this->kit();
        $kit->later('+1 day');
        $kit->terms->publish('terms', ['en' => 'Terms two', 'fr' => 'Conditions deux'], material: false);
        $kit->terms->publish('terms', ['en' => 'Terms three'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        $response = $this->policies($kit)->current(Request::create('/terms'), 'terms');
        $html = (string) $response->getContent();

        self::assertSame('max-age=300, public', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<h1>terms of use</h1>', $html);
        self::assertStringContainsString('<p>Terms two</p>', $html);
        self::assertStringContainsString('Version 2', $html);
        self::assertStringContainsString('In force since 2026-05-02', $html);
        self::assertStringContainsString('<a href="/terms/v2" aria-current="page">Version 2</a>', $html);
        self::assertStringContainsString('<a href="/terms/v3/changes/2">changes from version 2</a>', $html);
        self::assertStringContainsString('<a href="/terms/v2/changes/1">changes from version 1</a>', $html);
        self::assertStringNotContainsString('/terms/v1/changes', $html);
        self::assertStringContainsString('takes effect on 2026-06-01', $html);
        self::assertSame(2, substr_count($html, 'to accept again'));
    }

    public function testTheLicenceOfTheContributionsIsShownWithItsVersion(): void
    {
        $kit = new Kit(licensing: true);
        $kit->terms->publish('contributions', ['en' => 'Charter'], licence: 'CC-BY-SA-4.0');

        $page = (string) $this->policies($kit)->current(Request::create('/contributions'), 'contributions')->getContent();
        self::assertStringContainsString('<p>Your contributions are published under the licence CC-BY-SA-4.0.</p>', $page);
        self::assertStringNotContainsString('Your contributions', (string) $this->policies($this->kit())->current(Request::create('/terms'), 'terms')->getContent());

        $acceptance = (string) $this->acceptance($kit)->accept(Request::create('/accept'))->getContent();
        self::assertStringContainsString('<p>Your contributions are published under the licence CC-BY-SA-4.0.</p>', $acceptance);
        self::assertSame(1, substr_count($acceptance, 'Your contributions'));
    }

    public function testThePageOfAPolicyInTheLocaleOfTheRequest(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('terms', ['en' => 'Terms two', 'fr' => 'Conditions deux'], material: false);

        $request = Request::create('/terms');
        $request->setLocale('fr');

        self::assertStringContainsString('<p>Conditions deux</p>', (string) $this->policies($kit)->current($request, 'terms')->getContent());
    }

    public function testAnUpcomingAndAReplacedVersionAreSaidSo(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('terms', ['en' => 'Terms two'], effectiveAt: new DateTimeImmutable('2026-06-01'));
        $kit->terms->publish('privacy', ['en' => 'Privacy three']);

        $upcoming = $this->policies($kit)->version(Request::create('/terms/v2'), 'terms', 2);
        self::assertSame('max-age=86400, public', $upcoming->headers->get('Cache-Control'));
        self::assertStringContainsString('Takes effect on 2026-06-01', (string) $upcoming->getContent());

        $replaced = (string) $this->policies($kit)->version(Request::create('/privacy/v2'), 'privacy', 2)->getContent();
        self::assertStringContainsString('Replaced by a later version', $replaced);
        self::assertStringContainsString('<p>Privacy two</p>', $replaced);

        self::assertStringContainsString('In force since 2026-05-01', (string) $this->policies($kit)->version(Request::create('/terms/v1'), 'terms', 1)->getContent());
    }

    public function testAPolicyWhoseOnlyVersionIsUpcomingShowsIt(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'Soon'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        self::assertStringContainsString('<p>Soon</p>', (string) $this->policies($kit)->current(Request::create('/terms'), 'terms')->getContent());
    }

    public function testTheChangesFromOneVersionToAnother(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('terms', ['en' => "Terms <b>\n\nA new paragraph."]);

        $response = $this->policies($kit)->changes(Request::create('/terms/v2/changes/1'), 'terms', 2, 1);
        $html = (string) $response->getContent();

        self::assertSame('max-age=86400, public', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('Changes from version 1 to version 2', $html);
        self::assertStringContainsString('<ins>', $html);
        self::assertStringContainsString('A new paragraph.', $html);
        self::assertStringContainsString('href="/terms/v1"', $html);
        self::assertStringContainsString('href="/terms/v2"', $html);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function noChanges(): iterable
    {
        yield 'from a newer version' => ['terms', 1, 2];
        yield 'from the same version' => ['terms', 2, 2];
        yield 'an unknown version' => ['terms', 3, 1];
        yield 'from an unknown version' => ['terms', 2, 9];
        yield 'an unknown policy' => ['cookies', 2, 1];
    }

    #[DataProvider('noChanges')]
    public function testChangesThatCannotBeShownAreNotFound(string $policy, int $number, int $from): void
    {
        $kit = $this->kit();
        $kit->terms->publish('terms', ['en' => 'Terms two']);

        $this->expectException(NotFoundHttpException::class);

        $this->policies($kit)->changes(Request::create('/'), $policy, $number, $from);
    }

    public function testChangesWithoutTheDifferAreNotFound(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('terms', ['en' => 'Terms two']);

        $controller = new PolicyController($kit->pages(), $kit->policies, $kit->catalog, new PolicyTextRenderer(), $kit->clock, 'en');

        $this->expectException(NotFoundHttpException::class);

        $controller->changes(Request::create('/'), 'terms', 2, 1);
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function notFound(): iterable
    {
        yield 'an unknown policy' => ['cookies', null];
        yield 'a policy without version' => ['conduct', null];
        yield 'an unknown version' => ['terms', 2];
        yield 'a version of an unknown policy' => ['cookies', 1];
    }

    #[DataProvider('notFound')]
    public function testAnUnknownPolicyOrVersionIsNotFound(string $policy, ?int $number): void
    {
        $controller = $this->policies($this->kit());

        $this->expectException(NotFoundHttpException::class);

        null === $number ? $controller->current(Request::create('/'), $policy) : $controller->version(Request::create('/'), $policy, $number);
    }

    public function testAPageExtendsTheLayoutItIsGiven(): void
    {
        $kit = $this->kit();
        $old = $kit->catalog->find('terms', 1);
        self::assertNotNull($old);
        $context = ['old' => $old, 'new' => $old, 'changes' => ''];

        self::assertStringContainsString('noindex', (string) $kit->pages()->render('changes', $context, layout: PhilTermsBundle::ADMIN_LAYOUT)->getContent());
        self::assertStringNotContainsString('noindex', (string) $kit->pages()->render('changes', $context)->getContent());
        self::assertStringContainsString('noindex', (string) $kit->pages()->form('changes', $context, layout: PhilTermsBundle::ADMIN_LAYOUT)->getContent());
        self::assertSame(422, $kit->pages()->form('changes', $context, 422)->getStatusCode());
    }

    public function testAPolicyNoLongerDeclaredIsNotFoundEvenWithVersions(): void
    {
        $kit = $this->kit();
        $kit->versions->add(new PolicyVersion('former', 1, ['en' => 'x'], TextFormat::Text, $kit->clock->now(), $kit->clock->now(), true));

        $this->expectException(NotFoundHttpException::class);

        $this->policies($kit)->current(Request::create('/former'), 'former');
    }

    private function kit(): Kit
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'Terms <b>']);
        $kit->terms->publish('privacy', ['en' => 'Privacy one']);
        $kit->terms->publish('privacy', ['en' => 'Privacy two'], TextFormat::Text);

        return $kit;
    }

    private function acceptance(Kit $kit, ?string $member = '42'): AcceptanceController
    {
        return new AcceptanceController($kit->pages(), $kit->checker, $kit->terms, new FixedMember($member), $kit->texts, $kit->clock, 'en');
    }

    private function policies(Kit $kit): PolicyController
    {
        return new PolicyController($kit->pages(), $kit->policies, $kit->catalog, $kit->texts, $kit->clock, 'en');
    }
}
