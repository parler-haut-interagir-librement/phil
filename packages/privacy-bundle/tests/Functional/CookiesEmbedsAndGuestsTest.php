<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Testing\AssertsDeclaredCookies;
use Phil\PrivacyBundle\Tests\Functional\App\RecordingGuestLinks;
use Phil\PrivacyBundle\Tests\Functional\App\RightsKernel;
use Phil\PrivacyBundle\Tests\Functional\App\TestModule;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(AssertsDeclaredCookies::class)]
#[CoversClass(GuestRequestController::class)]
final class CookiesEmbedsAndGuestsTest extends WebTestCase
{
    use AssertsDeclaredCookies;

    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];

    private const array GUESTS = ['guest_requests' => ['enabled' => true, 'sender' => RecordingGuestLinks::class, 'per_client' => ['limit' => 3]]];

    /**
     * @var array<string, mixed>
     */
    private static array $configuration = RightsKernel::CONFIGURATION;

    private ?KernelBrowser $browser = null;

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
        self::$configuration = RightsKernel::CONFIGURATION;
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
        return new RightsKernel(self::$configuration);
    }

    public function testTheCookiePolicyIsDrawnFromTheInventory(): void
    {
        $this->boot();
        $crawler = $this->client()->request('GET', '/privacy-choices/cookies');

        self::assertResponseIsSuccessful();
        self::assertSame(['Your choice', 'Session', 'Measurement'], $crawler->filter('h2')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame(['phil_privacy_consent', '_visit'], $crawler->filter('td code')->each(static fn (Crawler $node): string => $node->text()));
        self::assertStringContainsString('A measurement service', $crawler->filter('tbody')->eq(1)->text());
        self::assertStringContainsString('P13M', $crawler->filter('tbody')->eq(1)->text());
        self::assertStringContainsString('Nothing is written for this category.', (string) $this->client()->getResponse()->getContent());
        self::assertSelectorExists('a[href$="/privacy-choices/consent"]');
    }

    public function testTheBannerAndThePageOfTheChoicesListTheCookies(): void
    {
        $this->boot();
        $banner = $this->client()->request('GET', '/page');
        self::assertStringContainsString('Measurement : _visit', $banner->filter('aside details')->text());
        self::assertCount(1, $banner->filter('aside details a[href$="/privacy-choices/cookies"]'));

        $page = $this->client()->request('GET', '/privacy-choices/consent');
        self::assertStringContainsString('_visit', $page->filter('fieldset')->eq(1)->text());
    }

    public function testAFrameIsBlockedUntilItsCategoryIsGranted(): void
    {
        $this->boot();
        $crawler = $this->client()->request('GET', '/embed');

        self::assertCount(0, $crawler->filter('figure > iframe'));
        self::assertSame('/media/still.webp', $crawler->filter('.phil-privacy-embed__preview')->attr('src'));
        self::assertStringContainsString('“A talk” is hosted by YouTube.', $crawler->filter('.phil-privacy-embed__blocked p')->text());
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $crawler->filter('template')->filter('iframe')->attr('src') ?? $this->frameInTemplate((string) $this->client()->getResponse()->getContent()));
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $crawler->filter('a[rel~=external]')->attr('href'));
        self::assertSame('/embed', $crawler->filter('input[name=_target]')->attr('value'));
        self::assertCount(2, $crawler->filter('#policy table'));

        $this->client()->request('POST', '/privacy-choices/consent', ['decision' => 'grant', 'category' => 'analytics', '_token' => 'csrf-token', '_target' => '/embed'], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertSame('/embed', $this->client()->getResponse()->headers->get('Location'));
        self::assertResponseSetsDeclaredCookiesOnly($this->client()->getResponse());

        $granted = $this->client()->request('GET', '/embed');
        $frame = $granted->filter('figure > iframe');
        self::assertCount(1, $frame);
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $frame->attr('src'));
        self::assertSame('A talk', $frame->attr('title'));
        self::assertSame('lazy', $frame->attr('loading'));
        self::assertSame('strict-origin-when-cross-origin', $frame->attr('referrerpolicy'));
        self::assertSame('on', $this->client()->request('GET', '/page')->filter('#analytics')->text());
    }

    public function testGrantingOneCategoryKeepsTheOthersAsTheyWere(): void
    {
        $this->boot();
        $this->client()->request('POST', '/privacy-choices/consent', ['decision' => 'grant', 'category' => 'unknown', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);

        self::assertSame('off', $this->client()->request('GET', '/page')->filter('#analytics')->text());
        self::assertSame('no', $this->client()->request('GET', '/page')->filter('#pending')->text());
    }

    public function testTheTestHelperFailsOnAnUndeclaredCookie(): void
    {
        $this->boot();
        $this->client()->request('GET', '/tracker');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageIsOrContains('The response sets cookies the inventory does not declare: _tracker. Declare each');

        self::assertResponseSetsDeclaredCookiesOnly($this->client()->getResponse());
    }

    public function testTheTestHelperTakesAMessage(): void
    {
        $this->boot();
        $this->client()->request('GET', '/tracker');

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageIsOrContains('My message');

        self::assertResponseSetsDeclaredCookiesOnly($this->client()->getResponse(), 'My message');
    }

    public function testTheGuestPagesAreNotFoundWhileTheyAreOff(): void
    {
        $this->boot();
        $this->client()->request('GET', '/privacy-choices/request');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client()->request('GET', '/privacy-choices/request/'.str_repeat('a', 32));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAPersonWithoutAnAccountGetsTheirDataThroughTheLink(): void
    {
        $this->boot(self::GUESTS);
        $form = $this->client()->request('GET', '/privacy-choices/request');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $form->filter('input[name=kind]'));

        $sent = $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'Eloise@Example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('If this address can receive mail, a link is on its way.', $sent->filter('[role=status]')->text());
        [$request, $token] = $this->links()->sent[0];
        self::assertSame('Eloise@example.org', $request->email);

        $confirm = $this->client()->request('GET', '/privacy-choices/request/'.$request->id.'?token='.$token);
        self::assertResponseIsSuccessful();
        self::assertSame('no-referrer', $this->client()->getResponse()->headers->get('Referrer-Policy'));
        self::assertTrue($this->client()->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame($token, $confirm->filter('input[name=token]')->attr('value'));
        self::assertSame('access', $confirm->filter('input[name=kind]')->attr('value'));

        $this->client()->request('POST', '/privacy-choices/request/'.$request->id, ['token' => $token, 'kind' => 'access', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseIsSuccessful();
        self::assertSame('application/zip', $this->client()->getResponse()->headers->get('Content-Type'));
        self::assertSame(['subject' => 'Eloise@example.org'], json_decode(Unzip::files((string) $this->client()->getResponse()->getContent())['members.json'], true));

        $this->client()->request('POST', '/privacy-choices/request/'.$request->id, ['token' => $token, 'kind' => 'access', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSelectorTextContains('h1', 'This link no longer works');
    }

    public function testAPersonWithoutAnAccountErasesTheirDataThroughTheLink(): void
    {
        $this->boot(self::GUESTS);
        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'erasure', 'email' => 'a@example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        [$request, $token] = $this->links()->sent[0];

        $this->client()->request('GET', '/privacy-choices/request/'.$request->id.'?token=wrong');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('no-referrer', $this->client()->getResponse()->headers->get('Referrer-Policy'));

        $this->client()->request('GET', '/privacy-choices/request/'.$request->id.'?token='.$token);
        self::assertSame([], $this->module()->erased, 'Opening the link changes nothing.');

        $this->client()->request('POST', '/privacy-choices/request/'.$request->id, ['token' => $token, 'kind' => 'erasure', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Your data is erased');
        self::assertSame(['a@example.org'], $this->module()->erased);
    }

    public function testTheGuestFormIsGuardedAgainstAbuse(): void
    {
        $this->boot(self::GUESTS);

        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'a@example.org', '_token' => 'csrf-token'], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'everything', 'email' => 'a@example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'not an address', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('[role=alert]', 'This address cannot receive mail.');

        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'b@example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseIsSuccessful();
        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'c@example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseIsSuccessful();
        $this->client()->request('POST', '/privacy-choices/request', ['kind' => 'access', 'email' => 'd@example.org', '_token' => 'csrf-token'], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertGreaterThan(0, (int) $this->client()->getResponse()->headers->get('Retry-After'));
        self::assertSelectorTextContains('[role=alert]', 'Too many requests.');
        self::assertCount(2, $this->links()->sent);
    }

    private function frameInTemplate(string $html): ?string
    {
        return 1 === preg_match('#<template data-phil--privacy-bundle--embed-target="frame">\s*<iframe src="([^"]+)"#', $html, $match) ? $match[1] : null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function boot(array $configuration = []): void
    {
        self::$configuration = [...RightsKernel::CONFIGURATION, ...$configuration];
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $connection = self::getContainer()->get('database_connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
    }

    private function client(): KernelBrowser
    {
        self::assertInstanceOf(KernelBrowser::class, $this->browser);

        return $this->browser;
    }

    private function links(): RecordingGuestLinks
    {
        $links = self::getContainer()->get(RecordingGuestLinks::class);
        self::assertInstanceOf(RecordingGuestLinks::class, $links);

        return $links;
    }

    private function module(): TestModule
    {
        $module = self::getContainer()->get(TestModule::class);
        self::assertInstanceOf(TestModule::class, $module);

        return $module;
    }
}
