<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Platform;

use DateTimeImmutable;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Ags\InMemoryLineItemStore;
use Phil\Lti\Platform\Ags\InMemoryScoreStore;
use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Platform\Nrps\InMemoryRoster;
use Phil\Lti\Platform\Nrps\NrpsService;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\Result;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Service\Scope;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgsService::class)]
#[CoversClass(NrpsService::class)]
#[CoversClass(InMemoryRoster::class)]
#[CoversClass(InMemoryLineItemStore::class)]
#[CoversClass(InMemoryScoreStore::class)]
#[CoversClass(StoredLineItem::class)]
final class PlatformServicesTest extends TestCase
{
    private InMemoryRoster $roster;

    private InMemoryLineItemStore $lineItems;

    private InMemoryScoreStore $scores;

    protected function setUp(): void
    {
        $members = [
            new Member('u1', Roles::of([ContextRole::Learner])),
            new Member('u2', Roles::of([ContextRole::Instructor])),
            new Member('u3', Roles::of([ContextRole::Learner])),
        ];
        $this->roster = new InMemoryRoster()->add(new Context('course-1', 'CS101'), ['tool'], $members)->add(new Context('course-2'), ['other'], []);
        $this->lineItems = new InMemoryLineItemStore();
        $this->scores = new InMemoryScoreStore();
    }

    public function testATokenCreatesLineItemsAndScoresThem(): void
    {
        $ags = $this->ags();
        $token = self::token(Scope::ALL);
        $created = $ags->create($token, 'course-1', new LineItem(10.0, 'Quiz', Url::fromString('https://ignored.example.org/'), 'quiz-1', 'link-1', 'graded'));

        self::assertNotNull($created);
        self::assertSame(32, \strlen($created->id));
        self::assertNull($created->lineItem->id);
        self::assertSame(['tool', 'course-1', 'Quiz'], [$created->clientId, $created->contextId, $created->lineItem->label]);
        self::assertSame([$created], $ags->lineItems($token, 'course-1'));
        self::assertSame([$created], $ags->lineItems($token, 'course-1', 'link-1', 'quiz-1', 'graded'));
        self::assertSame([], $ags->lineItems($token, 'course-1', 'link-2'));
        self::assertSame([], $ags->lineItems($token, 'course-1', resourceId: 'quiz-2'));
        self::assertSame([], $ags->lineItems($token, 'course-1', tag: 'other'));
        self::assertSame($created, $ags->lineItem($token, 'course-1', $created->id));

        self::assertTrue($ags->score($token, 'course-1', $created->id, self::score('u1', 4.0, 5.0, '12:00')));
        self::assertFalse($ags->score($token, 'course-1', $created->id, self::score('u1', 1.0, 5.0, '11:59')));
        self::assertTrue($ags->score($token, 'course-1', $created->id, self::score('u1', 3.0, 5.0, '12:00')));
        self::assertTrue($ags->score($token, 'course-1', $created->id, self::score('u2', null, null, '12:00')));

        $results = $ags->results($token, 'course-1', $created->id);
        self::assertNotNull($results);
        self::assertSame(['userId' => 'u1', 'resultScore' => 6.0, 'resultMaximum' => 10.0], $results[0]->toArray());
        self::assertSame(['userId' => 'u2', 'resultMaximum' => 10.0], $results[1]->toArray());
        self::assertCount(1, $ags->results($token, 'course-1', $created->id, 'u2') ?? []);
        self::assertSame([], $ags->results($token, 'course-1', $created->id, 'u9'));
        self::assertCount(1, $ags->results($token, 'course-1', $created->id, limit: 1, offset: 1) ?? []);

        $updated = $ags->update($token, 'course-1', $created->id, new LineItem(20.0, 'Quiz 2', Url::fromString('https://ignored.example.org/')));
        self::assertSame(['Quiz 2', null, $created->id], [$updated?->lineItem->label, $updated?->lineItem->id, $updated?->id]);
        self::assertSame(12.0, ($ags->results($token, 'course-1', $created->id) ?? [])[0]->resultScore);

        self::assertTrue($ags->delete($token, 'course-1', $created->id));
        self::assertFalse($ags->delete($token, 'course-1', $created->id));
        self::assertNull($ags->lineItem($token, 'course-1', $created->id));
    }

    public function testAToolSeesOnlyItsContextsAndLineItems(): void
    {
        $ags = $this->ags();
        $token = self::token(Scope::ALL);
        $foreign = new StoredLineItem('foreign', 'other', 'course-1', new LineItem(1.0, 'Other'));
        $this->lineItems->save($foreign);

        self::assertNull($ags->lineItems($token, 'course-2'));
        self::assertNull($ags->lineItems($token, 'nope'));
        self::assertNull($ags->create($token, 'course-2', new LineItem(1.0, 'x')));
        self::assertNull($ags->lineItem($token, 'course-1', 'foreign'));
        self::assertNull($ags->lineItem($token, 'course-2', 'foreign'));
        self::assertNull($ags->update($token, 'course-1', 'foreign', new LineItem(1.0, 'x')));
        self::assertNull($ags->update($token, 'course-2', 'foreign', new LineItem(1.0, 'x')));
        self::assertFalse($ags->delete($token, 'course-1', 'foreign'));
        self::assertFalse($ags->delete($token, 'course-2', 'foreign'));
        self::assertNull($ags->score($token, 'course-1', 'foreign', self::score('u1', 1.0, 1.0, '12:00')));
        self::assertNull($ags->score($token, 'course-2', 'foreign', self::score('u1', 1.0, 1.0, '12:00')));
        self::assertNull($ags->results($token, 'course-1', 'foreign'));
        self::assertNull($ags->results($token, 'course-2', 'foreign'));
        self::assertSame([], $ags->lineItems($token, 'course-1'));
        self::assertNull($this->scores->latest('foreign', 'u1'));
    }

    public function testEachOperationNeedsItsScope(): void
    {
        $ags = $this->ags();
        $readOnly = self::token([Scope::LINE_ITEM_READ_ONLY]);
        $ags->lineItems($readOnly, 'course-1');
        $ags->lineItem($readOnly, 'course-1', 'x');

        $cases = [
            [static fn (): ?StoredLineItem => $ags->create($readOnly, 'course-1', new LineItem(1.0, 'x')), Scope::LINE_ITEM],
            [static fn (): ?StoredLineItem => $ags->update($readOnly, 'course-1', 'x', new LineItem(1.0, 'x')), Scope::LINE_ITEM],
            [static fn (): bool => $ags->delete($readOnly, 'course-1', 'x'), Scope::LINE_ITEM],
            [static fn (): ?bool => $ags->score($readOnly, 'course-1', 'x', self::score('u', null, null, '12:00')), Scope::SCORE],
            [static fn (): ?array => $ags->results($readOnly, 'course-1', 'x'), Scope::RESULT_READ_ONLY],
            [static fn (): ?array => $ags->lineItems(self::token([Scope::SCORE]), 'course-1'), Scope::LINE_ITEM],
            [static fn (): ?StoredLineItem => $ags->lineItem(self::token([Scope::SCORE]), 'course-1', 'x'), Scope::LINE_ITEM],
            [fn (): ?array => new NrpsService($this->roster)->page(self::token([Scope::SCORE]), 'course-1'), Scope::MEMBERSHIP_READ_ONLY],
        ];

        foreach ($cases as [$call, $scope]) {
            try {
                $call();
                self::fail($scope);
            } catch (AccessDenied $accessDenied) {
                self::assertSame('The access token does not grant the scope "'.$scope.'".', $accessDenied->getMessage());
            }
        }
    }

    public function testTheLimitsAreBounded(): void
    {
        $ags = $this->ags();
        $token = self::token(Scope::ALL);

        for ($i = 0; $i < 3; ++$i) {
            $ags->create($token, 'course-1', new LineItem(1.0, 'L'.$i));
        }

        self::assertCount(1, $ags->lineItems($token, 'course-1', limit: 0) ?? []);
        self::assertCount(2, $ags->lineItems($token, 'course-1', limit: 2) ?? []);
        self::assertCount(1, $ags->lineItems($token, 'course-1', limit: 2, offset: 2) ?? []);
        self::assertCount(3, $ags->lineItems($token, 'course-1', offset: -5) ?? []);
        self::assertCount(3, $ags->lineItems($token, 'course-1', limit: 5000) ?? []);
        self::assertSame([100, 1000], [AgsService::DEFAULT_LIMIT, AgsService::MAX_LIMIT]);
    }

    public function testTheMembersArePaged(): void
    {
        $nrps = new NrpsService($this->roster);
        $token = self::token([Scope::MEMBERSHIP_READ_ONLY]);

        $first = $nrps->page($token, 'course-1', limit: 2);
        self::assertNotNull($first);
        self::assertSame('CS101', $first['context']->label);
        self::assertSame(['u1', 'u2'], array_map(static fn (Member $member): string => $member->userId, $first['members']));
        self::assertSame(2, $first['nextOffset']);

        $last = $nrps->page($token, 'course-1', limit: 2, offset: 2);
        self::assertSame(['u3'], array_map(static fn (Member $member): string => $member->userId, $this->members($last)));
        self::assertNotNull($last);
        self::assertNull($last['nextOffset']);

        $exact = $nrps->page($token, 'course-1', limit: 3);
        self::assertNotNull($exact);
        self::assertNull($exact['nextOffset']);
        self::assertCount(3, $this->members($exact));

        self::assertSame(['u1', 'u3'], array_map(static fn (Member $member): string => $member->userId, $this->members($nrps->page($token, 'course-1', 'Learner'))));
        self::assertCount(1, $this->members($nrps->page($token, 'course-1', limit: 0)));
        self::assertCount(3, $this->members($nrps->page($token, 'course-1', offset: -1)));
        self::assertCount(3, $this->members($nrps->page($token, 'course-1', limit: 5000)));
        self::assertNull($nrps->page($token, 'course-2'));
        self::assertSame([100, 1000], [NrpsService::DEFAULT_LIMIT, NrpsService::MAX_LIMIT]);
    }

    public function testTheRosterIsAskedForOneMemberMoreThanThePage(): void
    {
        $roster = new class implements Roster {
            public ?int $asked = null;

            public function context(string $clientId, string $contextId): Context
            {
                return new Context($contextId);
            }

            public function members(string $contextId, ?string $role, int $limit, int $offset): array
            {
                $this->asked = $limit;

                return [];
            }
        };

        new NrpsService($roster)->page(self::token([Scope::MEMBERSHIP_READ_ONLY]), 'c', limit: 7);

        self::assertSame(8, $roster->asked);
    }

    public function testResultsAreListedFromTheFirstWhateverTheOffset(): void
    {
        $ags = $this->ags();
        $token = self::token(Scope::ALL);
        $item = $ags->create($token, 'course-1', new LineItem(10.0, 'Quiz'));
        self::assertNotNull($item);

        foreach (['u1', 'u2', 'u3'] as $user) {
            $ags->score($token, 'course-1', $item->id, self::score($user, 5.0, 10.0, '12:00'));
        }

        self::assertCount(3, $ags->results($token, 'course-1', $item->id, offset: -5) ?? []);
        self::assertCount(3, $ags->results($token, 'course-1', $item->id) ?? []);
    }

    public function testAScoreWithoutAGradeGivesNoResultScore(): void
    {
        $ags = $this->ags();
        $token = self::token(Scope::ALL);
        $item = $ags->create($token, 'course-1', new LineItem(10.0, 'Quiz'));
        self::assertNotNull($item);
        $ags->score($token, 'course-1', $item->id, self::score('u1', null, 10.0, '12:00'));

        $results = $ags->results($token, 'course-1', $item->id) ?? [];

        self::assertSame([null], array_map(static fn (Result $result): ?float => $result->resultScore, $results));
    }

    public function testTheMemoryStoresListInTheOrderOfTheirKeys(): void
    {
        $this->lineItems->save(new StoredLineItem('b', 'tool', 'course-1', new LineItem(1.0, 'B')));
        $this->lineItems->save(new StoredLineItem('a', 'tool', 'course-1', new LineItem(1.0, 'A')));

        $this->scores->record('line', self::score('u2', null, null, '12:00'));
        $this->scores->record('line', self::score('u1', null, null, '12:00'));

        self::assertSame(['a', 'b'], array_map(static fn (StoredLineItem $item): string => $item->id, $this->lineItems->list('tool', 'course-1', null, null, null, 10, 0)));
        self::assertSame(['u1', 'u2'], array_map(static fn (Score $score): string => $score->userId, $this->scores->scores('line', null, 10, 0)));
    }

    public function testTheMemoryStoresForgetOnReset(): void
    {
        $this->lineItems->save(new StoredLineItem('a', 'tool', 'course-1', new LineItem(1.0, 'x')));
        $this->scores->record('a', self::score('u', null, null, '12:00'));
        $this->lineItems->reset();
        $this->scores->reset();

        self::assertNull($this->lineItems->find('tool', 'course-1', 'a'));
        self::assertNull($this->scores->latest('a', 'u'));
        self::assertSame([], $this->scores->scores('a', null, 10, 0));
    }

    /**
     * @param array{context: Context, members: list<Member>, nextOffset: int|null}|null $page
     *
     * @return list<Member>
     */
    private function members(?array $page): array
    {
        self::assertNotNull($page);

        return $page['members'];
    }

    private function ags(): AgsService
    {
        return new AgsService($this->roster, $this->lineItems, $this->scores);
    }

    /**
     * @param list<string> $scopes
     */
    private static function token(array $scopes): AccessToken
    {
        return new AccessToken('h', 'tool', $scopes, new DateTimeImmutable('2030-01-01'));
    }

    private static function score(string $user, ?float $given, ?float $maximum, string $at): Score
    {
        return new Score($user, ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14 '.$at), $given, $maximum);
    }
}
