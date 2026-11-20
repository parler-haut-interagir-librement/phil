<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentVaryListener;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Routing\MountingRouteLoader;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use Phil\PrivacyBundle\Tests\Functional\App\RightsKernel;
use Phil\PrivacyBundle\Tests\Functional\App\TestModule;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(ConsentContext::class)]
#[CoversClass(ConsentController::class)]
#[CoversClass(ConsentCookie::class)]
#[CoversClass(ConsentVaryListener::class)]
#[CoversClass(PreferencesController::class)]
#[CoversClass(PrivacyPages::class)]
#[CoversClass(MountingRouteLoader::class)]
#[CoversClass(PrivacyRoutes::class)]
#[CoversClass(ConsentExtension::class)]
final class RightsTest extends WebTestCase
{
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];

    private const array FRESH = [...self::SAME_ORIGIN, 'HTTP_X_TEST_SIGN_IN' => 'fresh'];

    private const array STALE = [...self::SAME_ORIGIN, 'HTTP_X_TEST_SIGN_IN' => 'stale'];

    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('database_connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return RightsKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new RightsKernel();
    }

    public function testAFirstVisitorIsAskedAndNothingOptionalRuns(): void
    {
        $crawler = $this->client->request('GET', '/page');

        self::assertResponseIsSuccessful();
        self::assertSame('off', $crawler->filter('#analytics')->text());
        self::assertSame('yes', $crawler->filter('#pending')->text());
        self::assertCount(1, $crawler->filter('aside form[method=post]'));
        self::assertContains('Sec-GPC', $this->response()->getVary());
        self::assertContains('Cookie', $this->response()->getVary());
        self::assertNull($this->client->getCookieJar()->get('phil_privacy_consent'));
        self::assertSame('/page', $crawler->filter('input[name=_target]')->attr('value'));
    }

    public function testRefusingIsAsSimpleAsAccepting(): void
    {
        $buttons = $this->client->request('GET', '/page')->filter('aside button');

        self::assertCount(2, $buttons);
        self::assertSame('refuse', $buttons->eq(0)->attr('value'));
        self::assertSame('accept', $buttons->eq(1)->attr('value'));
        self::assertSame($buttons->eq(0)->attr('class'), $buttons->eq(1)->attr('class'));
        self::assertSame($buttons->eq(0)->attr('type'), $buttons->eq(1)->attr('type'));
    }

    public function testAcceptingWorksWithoutJavascriptAndComesBack(): void
    {
        $this->decide('accept', ['_target' => '/page']);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertSame('/page', $this->response()->headers->get('Location'));
        self::assertTrue($this->response()->headers->hasCacheControlDirective('no-store'));

        $cookie = $this->response()->headers->getCookies()[0];
        self::assertSame('phil_privacy_consent', $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertNull($cookie->getDomain());
        self::assertSame('/', $cookie->getPath());
        self::assertFalse($cookie->isSecure());
        self::assertGreaterThan(time() + 86400 * 170, $cookie->getExpiresTime());
        self::assertLessThan(time() + 86400 * 190, $cookie->getExpiresTime());

        $crawler = $this->client->request('GET', '/page');
        self::assertSame('on', $crawler->filter('#analytics')->text());
        self::assertSame('no', $crawler->filter('#pending')->text());
        self::assertCount(0, $crawler->filter('aside'));
    }

    public function testASecureSiteGetsASecureCookie(): void
    {
        $this->client->request('POST', 'https://localhost/privacy-choices/consent', ['decision' => 'accept', '_token' => 'csrf-token'], [], ['HTTP_ORIGIN' => 'https://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertTrue($this->response()->headers->getCookies()[0]->isSecure());
    }

    public function testRefusingIsRemembered(): void
    {
        $this->decide('refuse');
        self::assertSame('/privacy-choices/consent', $this->response()->headers->get('Location'));

        $crawler = $this->client->request('GET', '/page');
        self::assertSame('off', $crawler->filter('#analytics')->text());
        self::assertSame('no', $crawler->filter('#pending')->text());
    }

    public function testAConsentCanBeWithdrawnAtAnyTime(): void
    {
        $this->decide('accept');
        $page = $this->client->request('GET', '/privacy-choices/consent');
        self::assertResponseIsSuccessful();
        self::assertNotNull($page->filter('input[value=analytics]')->attr('checked'));

        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'save', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertSame('off', $this->client->request('GET', '/page')->filter('#analytics')->text());
    }

    public function testAChoiceOfCategoriesIsKept(): void
    {
        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'save', 'categories' => ['analytics', 'unknown'], '_token' => 'csrf-token'], [], self::SAME_ORIGIN);

        self::assertSame('on', $this->client->request('GET', '/page')->filter('#analytics')->text());
    }

    public function testAJsonAnswerForTheController(): void
    {
        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'accept', '_token' => 'csrf-token'], [], [...self::SAME_ORIGIN, 'HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['granted' => ['analytics'], 'version' => '2'], json_decode((string) $this->response()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
        self::assertCount(1, $this->response()->headers->getCookies());
        self::assertTrue($this->response()->headers->hasCacheControlDirective('no-store'));
    }

    public function testADecisionFromAnotherSiteIsRefused(): void
    {
        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'accept', '_token' => 'csrf-token'], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertCount(0, $this->response()->headers->getCookies());
    }

    public function testAnUnknownDecisionIsRefused(): void
    {
        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'maybe', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testATargetOnAnotherHostIsNotFollowed(): void
    {
        foreach (['https://evil.example', '//evil.example', '/\\evil.example', "/a\nb", '/'.str_repeat('a', 2048)] as $target) {
            $this->decide('refuse', ['_target' => $target]);
            self::assertSame('/privacy-choices/consent', $this->response()->headers->get('Location'), $target);
        }

        $this->decide('refuse', ['_target' => '/'.str_repeat('a', 2047)]);
        self::assertSame('/'.str_repeat('a', 2047), $this->response()->headers->get('Location'));
    }

    public function testTheGlobalPrivacyControlRefusesEverythingAndIsNotAsked(): void
    {
        $this->decide('accept');
        $crawler = $this->client->request('GET', '/page', [], [], ['HTTP_SEC_GPC' => '1']);

        self::assertSame('off', $crawler->filter('#analytics')->text());
        self::assertSame('no', $crawler->filter('#pending')->text());
        self::assertCount(0, $crawler->filter('aside'));

        $page = $this->client->request('GET', '/privacy-choices/consent', [], [], ['HTTP_SEC_GPC' => '1']);
        self::assertCount(1, $page->filter('[role=status]'));

        $this->client->request('POST', '/privacy-choices/consent', ['decision' => 'accept', '_token' => 'csrf-token'], [], [...self::SAME_ORIGIN, 'HTTP_SEC_GPC' => '1', 'HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['granted' => [], 'version' => '2'], json_decode((string) $this->response()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testASignalOtherThanOneIsNoSignal(): void
    {
        $this->decide('accept');

        self::assertSame('on', $this->client->request('GET', '/page', [], [], ['HTTP_SEC_GPC' => '0'])->filter('#analytics')->text());
    }

    public function testATamperedCookieGrantsNothing(): void
    {
        $this->client->getCookieJar()->set(new Cookie('phil_privacy_consent', 'forged.value'));

        self::assertSame('off', $this->client->request('GET', '/page')->filter('#analytics')->text());
    }

    public function testADecisionLeavesADatedProofOfTheVersion(): void
    {
        $this->decide('accept');

        $connection = self::getContainer()->get('database_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $row = $connection->fetchAssociative('SELECT version, granted, decided_at FROM '.PrivacyTables::PROOFS);
        self::assertIsArray($row);
        self::assertSame('2', $row['version']);
        self::assertSame('["analytics"]', $row['granted']);
        self::assertIsString($row['decided_at']);
        self::assertStringStartsWith(new DateTimeImmutable()->format('Y-'), $row['decided_at']);
    }

    public function testTheRetentionJobIsWiredAndRefusesNothingItCanPurge(): void
    {
        $job = self::getContainer()->get(RetentionJob::class);
        self::assertInstanceOf(RetentionJob::class, $job);

        self::assertSame(['R-1' => 0], $job->run());
    }

    public function testThePreferencesNeedASignedInMember(): void
    {
        $this->client->request('GET', '/privacy-choices/');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testThePreferencesOfferBothRights(): void
    {
        $crawler = $this->client->request('GET', '/privacy-choices/', [], [], self::FRESH);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->response()->headers->hasCacheControlDirective('no-store'));
        self::assertCount(1, $crawler->filter('form[action$="/privacy-choices/export"]'));
        self::assertCount(1, $crawler->filter('a[href$="/privacy-choices/erasure"]'));
    }

    public function testTrackedEmailsAreNotAskedUntilTheApplicationTurnsThemOn(): void
    {
        $crawler = $this->client->request('GET', '/privacy-choices/', [], [], self::FRESH);
        self::assertCount(0, $crawler->filter('form[action$="/privacy-choices/email-tracking"]'));

        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token', 'opens' => '1'], [], self::FRESH);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAMemberDownloadsTheirData(): void
    {
        $this->client->request('POST', '/privacy-choices/export', ['_token' => 'csrf-token'], [], self::FRESH);

        self::assertResponseIsSuccessful();
        $files = Unzip::files((string) $this->response()->getContent());
        self::assertSame(['README.txt', 'members.json'], array_keys($files));
        self::assertSame(['subject' => 'member-1'], json_decode($files['members.json'], true, 512, \JSON_THROW_ON_ERROR));
        self::assertStringContainsString('members.json : 1 entry.', $files['README.txt']);
        self::assertSame('attachment; filename="personal-data.zip"', $this->response()->headers->get('Content-Disposition'));
        self::assertTrue($this->response()->headers->hasCacheControlDirective('no-store'));
        self::assertSame('nosniff', $this->response()->headers->get('X-Content-Type-Options'));
        self::assertSame('application/zip', $this->response()->headers->get('Content-Type'));
    }

    public function testAnExportNeedsTheFormsToken(): void
    {
        $this->client->request('POST', '/privacy-choices/export', ['_token' => 'csrf-token'], [], ['HTTP_X_TEST_SIGN_IN' => 'fresh', 'HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAStaleSessionMustProveItself(): void
    {
        $this->client->request('GET', '/privacy-choices/erasure', [], [], self::STALE);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('POST', '/privacy-choices/erasure', ['_token' => 'csrf-token'], [], self::STALE);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertNull($this->deletions()->openFor(DataSubject::fromString('member-1')));
    }

    public function testTheConfirmationPageAsksBeforeAnythingHappens(): void
    {
        $crawler = $this->client->request('GET', '/privacy-choices/erasure', [], [], self::FRESH);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[method=post] button'));
        self::assertNull($this->deletions()->openFor(DataSubject::fromString('member-1')));
    }

    public function testAMemberAsksForTheDeletionThenCancelsIt(): void
    {
        $this->client->request('POST', '/privacy-choices/erasure', ['_token' => 'csrf-token'], [], self::FRESH);
        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertSame('/privacy-choices/', $this->response()->headers->get('Location'));
        self::assertInstanceOf(AccountDeletion::class, $this->deletions()->openFor(DataSubject::fromString('member-1')));

        $crawler = $this->client->request('GET', '/privacy-choices/', [], [], self::STALE);
        self::assertCount(1, $crawler->filter('form[action$="/erasure/cancel"]'));
        self::assertCount(0, $crawler->filter('a[href$="/privacy-choices/erasure"]'));

        $this->client->request('POST', '/privacy-choices/erasure/cancel', ['_token' => 'csrf-token'], [], self::STALE);
        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertNull($this->deletions()->openFor(DataSubject::fromString('member-1')));
        $module = self::getContainer()->get(TestModule::class);
        self::assertInstanceOf(TestModule::class, $module);
        self::assertSame([], $module->erased);
    }

    public function testAnErasureNeedsTheFormsToken(): void
    {
        foreach (['/privacy-choices/erasure', '/privacy-choices/erasure/cancel'] as $path) {
            $this->client->request('POST', $path, ['_token' => 'csrf-token'], [], ['HTTP_X_TEST_SIGN_IN' => 'fresh', 'HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST, $path);
        }
    }

    public function testNoPageOfTheBundleCarriesTheMemberInItsAddress(): void
    {
        foreach (PrivacyRoutes::collection()->all() as $name => $route) {
            // The link of a request without an account and the progress of an erasure carry a random identifier, never a person.
            self::assertStringNotContainsString('{', \in_array($name, [PrivacyRoutes::GUEST_CONFIRM, PrivacyRoutes::ERASURE_PROGRESS], true) ? str_replace(['{id}', '{_format}'], '', $route->getPath()) : $route->getPath());
        }
    }

    public function testTheRoutesAreInFrontOfTheApplicationCatchAll(): void
    {
        $this->client->request('GET', '/privacy-choices/consent');

        self::assertSelectorExists('form[action$="/privacy-choices/consent"]');
    }

    public function testTheDeletionRunsToTheEndThroughTheContainer(): void
    {
        $store = self::getContainer()->get(AccountDeletionStore::class);
        self::assertInstanceOf(AccountDeletionStore::class, $store);
        $past = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $store->save(new AccountDeletion(str_repeat('a', 32), DataSubject::fromString('member-1'), DeletionPlace::Requested, $past, $past));

        self::assertSame(DeletionPlace::Erased, $this->deletions()->advance(str_repeat('a', 32)));
        $module = self::getContainer()->get(TestModule::class);
        self::assertInstanceOf(TestModule::class, $module);
        self::assertSame(['member-1'], $module->erased);
    }

    /**
     * @param array<string, string> $extra
     */
    private function decide(string $decision, array $extra = []): void
    {
        $this->client->request('POST', '/privacy-choices/consent', ['decision' => $decision, '_token' => 'csrf-token', ...$extra], [], self::SAME_ORIGIN);
    }

    private function response(): Response
    {
        return $this->client->getResponse();
    }

    private function deletions(): AccountDeletions
    {
        $deletions = self::getContainer()->get(AccountDeletions::class);
        self::assertInstanceOf(AccountDeletions::class, $deletions);

        return $deletions;
    }
}
