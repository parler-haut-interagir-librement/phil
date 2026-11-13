<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Functional;

use DateTimeImmutable;
use Override;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\Twig\AccessExtension;
use Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent;
use Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The bundle in an application: the Twig functions, the voters, the summary and the banner drawn with the recipes of
 * the kit, and "view as this member" started and stopped by its plain forms in the session.
 */
#[CoversClass(AccessExtension::class)]
#[CoversClass(AccessSummaryComponent::class)]
#[CoversClass(ViewAsBannerComponent::class)]
final class AccessBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $spaces = self::getContainer()->get(Spaces::class);
        $this->assertInstanceOf(Spaces::class, $spaces);
        $spaces->addAdmin(MemberId::of('ada'));
        $spaces->addMember(MemberId::of('ann'));

        $items = self::getContainer()->get(Items::class);
        $this->assertInstanceOf(Items::class, $items);
        $items->register(new Page('1'), MemberId::of('olga'), Visibility::Members);
        $sharing = self::getContainer()->get(Sharing::class);
        $this->assertInstanceOf(Sharing::class, $sharing);
        $sharing->share(new Page('1'), 'edit', MemberId::of('ann'), new DateTimeImmutable('2026-12-01 00:00:00 UTC'));
        $sharing->share(new Page('1'), 'view', AccessGroup::of('supporters', [EntryPoint::Purchase]));
    }

    public function testAVisitor(): void
    {
        $page = $this->page();

        self::assertSame('no', $page->filter('#can-view')->text());
        self::assertSame('no', $page->filter('#granted')->text());
        self::assertSame('members', $page->filter('#visibility')->text());
        self::assertSame('nobody', $page->filter('#viewing-as')->text());
        self::assertCount(0, $page->filter('.phil-access-summary'));
        self::assertCount(0, $page->filter('.phil-access-view-as'));
        self::assertCount(0, $page->filter('#view-as'));
    }

    public function testAMemberWithAShare(): void
    {
        $this->client->loginUser($this->member('ann'));
        $page = $this->page();

        self::assertSame('yes', $page->filter('#can-view')->text());
        self::assertSame('yes', $page->filter('#can-edit')->text());
        self::assertSame('no', $page->filter('#granted')->text());
        self::assertCount(0, $page->filter('.phil-access-summary'));
    }

    public function testTheOwnerSeesTheSummary(): void
    {
        $this->client->loginUser($this->member('olga'));
        $page = $this->page();

        self::assertSame('yes', $page->filter('#granted')->text());
        $summary = $page->filter('section.phil-access-summary');
        self::assertCount(1, $summary);
        self::assertSame('Visible to Members', $summary->filter('.phil-access-summary__visibility')->text());
        self::assertCount(1, $summary->filter('[data-slot="badge"]')->first());
        self::assertSame('Shared with', $summary->filter('caption[data-slot="table-caption"]')->text());
        self::assertSame(['With', 'Level', 'Until'], $summary->filter('th[data-slot="table-head"]')->each(static fn (Crawler $cell): string => $cell->text()));
        self::assertSame(
            [['Member ann', 'edit', '2026-12-01 00:00 UTC'], ['Group supporters', 'view', 'No end']],
            $summary->filter('tbody tr')->each(static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => $cell->text())),
        );
        self::assertSame('2026-12-01T00:00:00+00:00', $summary->filter('time')->attr('datetime'));
    }

    public function testAnItemSharedWithNobody(): void
    {
        $sharing = self::getContainer()->get(Sharing::class);
        $this->assertInstanceOf(Sharing::class, $sharing);
        $sharing->unshare(new Page('1'), 'edit', MemberId::of('ann'));
        $sharing->unshare(new Page('1'), 'view', AccessGroup::of('supporters', [EntryPoint::Purchase]));

        $this->client->loginUser($this->member('olga'));

        self::assertSame('Shared with nobody.', $this->page()->filter('.phil-access-summary__empty')->text());
    }

    public function testAnAdminViewsTheSiteAsAMember(): void
    {
        $this->client->loginUser($this->member('ada'));
        $page = $this->page();
        self::assertSame('yes', $page->filter('#can-view')->text());
        self::assertSame('no', $page->filter('#can-edit')->text());

        $this->client->submit($page->filter('#view-as')->form());
        self::assertResponseRedirects('/page', 303);
        $page = $this->client->followRedirect();

        self::assertSame('ann', $page->filter('#viewing-as')->text());
        self::assertSame('yes', $page->filter('#can-view')->text());
        self::assertSame('no', $page->filter('#can-edit')->text(), 'the view only reads');
        $banner = $page->filter('.phil-access-view-as [data-slot="alert"]');
        self::assertCount(1, $banner);
        self::assertSame('status', $banner->attr('role'));
        self::assertSame('You are viewing the site as member ann', $banner->filter('[data-slot="alert-title"]')->text());
        self::assertSame('Stop viewing as this member', $banner->filter('button[type="submit"]')->text());

        $this->client->submit($banner->filter('form')->form());
        self::assertResponseRedirects('/page', 303);
        $page = $this->client->followRedirect();

        self::assertSame('nobody', $page->filter('#viewing-as')->text());
        self::assertCount(0, $page->filter('.phil-access-view-as'));
    }

    public function testAMemberMayNotViewAsAnother(): void
    {
        $this->client->loginUser($this->member('ann'));
        $this->client->request('POST', '/_access/view-as/start/olga', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAGroupMembershipEndsOnItsOwn(): void
    {
        $groups = self::getContainer()->get(AccessGroups::class);
        $this->assertInstanceOf(AccessGroups::class, $groups);
        $groups->join('supporters', MemberId::of('sid'), EntryPoint::Purchase);
        $clock = self::getContainer()->get('clock');
        $this->assertInstanceOf(MockClock::class, $clock);

        self::assertSame(['supporters'], $groups->groupsOf(MemberId::of('sid')));
        $clock->modify('+1 month');
        self::assertSame([], $groups->groupsOf(MemberId::of('sid')));
    }

    private function member(string $identifier): InMemoryUser
    {
        return new InMemoryUser($identifier, 'p', ['ROLE_USER']);
    }

    private function page(): Crawler
    {
        $page = $this->client->request('GET', '/page');
        self::assertResponseIsSuccessful();

        return $page;
    }
}
