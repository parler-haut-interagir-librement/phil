<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Phil\LegalNoticesBundle\Infrastructure\Privacy\ScheduleRetentionDurations;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\FixedSchedule;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Kit;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\ListedConsent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(DocumentController::class)]
final class DocumentControllerTest extends TestCase
{
    public function testTheVersionInForce(): void
    {
        $response = $this->controller()->current($this->request('fr_CA'), 'legal_notice');

        self::assertSame('legal_notice|1|<p>Publié par Example.</p>||[]|[]|1|2|2026-12-15|layout.html.twig', $response->getContent());
        self::assertSame(DocumentController::CURRENT_CACHE, $response->headers->get('Cache-Control'));
        self::assertSame('fr-CA', $response->headers->get('Content-Language'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testThePrivacyPolicyListsTheRetentionAndTheConsent(): void
    {
        $response = $this->controller()->current($this->request('fr'), 'privacy_policy');

        self::assertSame('privacy_policy|2|<p>Policy</p>||[{"label":"Comptes","period":"2 ans"},{"label":"orders","period":"the end of the warranty"},{"label":"logs","period":"pas encore fix\u00e9e"}]|[{"label":"Mesure","description":"Pages","necessary":false,"cookies":["_pk"]}]|2|-|2026-12-15|layout.html.twig', $response->getContent());
        self::assertSame('privacy_policy|2|<p>Policy</p>||[]|[]|2|-|2026-12-15|layout.html.twig', $this->controller(false, false)->current($this->request('fr'), 'privacy_policy')->getContent());
        self::assertSame('privacy_policy|2|<p>Policy</p>||[]|[{"label":"Audience","description":"Pages","necessary":false,"cookies":["_pk"]}]|2|-|2026-12-15|layout.html.twig', $this->controller(false)->current($this->request('de'), 'privacy_policy')->getContent());
        self::assertStringContainsString('|[{"label":"users","period":"2 years"}', (string) $this->controller(true, false)->current($this->request('en'), 'privacy_policy')->getContent());
    }

    public function testTheSectionsAreShownByDefault(): void
    {
        $notices = Kit::notices(
            new ScheduleRetentionDurations(new FixedSchedule()),
            new ListedConsent([new ConsentCategory('necessary', true, ['en' => 'Necessary']), new ConsentCategory('analytics', false, ['en' => 'Audience'])]),
        );
        $controller = new DocumentController($notices, $this->twig(), new MockClock(new DateTimeImmutable('2026-12-15')), 'layout.html.twig');

        self::assertSame('privacy_policy|2|<p>Policy</p>||[{"label":"users","period":"2 years"},{"label":"orders","period":"the end of the warranty"},{"label":"logs","period":"not set yet"}]|[{"label":"Necessary","description":null,"necessary":true,"cookies":[]},{"label":"Audience","description":null,"necessary":false,"cookies":[]}]|2|-|2026-12-15|layout.html.twig', $controller->current($this->request('en'), 'privacy_policy')->getContent());
    }

    public function testAVersionAtItsOwnAddress(): void
    {
        $response = $this->controller()->version($this->request('fr'), 'legal_notice', 2);

        self::assertSame('legal_notice|2|<p>Second</p>|Nouvel hébergeur|[]|[]|1|2|2026-12-15|layout.html.twig', $response->getContent());
        self::assertSame(DocumentController::VERSION_CACHE, $response->headers->get('Cache-Control'));
        self::assertStringContainsString('|New host|', (string) $this->controller()->version($this->request('de'), 'legal_notice', 2)->getContent());
    }

    public function testTheHistory(): void
    {
        $response = $this->controller()->history($this->request('en'), 'privacy_policy');

        self::assertSame('privacy_policy|2,1,|2|-|2026-12-15|layout.html.twig', $response->getContent());
        self::assertSame(DocumentController::CURRENT_CACHE, $response->headers->get('Cache-Control'));
        self::assertSame('en', $response->headers->get('Content-Language'));
    }

    public function testTheRouteNames(): void
    {
        self::assertSame('phil_legal_notices_legal_notice', DocumentController::route(DocumentType::LegalNotice));
        self::assertSame('phil_legal_notices_privacy_policy_history', DocumentController::route(DocumentType::PrivacyPolicy, 'history'));
    }

    public function testWhatIsNotThereIsNotFound(): void
    {
        $empty = new DocumentController(Kit::notices(), $this->twig(), new MockClock(new DateTimeImmutable('2026-10-01')), 'layout.html.twig');
        $cases = [
            'an unknown document' => fn (): Response => $this->controller()->current($this->request('en'), 'terms'),
            'nothing in force' => static fn (): Response => $empty->current(new Request(), 'legal_notice'),
            'a draft' => fn (): Response => $this->controller()->version($this->request('en'), 'legal_notice', 3),
            'a version that is none' => fn (): Response => $this->controller()->version($this->request('en'), 'privacy_policy', 9),
            'an unknown history' => fn (): Response => $this->controller()->history($this->request('en'), 'cookies'),
        ];

        foreach ($cases as $case => $call) {
            try {
                $call();
                self::fail($case.' was found.');
            } catch (NotFoundHttpException $notFoundHttpException) {
                self::assertSame(404, $notFoundHttpException->getStatusCode(), $case);
            }
        }

        $noVersion = new DocumentController(new LegalNotices(new ConfigDocumentSource(['legal_notice' => [Kit::version(1, 'draft', null, '2026-01-01', ['en' => 'a'])]]), new PlainTextBody(), new SiteIdentity()), $this->twig(), new MockClock(), 'layout.html.twig');
        $this->expectException(NotFoundHttpException::class);
        $noVersion->history(new Request(), 'legal_notice');
    }

    private function controller(bool $retention = true, bool $consent = true): DocumentController
    {
        $notices = Kit::notices(
            new ScheduleRetentionDurations(new FixedSchedule(), ['R-001' => ['fr' => 'Comptes']]),
            new ListedConsent([new ConsentCategory('analytics', false, ['en' => 'Audience', 'fr' => 'Mesure'], ['fr' => 'Pages'], ['_pk'])]),
        );

        return new DocumentController($notices, $this->twig(), new MockClock(new DateTimeImmutable('2026-12-15')), 'layout.html.twig', $retention, $consent);
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            '@PhilLegalNotices/document.html.twig' => '{{ type.value }}|{{ version.number }}|{{ html|raw }}|{{ summary }}|{{ retention|json_encode|raw }}|{{ consent|json_encode|raw }}|{{ in_force.number|default("-") }}|{{ upcoming.number|default("-") }}|{{ now|date("Y-m-d") }}|{{ layout }}',
            '@PhilLegalNotices/history.html.twig' => '{{ type.value }}|{% for version in versions %}{{ version.number }},{% endfor %}|{{ in_force.number|default("-") }}|{{ upcoming.number|default("-") }}|{{ now|date("Y-m-d") }}|{{ layout }}',
        ]), ['strict_variables' => true]);
    }

    private function request(string $locale): Request
    {
        $request = new Request();
        $request->setLocale($locale);

        return $request;
    }
}
