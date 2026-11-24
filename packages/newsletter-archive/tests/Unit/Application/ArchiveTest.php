<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Application\Exception\NotArchivable;
use Phil\NewsletterArchive\Application\LinkTokens;
use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Tests\Fixture\Stage;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Nothing enters the archive on its own: a sent campaign is published or shared when an editor says so, its web
 * version frozen then; unpublishing withdraws the issue and its link; the slug of an issue is clean and stays.
 */
#[CoversClass(Archive::class)]
#[CoversClass(ArchiveReader::class)]
#[CoversClass(NotArchivable::class)]
#[CoversClass(LinkTokens::class)]
final class ArchiveTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testASentCampaignIsNotArchivedUntilItIsPublished(): void
    {
        $this->stage->campaign('c1', 'Hello world');

        self::assertNull($this->stage->issues->get('c1'));
        self::assertSame(0, $this->stage->reader->page(new ArchiveFilter('default'))->total);

        $issue = $this->stage->archive->publish('c1', ['events', 'events']);

        self::assertSame('hello-world', $issue->slug);
        self::assertSame(['events'], $issue->tagValues());
        self::assertSame('News', $issue->listName);
        self::assertSame('news', $issue->listKey);
        self::assertSame('l1', $issue->listId);
        self::assertSame('This month in short', $issue->preheader);
        self::assertEquals(new DateTimeImmutable('2026-11-01 09:00:00 UTC'), $issue->sentAt);
        self::assertEquals($this->stage->clock->now(), $issue->publishedAt);
        self::assertEquals($this->stage->clock->now(), $issue->renderedAt);
        self::assertStringContainsString('<strong>readers</strong>', $issue->html);
        self::assertSame($issue->campaignId, $this->stage->reader->issue('default', 'hello-world')?->campaignId);
        self::assertNull($this->stage->reader->issue('other', 'hello-world'));
    }

    public function testAnUnknownOrUnsentCampaignIsRefused(): void
    {
        $this->stage->campaign('draft', 'A draft', null);

        try {
            $this->stage->archive->publish('draft');
            self::fail('A draft was published.');
        } catch (NotArchivable $notArchivable) {
            self::assertSame('The campaign "draft" is not sent: only a sent campaign is archived.', $notArchivable->getMessage());
        }

        try {
            $this->stage->archive->share('draft');
            self::fail('A draft was shared.');
        } catch (NotArchivable) {
            self::assertNull($this->stage->issues->get('draft'));
        }

        $this->expectExceptionMessageIsOrContains('No campaign "none".');
        $this->stage->archive->publish('none');
    }

    public function testPublishingAgainRefreshesTheWebVersionAndKeepsTheSlugTheDateAndTheTags(): void
    {
        $this->stage->campaign('c1', 'Hello world');
        $this->stage->archive->publish('c1', ['events']);

        $first = $this->stage->clock->now();
        $this->stage->clock->modify('+1 day');
        $this->stage->connection->update('phil_newsletter_campaign', ['body' => 'Changed'], ['id' => 'c1']);

        $issue = $this->stage->archive->publish('c1');

        self::assertSame('hello-world', $issue->slug);
        self::assertSame(['events'], $issue->tagValues());
        self::assertEquals($first, $issue->publishedAt);
        self::assertEquals($this->stage->clock->now(), $issue->renderedAt);
        self::assertSame('<p>Changed</p>', $issue->html);
        self::assertSame('news', $issue->listKey);
        self::assertSame('News', $issue->listName);

        self::assertSame(['news'], $this->stage->archive->publish('c1', ['news'])->tagValues());
    }

    public function testUnpublishingWithdrawsTheIssueAndItsLink(): void
    {
        $this->stage->campaign('c1', 'Hello world');
        $this->stage->archive->publish('c1');

        $token = $this->stage->archive->share('c1');

        self::assertInstanceOf(ArchivedIssue::class, $this->stage->reader->shared($token));

        $this->stage->archive->unpublish('c1');

        self::assertNull($this->stage->reader->issue('default', 'hello-world'));
        self::assertNull($this->stage->reader->shared($token));
        self::assertSame([], $this->stage->reader->page(new ArchiveFilter('default'))->issues);
        self::assertInstanceOf(ArchivedIssue::class, $this->stage->issues->get('c1'));

        $this->stage->archive->unpublish('none');
        $this->stage->archive->revokeLink('none');
        self::assertNull($this->stage->issues->get('none'));
    }

    public function testALinkWorksUntilItIsRevokedOrReplaced(): void
    {
        $this->stage->campaign('c1', 'Hello world');
        $first = $this->stage->archive->share('c1');

        self::assertTrue(LinkTokens::isWellFormed($first));
        self::assertSame('c1', $this->stage->reader->shared($first)?->campaignId);
        self::assertFalse($this->stage->issues->get('c1')?->isPublished());
        self::assertNull($this->stage->reader->issue('default', 'hello-world'));
        self::assertSame(LinkTokens::hash($first), $this->stage->issues->get('c1')?->linkHash);

        $second = $this->stage->archive->share('c1');
        self::assertNotSame($first, $second);
        self::assertNull($this->stage->reader->shared($first));
        self::assertSame('c1', $this->stage->reader->shared($second)?->campaignId);

        $this->stage->archive->revokeLink('c1');
        self::assertNull($this->stage->reader->shared($second));
        self::assertNull($this->stage->reader->shared('not a token'));
        self::assertNull($this->stage->reader->shared(str_repeat('A', 43)));
    }

    public function testRetagging(): void
    {
        $this->stage->campaign('c1', 'Hello world');
        $this->stage->archive->publish('c1', ['events']);
        $this->stage->archive->retag('c1', []);

        self::assertSame([], $this->stage->issues->get('c1')?->tagValues());

        $this->stage->archive->retag('c1', ['news', 'games']);
        self::assertSame(['games', 'news'], $this->stage->issues->get('c1')->tagValues());

        try {
            $this->stage->archive->retag('c1', ['Not a tag']);
            self::fail('A bad tag was accepted.');
        } catch (InvalidArchiveValue) {
            self::assertSame(['games', 'news'], $this->stage->issues->get('c1')?->tagValues());
        }

        $this->expectExceptionMessageIsOrContains('The campaign "none" is not in the archive.');
        $this->stage->archive->retag('none', []);
    }

    public function testSlugsOfTheSameSubjectAreSetApart(): void
    {
        $slugs = [];

        for ($i = 1; $i <= 21; ++$i) {
            $this->stage->campaign('c'.$i, 'Monthly news');
            $slugs[] = $this->stage->archive->publish('c'.$i)->slug;
        }

        self::assertSame('monthly-news', $slugs[0]);
        self::assertSame('monthly-news-2', $slugs[1]);
        self::assertSame('monthly-news-20', $slugs[19]);
        self::assertSame('monthly-news-'.substr(hash('sha256', 'c21'), 0, 12), $slugs[20]);
    }

    public function testASubjectWithoutLettersTakesAPlainSlug(): void
    {
        $this->stage->campaign('c1', '!!!');

        self::assertSame('issue', $this->stage->archive->publish('c1')->slug);
    }

    public function testALongSubjectGivesASlugCutBetweenTwoWords(): void
    {
        $this->stage->campaign('c1', str_repeat('word ', 40));

        $slug = $this->stage->archive->publish('c1')->slug;
        self::assertLessThanOrEqual(120, \strlen($slug));
        self::assertStringEndsWith('word', $slug);
    }

    public function testASentCampaignWithoutTheInstantItWasSentAtIsRefused(): void
    {
        $this->stage->campaign('c1', 'Odd');
        $this->stage->campaigns->save(Campaign::restore($this->stage->campaigns->get('c1') ?? self::fail(), CampaignStatus::Sent, null, null));

        $this->expectException(NotArchivable::class);
        $this->stage->archive->publish('c1');
    }

    public function testACampaignNotSentIsRefusedWhateverItsInstants(): void
    {
        $this->stage->campaign('c1', 'Odd');
        $this->stage->campaigns->save(Campaign::restore($this->stage->campaigns->get('c1') ?? self::fail(), CampaignStatus::Cancelled, null, new DateTimeImmutable('2026-11-01 09:00:00 UTC')));

        $this->expectException(NotArchivable::class);
        $this->stage->archive->publish('c1');
    }

    public function testSharingAnIssueKeepsItsWebVersionAsItWasFrozen(): void
    {
        $this->stage->campaign('c1', 'Hello world');
        $frozen = $this->stage->archive->publish('c1');
        $renderedAt = $frozen->renderedAt;

        $this->stage->clock->modify('+1 day');
        $this->stage->connection->update('phil_newsletter_campaign', ['body' => 'Changed'], ['id' => 'c1']);
        $this->stage->archive->share('c1');

        $issue = $this->stage->issues->get('c1');
        self::assertInstanceOf(ArchivedIssue::class, $issue);
        self::assertEquals($renderedAt, $issue->renderedAt);
        self::assertStringContainsString('<strong>readers</strong>', $issue->html);
    }

    public function testASlugIsCutAtTwoHundredCharactersAndNeverEndsWithADash(): void
    {
        $stage = new Stage(255);
        $stage->campaign('c1', str_repeat('wordsß ', 28));
        $stage->campaign('c2', str_repeat('wordß ', 30));

        $dashed = $stage->archive->publish('c1')->slug;
        self::assertSame(199, \strlen($dashed));
        self::assertStringEndsWith('sss', $dashed);
        self::assertSame(200, \strlen($stage->archive->publish('c2')->slug));
    }

    public function testACampaignOfAListThatIsGoneIsStillArchived(): void
    {
        $this->stage->campaign('c1', 'Orphan', listId: 'gone');

        $issue = $this->stage->archive->publish('c1');
        self::assertSame('', $issue->listKey);
        self::assertSame('', $issue->listName);
        self::assertSame([], $this->stage->reader->lists('default'));
    }

    public function testTheReaderListsTheListsAndTagsOfPublishedIssues(): void
    {
        $this->stage->campaign('c1', 'One');
        $this->stage->campaign('c2', 'Two', listId: 'l2');
        $this->stage->archive->publish('c1', ['news']);
        $this->stage->archive->publish('c2', ['events', 'news']);
        $this->stage->campaign('c3', 'Three', listId: 'l2');
        $this->stage->archive->share('c3');

        self::assertSame(['events' => 'Events', 'news' => 'News'], $this->stage->reader->lists('default'));
        self::assertSame(['events', 'news'], $this->stage->reader->tags('default'));
        self::assertSame([], $this->stage->reader->tags('other'));
    }
}
