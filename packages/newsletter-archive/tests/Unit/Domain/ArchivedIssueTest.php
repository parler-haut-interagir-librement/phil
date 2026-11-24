<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An issue is published and shared explicitly; unpublishing withdraws both; its values are checked.
 */
#[CoversClass(ArchivedIssue::class)]
final class ArchivedIssueTest extends TestCase
{
    private const string HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testANewIssueIsNotPublic(): void
    {
        $issue = $this->issue();

        self::assertFalse($issue->isPublished());
        self::assertFalse($issue->hasLink());
        self::assertFalse($issue->isPublic());
        self::assertNull($issue->publishedAt);
        self::assertSame('hello-world', $issue->slug);
        self::assertSame(['events', 'news'], $issue->tagValues());
    }

    public function testPublishingKeepsTheFirstInstant(): void
    {
        $issue = $this->issue();
        $issue->publish(new DateTimeImmutable('2026-11-02'));
        $issue->publish(new DateTimeImmutable('2026-11-03'));

        self::assertTrue($issue->isPublished());
        self::assertTrue($issue->isPublic());
        self::assertEquals(new DateTimeImmutable('2026-11-02'), $issue->publishedAt);
    }

    public function testSharingThenRevoking(): void
    {
        $issue = $this->issue();
        $issue->share(self::HASH, new DateTimeImmutable('2026-11-02'));

        self::assertTrue($issue->hasLink());
        self::assertTrue($issue->isPublic());
        self::assertFalse($issue->isPublished());
        self::assertSame(self::HASH, $issue->linkHash);
        self::assertEquals(new DateTimeImmutable('2026-11-02'), $issue->linkIssuedAt);

        $issue->revokeLink();
        self::assertFalse($issue->hasLink());
        self::assertNull($issue->linkIssuedAt);
        self::assertFalse($issue->isPublic());
    }

    public function testUnpublishingWithdrawsTheLinkToo(): void
    {
        $issue = $this->issue();
        $issue->publish(new DateTimeImmutable('2026-11-02'));
        $issue->share(self::HASH, new DateTimeImmutable('2026-11-02'));
        $issue->unpublish();

        self::assertFalse($issue->isPublished());
        self::assertFalse($issue->hasLink());
        self::assertNull($issue->linkHash);
        self::assertNull($issue->linkIssuedAt);
    }

    public function testAHashIsSha256InLowercaseHexadecimal(): void
    {
        $this->expectExceptionMessageIsOrContains('The hash of a link is SHA-256 in lowercase hexadecimal.');
        $this->issue()->share(strtoupper(self::HASH), new DateTimeImmutable());
    }

    public function testAShortHashIsRefused(): void
    {
        $this->expectException(InvalidArchiveValue::class);
        $this->issue()->share(substr(self::HASH, 1), new DateTimeImmutable());
    }

    public function testAHashHasNothingAroundIt(): void
    {
        foreach (['a'.self::HASH, self::HASH.'a', self::HASH."\n"] as $hash) {
            try {
                $this->issue()->share($hash, new DateTimeImmutable());
                self::fail('The hash '.json_encode($hash).' was accepted.');
            } catch (InvalidArchiveValue) {
                self::assertTrue(true);
            }
        }
    }

    public function testRestoringDropsTheInstantOfALinkThatIsNotThere(): void
    {
        $at = new DateTimeImmutable('2026-11-02');
        $issue = ArchivedIssue::restore($this->issue(), $at, null, $at);
        self::assertSame($at, $issue->publishedAt);
        self::assertNull($issue->linkIssuedAt);

        $shared = ArchivedIssue::restore($this->issue(), null, self::HASH, $at);
        self::assertSame($at, $shared->linkIssuedAt);
        self::assertSame(self::HASH, $shared->linkHash);
    }

    public function testRefreshingFreezesTheWebVersionAgain(): void
    {
        $issue = $this->issue();
        $at = new DateTimeImmutable('2026-11-05');
        $issue->refresh('l2', 'events', 'Events', 'New subject', 'New preheader', '<p>New</p>', $at);

        self::assertSame(['l2', 'events', 'Events', 'New subject', 'New preheader', '<p>New</p>'], [$issue->listId, $issue->listKey, $issue->listName, $issue->subject, $issue->preheader, $issue->html]);
        self::assertSame($at, $issue->renderedAt);
        self::assertSame('hello-world', $issue->slug);

        $issue->retag([Tag::of('zeta'), Tag::of('alpha')]);
        self::assertSame(['alpha', 'zeta'], $issue->tagValues());
    }

    public function testARefreshWithABlankSubjectIsRefused(): void
    {
        $this->expectException(InvalidArchiveValue::class);
        $this->issue()->refresh('l1', 'news', 'News', ' ', '', '', new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, string}>
     */
    public static function badValues(): iterable
    {
        yield 'empty campaign' => ['', 'default', 'hello', 'News', 'Subject', ''];
        yield 'long campaign' => [str_repeat('c', 37), 'default', 'hello', 'News', 'Subject', ''];
        yield 'empty site' => ['c1', '', 'hello', 'News', 'Subject', ''];
        yield 'long site' => ['c1', str_repeat('s', 65), 'hello', 'News', 'Subject', ''];
        yield 'bad slug' => ['c1', 'default', 'Hello', 'News', 'Subject', ''];
        yield 'blank subject' => ['c1', 'default', 'hello', 'News', '  ', ''];
        yield 'long subject' => ['c1', 'default', 'hello', 'News', str_repeat('é', 201), ''];
        yield 'long preheader' => ['c1', 'default', 'hello', 'News', 'Subject', str_repeat('é', 256)];
        yield 'long list name' => ['c1', 'default', 'hello', str_repeat('é', 121), 'Subject', ''];
    }

    #[DataProvider('badValues')]
    public function testValuesOutOfShapeAreRefused(string $campaign, string $site, string $slug, string $listName, string $subject, string $preheader): void
    {
        $this->expectException(InvalidArchiveValue::class);
        new ArchivedIssue($campaign, $site, $slug, 'l1', 'news', $listName, $subject, $preheader, '', new DateTimeImmutable(), new DateTimeImmutable());
    }

    public function testValuesAtTheirLimitsAreAccepted(): void
    {
        $issue = new ArchivedIssue(str_repeat('c', 36), str_repeat('s', 64), 'hello', 'l1', 'news', str_repeat('é', 120), str_repeat('é', 200), str_repeat('é', 255), '', new DateTimeImmutable(), new DateTimeImmutable());

        self::assertSame(200, mb_strlen($issue->subject));
        self::assertSame([], $issue->tags);
    }

    private function issue(): ArchivedIssue
    {
        return new ArchivedIssue('c1', 'default', 'hello-world', 'l1', 'news', 'News', 'Hello world', 'In short', '<p>Hello</p>', new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01'), [Tag::of('news'), Tag::of('events')]);
    }
}
