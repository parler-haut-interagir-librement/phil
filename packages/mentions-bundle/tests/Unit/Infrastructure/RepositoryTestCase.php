<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;

/**
 * What every store of mentions does, run against each one.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testMentionsAreAddedReadAndRemoved(): void
    {
        $store = $this->store();
        $a = $this->mention('a', 'comment:1', 'user:1', '10:00:01');
        $b = $this->mention('b', 'comment:1', 'user:2', '10:00:00');
        $c = $this->mention('c', 'comment:2', 'user:1', '10:00:02');
        self::assertSame([$a, $b, $c], $store->change([$a, $b, $c], []));

        self::assertSame(['b', 'a'], $this->ids($this->reread($store, 'comment:1')));
        self::assertSame(['c'], $this->ids($this->reread($store, 'comment:2')));
        self::assertSame([], $store->ofSubject(ResourceReference::fromString('comment:3')));
        self::assertSame(['c', 'a'], $this->ids($store->ofMember(ResourceReference::fromString('user:1'), 10)));
        self::assertSame(['c'], $this->ids($store->ofMember(ResourceReference::fromString('user:1'), 1)));

        $store->change([$this->mention('e', 'comment:4', 'user:5', '10:00:00'), $this->mention('f', 'comment:5', 'user:5', '10:00:00')], []);
        self::assertSame(['f', 'e'], $this->ids($store->ofMember(ResourceReference::fromString('user:5'), 10)));
        $store->change([$this->mention('z', 'comment:7', 'user:8', '10:00:00'), $this->mention('m', 'comment:8', 'user:8', '10:00:05')], []);
        self::assertSame(['m', 'z'], $this->ids($store->ofMember(ResourceReference::fromString('user:8'), 10)));
        $store->change([$this->mention('h', 'comment:6', 'user:6', '10:00:00'), $this->mention('g', 'comment:6', 'user:7', '10:00:00')], []);
        self::assertSame(['g', 'h'], $this->ids($this->reread($store, 'comment:6')));

        $found = $store->ofSubject(ResourceReference::fromString('comment:1'));
        $d = $this->mention('d', 'comment:1', 'user:3', '10:00:03');
        self::assertSame([$d], $store->change([$d], [$found[0]]));
        self::assertSame(['a', 'd'], $this->ids($this->reread($store, 'comment:1')));

        $store->forget(ResourceReference::fromString('comment:1'));
        self::assertSame([], $this->reread($store, 'comment:1'));
        self::assertSame(['c'], $this->ids($this->reread($store, 'comment:2')));

        $read = $this->reread($store, 'comment:2')[0];
        self::assertSame(['comment:2', 'user:1', 'user:9', '10:00:02'], [$read->subject()->toString(), $read->member()->toString(), $read->author()->toString(), $read->createdAt()->format('H:i:s')]);
    }

    public function testAMemberAlreadyMentionedIsLeftOutAndTheRowFoundKept(): void
    {
        $store = $this->store();
        $first = $this->mention('a', 'comment:1', 'user:1', '10:00:00');
        $store->change([$first], []);

        $again = $this->mention('b', 'comment:1', 'user:1', '10:00:05');
        $other = $this->mention('c', 'comment:1', 'user:2', '10:00:05');
        $elsewhere = $this->mention('d', 'comment:2', 'user:1', '10:00:05');

        self::assertSame([$other, $elsewhere], $store->change([$again, $other, $elsewhere], []));
        self::assertSame(['a', 'c'], $this->ids($this->reread($store, 'comment:1')));
        self::assertSame(['d'], $this->ids($this->reread($store, 'comment:2')));
        self::assertSame('10:00:00', $this->reread($store, 'comment:1')[0]->createdAt()->format('H:i:s'));

        // Removed and added again in one change: the row is written anew.
        $removed = $this->reread($store, 'comment:1')[0];
        $back = $this->mention('e', 'comment:1', 'user:1', '10:00:09');
        self::assertSame([$back], $store->change([$back], [$removed]));
        self::assertSame(['c', 'e'], $this->ids($this->reread($store, 'comment:1')));
    }

    abstract protected function store(): MentionRepository;

    /**
     * @return list<RecordedMention>
     */
    protected function reread(MentionRepository $store, string $subject): array
    {
        return $store->ofSubject(ResourceReference::fromString($subject));
    }

    private function mention(string $id, string $subject, string $member, string $time): RecordedMention
    {
        return RecordedMention::record($id, ResourceReference::fromString($subject), ResourceReference::fromString($member), ResourceReference::of('user', '9'), new DateTimeImmutable('2026-11-08 '.$time.' UTC'));
    }

    /**
     * @param list<RecordedMention> $mentions
     *
     * @return list<string>
     */
    private function ids(array $mentions): array
    {
        return array_map(static fn (RecordedMention $mention): string => $mention->id, $mentions);
    }
}
