<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Privacy;

use Closure;
use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Infrastructure\Privacy\SchedulingPollPrivacy;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(SchedulingPollPrivacy::class)]
final class SchedulingPollPrivacyTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): Fixtures}>
     */
    public static function stores(): iterable
    {
        yield 'memory' => [static fn (): Fixtures => Fixtures::memory()];
        yield 'doctrine' => [static fn (): Fixtures => Fixtures::doctrine()];
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAPersonIsExportedByTheirAccountOrByTheirAddress(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $member = $test->polls->join($poll->id, 'Bob', null, ResourceReference::of('member', '42'), 'fr');
        $test->polls->answer($member->token, [$first => Availability::Yes], 'Europe/Paris');
        $test->polls->join($poll->id, 'Carol', 'Carol@Example.org');

        $privacy = new SchedulingPollPrivacy($test->respondentStore, $test->pollStore, 'member');

        self::assertSame('scheduling_poll', $privacy->module()->value);

        $byAccount = $this->respondents($privacy->export(DataSubject::fromString('42')));
        self::assertCount(1, $byAccount);
        $bob = $byAccount[0];
        $answers = $bob['answers'] ?? null;
        self::assertIsArray($answers);
        self::assertSame($poll->id, $bob['poll']);
        self::assertSame('Game night', $bob['title']);
        self::assertSame('Bob', $bob['name']);
        self::assertSame('member:42', $bob['account']);
        self::assertSame('fr', $bob['locale']);
        self::assertSame('Europe/Paris', $bob['timezone']);
        self::assertSame('2026-11-21T10:00:00+00:00', $bob['joined_at']);
        self::assertSame('2026-11-21T10:00:00+00:00', $bob['answered_at']);
        self::assertSame('yes', $answers[$first]);
        self::assertSame('no', $answers[$test->slotIds($poll)[1]]);
        self::assertArrayNotHasKey('email', $bob);
        self::assertArrayNotHasKey('revoked_at', $bob);

        [$carol] = $this->respondents($privacy->export(DataSubject::fromString('carol@example.ORG')));
        self::assertSame('Carol@example.org', $carol['email']);
        self::assertArrayNotHasKey('answers', $carol);
        self::assertArrayNotHasKey('answered_at', $carol);

        self::assertSame([], $privacy->export(DataSubject::fromString('nobody@example.org')));
        self::assertSame([], $privacy->export(DataSubject::fromString('not@an@address')));
        self::assertSame([], $privacy->export(DataSubject::fromString('43')));
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheErasureDeletesThePersonsRespondentsAndIsIdempotent(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $bob = $test->polls->join($poll->id, 'Bob', 'bob@example.org');
        $test->polls->join($poll->id, 'Carol', null, ResourceReference::of('user', 'carol'));
        $test->polls->revoke($poll->id, Fixtures::organizer(), (string) $test->polls->open($bob->token)?->respondent->id);

        $privacy = new SchedulingPollPrivacy($test->respondentStore, $test->pollStore);

        self::assertArrayHasKey('revoked_at', $this->respondents($privacy->export(DataSubject::fromString('bob@example.org')))[0]);

        for ($time = 1; $time <= 2; ++$time) {
            $report = $privacy->erase(DataSubject::fromString('bob@example.org'));
            self::assertSame('scheduling_poll', $report->module->value);
            self::assertSame([Respondent::TABLE => ErasureEffect::Deleted], $report->effects);
        }

        self::assertSame(['Carol'], array_map(static fn (Respondent $respondent): ?string => $respondent->name(), $test->polls->respondents($poll->id)));
        self::assertNull($test->polls->open($bob->token));

        $privacy->erase(DataSubject::fromString('carol'));
        self::assertSame([], $test->polls->respondents($poll->id));
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testASubjectThatIsNotAnAddressIsNeverTakenForAnAccountAndAGonePollHasNoTitle(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $test->polls->join($poll->id, 'Mallory', null, ResourceReference::of('user', 'not@an@address'));
        $now = new DateTimeImmutable(Fixtures::NOW);
        $test->respondentStore->add(new Respondent(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), bin2hex(random_bytes(16)), 'Ghost', $now, 'ghost@example.org'));
        $privacy = new SchedulingPollPrivacy($test->respondentStore, $test->pollStore);

        self::assertSame([], $privacy->export(DataSubject::fromString('not@an@address')));

        $privacy->erase(DataSubject::fromString('not@an@address'));
        self::assertSame(['Mallory'], array_map(static fn (Respondent $respondent): ?string => $respondent->name(), $test->polls->respondents($poll->id)));

        [$ghost] = $this->respondents($privacy->export(DataSubject::fromString('ghost@example.org')));
        self::assertSame('Ghost', $ghost['name']);
        self::assertArrayNotHasKey('title', $ghost);
    }

    /**
     * @param array<string, mixed> $export
     *
     * @return list<array<string, mixed>>
     */
    private function respondents(array $export): array
    {
        self::assertSame(['respondents'], array_keys($export));
        $respondents = $export['respondents'];
        self::assertIsList($respondents);
        $checked = [];

        foreach ($respondents as $respondent) {
            self::assertIsArray($respondent);
            $row = [];

            foreach ($respondent as $key => $value) {
                $row[(string) $key] = $value;
            }

            $checked[] = $row;
        }

        return $checked;
    }
}
