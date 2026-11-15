<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Phil\TermsBundle\PhilTermsBundle;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use Phil\TermsBundle\Tests\Unit\Fixture\RoleChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(TermsAdminController::class)]
final class AdminTest extends TestCase
{
    public function testThePoliciesWithTheirVersions(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x', 'fr' => 'y']);
        $kit->later('+1 hour');
        $kit->terms->publish('terms', ['en' => 'x'], effectiveAt: new DateTimeImmutable('2026-06-01 08:30'), material: false);

        $response = $this->controller($kit)->list(Request::create('/admin/terms/'));
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringContainsString('terms of use <small>(terms)</small>', $html);
        self::assertStringContainsString('privacy policy <small>(privacy)</small>', $html);
        self::assertStringContainsString('renewed every 30 days', $html);
        self::assertStringContainsString('<span data-slot="badge" data-variant="outline" class="isBadge isOutline">Not required</span>', $html);
        self::assertStringContainsString('<span data-slot="badge" data-variant="default" class="isBadge">To accept to go on</span>', $html);
        self::assertStringContainsString('class="isButton isOutline" href="/terms/publish">Publish a version</a>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Moments in the time zone America/Toronto.</caption>', $html);
        self::assertStringContainsString('<th data-slot="table-head" class="isTableHead" scope="col">Published</th>', $html);
        // Kept in UTC, shown in the time zone of the administration: 11:00 and 08:30 in UTC.
        self::assertStringContainsString('<td data-slot="table-cell" class="isTableCell">2026-05-01 07:00</td>', $html);
        self::assertStringContainsString('<td data-slot="table-cell" class="isTableCell">2026-06-01 04:30</td>', $html);
        self::assertStringContainsString('<td data-slot="table-cell" class="isTableCell">en, fr</td>', $html);
        self::assertSame(2, substr_count($html, 'No version yet.'));
        self::assertStringNotContainsString('data-slot="alert"', $html);
        self::assertStringContainsString('<p data-slot="alert-title" class="isAlertTitle"><strong>The version was published.</strong></p>', (string) $this->controller($kit)->list(Request::create('/?done=published'))->getContent());
        self::assertStringNotContainsString('data-slot="alert"', (string) $this->controller($kit)->list(Request::create('/admin/terms/?done=other'))->getContent());
    }

    public function testTheFormToPublishAVersion(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);

        $response = $this->controller($kit)->publish(Request::create('/admin/terms/terms/publish'), 'terms');
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Publish version 2', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringContainsString('name="texts[en]"', $html);
        self::assertStringContainsString('name="texts[fr]"', $html);
        self::assertStringContainsString('<select data-slot="native-select" data-size="default" id="phil-terms-format" name="format">', $html);
        self::assertStringContainsString('<option data-slot="native-select-option" value="ujo">UJO document</option>', $html);
        self::assertStringContainsString('<option data-slot="native-select-option" value="text" selected="">Plain text</option>', $html);
        self::assertStringContainsString('<input type="checkbox" data-slot="checkbox" id="phil-terms-material" name="material" value="1" aria-describedby="phil-terms-material-help">', $html);
        self::assertStringContainsString('<label data-slot="field-label" class="isFieldLabel" for="phil-terms-text-fr">Text (fr)</label>', $html);
        self::assertStringContainsString('Time zone America/Toronto', $html);
        $withoutUjo = (string) $this->controller($kit, ujo: false)->publish(Request::create('/'), 'terms')->getContent();
        self::assertStringNotContainsString('value="ujo"', $withoutUjo);
        self::assertStringContainsString('value="text" selected="">', $withoutUjo);
    }

    public function testThePolicyThatNamesTheLicenceAsksForItAlone(): void
    {
        $kit = new Kit(licensing: true);

        $html = (string) $this->controller($kit)->publish(Request::create('/'), 'contributions')->getContent();
        self::assertStringContainsString('<label data-slot="field-label" class="isFieldLabel" for="phil-terms-licence">Licence of the contributions</label>', $html);
        self::assertStringContainsString('<input data-slot="input" id="phil-terms-licence" type="text" name="licence" value="" required="" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9.+\-]*" aria-describedby="phil-terms-licence-help">', $html);
        self::assertStringContainsString('The SPDX identifier of the licence', $html);
        self::assertStringNotContainsString('name="licence"', (string) $this->controller($kit)->publish(Request::create('/'), 'terms')->getContent());

        $refused = $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'Charter'], 'format' => 'text', 'licence' => '  ']), 'contributions');
        self::assertSame(422, $refused->getStatusCode());
        self::assertStringContainsString('Name the licence of the contributions.', (string) $refused->getContent());

        $bad = $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'Charter'], 'format' => 'text', 'licence' => 'CC BY']), 'contributions');
        self::assertStringContainsString('&quot;CC BY&quot; is not an SPDX licence identifier.', (string) $bad->getContent());
        self::assertStringContainsString('value="CC BY"', (string) $bad->getContent());

        $response = $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'Charter'], 'format' => 'text', 'licence' => ' CC-BY-SA-4.0 ']), 'contributions');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('CC-BY-SA-4.0', $kit->catalog->latest('contributions')?->licence);
    }

    public function testAVersionIsPublished(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);

        $response = $this->controller($kit)->publish($kit->post('/admin/terms/terms/publish', [
            'texts' => ['en' => 'New terms', 'fr' => '  ', 'de' => 'ignored'],
            'format' => 'text',
            'effective_at' => '2026-06-01T09:00',
            'material' => '1',
        ]), 'terms');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/?done=published', $response->headers->get('Location'));
        $version = $kit->catalog->latest('terms');
        self::assertNotNull($version);
        self::assertSame(2, $version->number);
        self::assertSame(['en' => 'New terms'], $version->texts);
        self::assertTrue($version->material);
        self::assertSame(TextFormat::Text, $version->format);
        self::assertEquals(new DateTimeImmutable('2026-06-01 09:00', new DateTimeZone('America/Toronto')), $version->effectiveAt);
    }

    public function testAVersionIsPublishedNowAndMinorByDefault(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);

        $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'New']]), 'terms');

        $version = $kit->catalog->latest('terms');
        self::assertNotNull($version);
        self::assertFalse($version->material);
        self::assertEquals($kit->clock->now(), $version->effectiveAt);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'no text' => [['texts' => ['en' => '']], 'Write the text of one locale at least.'];
        yield 'a text not a string' => [['texts' => ['en' => ['x']]], 'Write the text of one locale at least.'];
        yield 'a bad format' => [['texts' => ['en' => 'x'], 'format' => 'html'], 'Choose a format.'];
        yield 'a bad document' => [['texts' => ['en' => 'x'], 'format' => 'ujo'], 'The text is not a UJO document.'];
        yield 'a bad moment' => [['texts' => ['en' => 'x'], 'effective_at' => 'tomorrow'], 'The moment it takes effect is not a date and a time.'];
        yield 'an impossible moment' => [['texts' => ['en' => 'x'], 'effective_at' => '2026-02-30T10:00'], 'The moment it takes effect is not a date and a time.'];
        yield 'a moment with seconds' => [['texts' => ['en' => 'x'], 'effective_at' => '2026-06-01T10:00:00'], 'The moment it takes effect is not a date and a time.'];
    }

    /**
     * @param array<string, mixed> $form
     */
    #[DataProvider('refused')]
    public function testARefusalIsShownOnTheForm(array $form, string $message): void
    {
        $kit = new Kit();
        $response = $this->controller($kit)->publish($kit->post('/', $form + ['effective_at' => ' 2026-06-01T10:00 ']), 'terms');
        $html = (string) $response->getContent();

        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesRegularExpression('~role="alert">\s*<p data-slot="alert-title" class="isAlertTitle"><strong>'.preg_quote(htmlspecialchars($message, \ENT_QUOTES | \ENT_HTML5), '~').'</strong></p>~', $html);
        self::assertSame([], $kit->catalog->versions('terms'));
    }

    public function testTheDraftIsKeptOnTheForm(): void
    {
        $kit = new Kit();
        $html = (string) $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'Kept <text>', 'fr' => 'Gardé'], 'format' => 'ujo', 'effective_at' => '2026-06-01T10:00', 'material' => '1']), 'terms')->getContent();

        self::assertStringContainsString('>Kept &lt;text&gt;</textarea>', $html);
        self::assertStringContainsString('>Gardé</textarea>', $html);
        self::assertStringContainsString('value="ujo" selected="">UJO document</option>', $html);
        self::assertStringContainsString('value="2026-06-01T10:00"', $html);
        self::assertStringContainsString('name="material" value="1" checked=""', $html);
        self::assertStringContainsString('Publish version 1', $html);
    }

    public function testAnUnknownPolicyGoesBackToTheList(): void
    {
        $kit = new Kit();
        $response = $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'x']]), 'cookies');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $kit = new Kit();

        $this->expectException(BadRequestHttpException::class);

        $this->controller($kit)->publish($kit->post('/', ['texts' => ['en' => 'x']], 'bad'), 'terms');
    }

    public function testAUserWithoutTheRoleIsDenied(): void
    {
        $kit = new Kit();

        foreach ([fn (): Response => $this->controller($kit, [])->list(Request::create('/')), fn (): Response => $this->controller($kit, [])->publish(Request::create('/'), 'terms')] as $call) {
            try {
                $call();
                self::fail('Allowed.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertSame(['ROLE_LEGAL'], $accessDeniedException->getAttributes());
            }
        }
    }

    /**
     * @param list<string> $roles
     */
    private function controller(Kit $kit, array $roles = ['ROLE_LEGAL'], bool $ujo = true): TermsAdminController
    {
        return new TermsAdminController($kit->pages(), $kit->policies, $kit->catalog, $kit->terms, new RoleChecker(null, $roles), 'ROLE_LEGAL', PhilTermsBundle::ADMIN_LAYOUT, ['en', 'fr'], 'America/Toronto', $ujo);
    }
}
