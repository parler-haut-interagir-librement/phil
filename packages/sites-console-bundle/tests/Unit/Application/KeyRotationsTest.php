<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Console\Journal;
use Phil\SitesConsoleBundle\Application\Console\KeyRotations;
use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\Exception\RotationRefused;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;
use Phil\SitesConsoleBundle\Domain\Status\RotationStep;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySnapshotRepository;
use Phil\SitesConsoleBundle\Tests\Unit\Support\FakeAgentClient;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingKeyRotations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Clock\MockClock;

/**
 * The rotation of the console key from the console: the next key staged on every site, the signature switched, the
 * previous key retired, the stop at the first failure and the resumption.
 */
#[CoversClass(KeyRotations::class)]
#[CoversClass(ConsoleSigning::class)]
#[CoversClass(KeyRotation::class)]
#[CoversClass(RotationPhase::class)]
#[CoversClass(RotationStep::class)]
#[CoversClass(RotationRefused::class)]
final class KeyRotationsTest extends TestCase
{
    private MockClock $clock;

    private InMemorySiteRepository $sites;

    private InMemoryKeyRotationRepository $rotations;

    private InMemoryJournalRepository $journal;

    private FakeAgentClient $client;

    /** @var array<string, list<string>> the key each request was meant to be signed with, by site */
    private array $signedWith = [];

    private ?ConsoleSigning $signing = null;

    /** @var list<array{KeyOperation, string}> the operation of each request and the key it carries */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock(Fixtures::NOW);
        $this->sites = new InMemorySiteRepository();
        $this->rotations = new InMemoryKeyRotationRepository();
        $this->journal = new InMemoryJournalRepository();
        $this->client = new FakeAgentClient();

        foreach (['blog', 'shop'] as $id) {
            $this->sites->save(Fixtures::site($id));
            $this->client->keys[$id] = $this->agent($id);
        }
    }

    public function testTheKeyRotatesOnEverySite(): void
    {
        $signing = $this->signing();
        $this->assertSameKey(Keys::console(), $signing->key());

        $rotation = $this->rotations($signing)->run('ada');

        self::assertSame(RotationPhase::Finished, $rotation->phase());
        self::assertSame([
            'keys:blog:'.$rotation->id().':stage:blog',
            'keys:shop:'.$rotation->id().':stage:shop',
            'keys:blog:'.$rotation->id().':retire:blog',
            'keys:shop:'.$rotation->id().':retire:shop',
        ], $this->client->calls);
        $staged = Keys::next()->verifyingKey()->toBase64();
        $retired = Keys::console()->verifyingKey()->toBase64();
        self::assertSame([
            [KeyOperation::Stage, $staged],
            [KeyOperation::Stage, $staged],
            [KeyOperation::Retire, $retired],
            [KeyOperation::Retire, $retired],
        ], $this->sent, 'The next key is staged, the previous key is retired.');
        self::assertSame(['blog' => ['current', 'next'], 'shop' => ['current', 'next']], $this->signedWith, 'Each stage is signed with the current key, each retirement with the next one.');
        $this->assertSameKey(Keys::next(), $signing->key());
        self::assertSame([
            ['site' => 'blog', 'stage' => RotationStep::Done, 'retire' => RotationStep::Done, 'message' => 'retired'],
            ['site' => 'shop', 'stage' => RotationStep::Done, 'retire' => RotationStep::Done, 'message' => 'retired'],
        ], $rotation->progress());
        self::assertSame('ada', $rotation->startedBy());
        self::assertNull($this->rotations->unfinished());

        self::assertSame([
            [JournalAction::KeyRotationStarted, null, true, 'From '.Keys::console()->verifyingKey()->toBase64().' to '.Keys::next()->verifyingKey()->toBase64().' on 2 sites'],
            [JournalAction::KeyStaged, 'blog', true, 'done: staged'],
            [JournalAction::KeyStaged, 'shop', true, 'done: staged'],
            [JournalAction::KeySwitched, null, true, 'The console signs with '.Keys::next()->verifyingKey()->toBase64()],
            [JournalAction::KeyRetired, 'blog', true, 'done: retired'],
            [JournalAction::KeyRetired, 'shop', true, 'done: retired'],
            [JournalAction::KeyRotationFinished, null, true, 'No site accepts '.Keys::console()->verifyingKey()->toBase64().' any more'],
        ], $this->lines());

        foreach ($this->journal->recent(20) as $entry) {
            self::assertSame('ada', $entry->actor());
            self::assertSame($rotation->id(), $entry->reference());
        }
    }

    public function testTheRunStopsAtTheFirstFailureAndResumes(): void
    {
        $this->client->keys['blog'] = static fn (KeyRequest $request): Outcome => throw AgentFailure::unreachable('Connection refused');
        $signing = $this->signing();
        $rotations = $this->rotations($signing);

        $stopped = $rotations->run('ada');
        self::assertSame(RotationPhase::Staging, $stopped->phase());
        self::assertSame(['keys:blog:'.$stopped->id().':stage:blog'], $this->client->calls, 'The run stops at the first site that fails.');
        self::assertSame(RotationStep::Failed, $stopped->progress()[0]['stage']);
        self::assertSame('The agent could not be reached: Connection refused', $stopped->progress()[0]['message']);
        self::assertSame(RotationStep::Pending, $stopped->progress()[1]['stage']);
        $this->assertSameKey(Keys::console(), $signing->key(), 'The console keeps signing with the current key.');
        self::assertSame([JournalAction::KeyStaged, 'blog', false, 'failed: The agent could not be reached: Connection refused'], $this->lines()[1]);

        $this->client->keys['blog'] = $this->agent('blog');
        $this->clock->sleep(60);
        $resumed = $rotations->run('bob');

        self::assertSame($stopped->id(), $resumed->id());
        self::assertSame(RotationPhase::Finished, $resumed->phase());
        self::assertSame('ada', $resumed->startedBy());
        self::assertEquals(new DateTimeImmutable('2026-11-20 10:01:00'), $resumed->updatedAt());
        self::assertEquals(Fixtures::now(), $resumed->startedAt());
        self::assertSame('keys:blog:'.$stopped->id().':stage:blog', $this->client->calls[1], 'The step carries the same idempotency key again.');
        self::assertCount(1, array_filter($this->lines(), static fn (array $line): bool => JournalAction::KeyRotationStarted === $line[0]));
    }

    public function testARefusalStopsTheRetirement(): void
    {
        $this->client->keys['shop'] = static fn (KeyRequest $request): Outcome => KeyOperation::Stage === $request->operation ? Outcome::succeeded('staged') : Outcome::failed('No.');
        $signing = $this->signing();

        $rotation = $this->rotations($signing)->run(null);

        self::assertSame(RotationPhase::Retiring, $rotation->phase());
        $this->assertSameKey(Keys::next(), $signing->key(), 'Every site holds the next key: the console signs with it.');
        self::assertSame(['blog'], array_values(array_diff(['blog', 'shop'], $rotation->unsettled(KeyOperation::Retire))));
        self::assertSame([JournalAction::KeyRetired, 'shop', false, 'failed: No.'], $this->lines()[5]);
        self::assertSame($rotation, $this->rotations->unfinished());
    }

    public function testASiteThatLeftIsSkipped(): void
    {
        $this->client->keys['blog'] = static fn (KeyRequest $request): Outcome => Outcome::failed('Down.');
        $rotations = $this->rotations($this->signing());
        $rotations->run(null);

        $this->sites->remove(Fixtures::site('blog'));

        $rotation = $rotations->run(null);

        self::assertSame(RotationPhase::Finished, $rotation->phase());
        self::assertSame(['site' => 'blog', 'stage' => RotationStep::Skipped, 'retire' => RotationStep::Skipped, 'message' => 'The site left the console.'], $rotation->progress()[0]);
        self::assertSame([JournalAction::KeyStaged, 'blog', true, 'skipped: the site left the console'], $this->lines()[2]);
    }

    public function testARotationWithoutSitesFinishesAtOnce(): void
    {
        $rotation = $this->rotations($this->signing(), new InMemorySiteRepository())->run(null);

        self::assertSame(RotationPhase::Finished, $rotation->phase());
        self::assertSame([], $rotation->progress());
        self::assertSame([], $this->client->calls);
    }

    public function testARetiringRotationResumesOnceTheKeysOfTheConfigurationMoved(): void
    {
        $this->client->keys['shop'] = static fn (KeyRequest $request): Outcome => KeyOperation::Stage === $request->operation ? Outcome::succeeded() : Outcome::failed('Down.');
        $first = $this->rotations($this->signing())->run(null);
        $this->client->keys['shop'] = $this->agent('shop');

        $moved = new ConsoleSigning(Keys::next(), null, $this->rotations);
        $rotation = $this->rotations($moved)->run(null);

        self::assertSame($first->id(), $rotation->id());
        self::assertSame(RotationPhase::Finished, $rotation->phase());
        self::assertSame('done', $this->lines()[2][3], 'An outcome without message is journaled by its step.');
    }

    public function testTheRotationsThatCannotRun(): void
    {
        $this->assertRefused(RotationRefused::noNextKey(), new ConsoleSigning(Keys::console(), null, $this->rotations));
        $this->assertRefused(RotationRefused::sameKey(), new ConsoleSigning(Keys::console(), Keys::console(), $this->rotations));

        $this->client->keys['blog'] = static fn (KeyRequest $request): Outcome => Outcome::failed('Down.');
        $stopped = $this->rotations($this->signing())->run(null);

        $this->assertRefused(RotationRefused::otherRotation($stopped->id()), new ConsoleSigning(Keys::console(), SigningKey::generate(), $this->rotations));
        $this->assertRefused(RotationRefused::otherPrevious($stopped->id()), new ConsoleSigning(Keys::site(), Keys::next(), $this->rotations));
        $this->assertRefused(RotationRefused::noNextKey(), new ConsoleSigning(Keys::next(), null, $this->rotations), 'A staging rotation does not resume on the next key alone.');
        self::assertSame([], array_filter($this->client->calls, static fn (string $call): bool => !str_starts_with($call, 'keys:blog:'.$stopped->id().':stage')));
    }

    public function testTheSigningKey(): void
    {
        $this->assertSameKey(Keys::console(), new ConsoleSigning(Keys::console())->key());
        $this->assertSameKey(Keys::console(), new ConsoleSigning(Keys::console(), Keys::next())->key(), 'Without rotations, the current key signs.');
        $this->assertSameKey(Keys::console(), new ConsoleSigning(Keys::console(), Keys::next(), $this->rotations)->key(), 'Without a rotation to the next key, the current key signs.');
        self::assertNull(new ConsoleSigning(Keys::console())->next());
        $this->assertSameKey(Keys::next(), new ConsoleSigning(Keys::console(), Keys::next())->next());
        $this->assertSameKey(Keys::console(), new ConsoleSigning(Keys::console(), Keys::next())->current());

        $rotation = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), [], null, Fixtures::now());
        $this->rotations->save($rotation);
        $this->assertSameKey(Keys::console(), new ConsoleSigning(Keys::console(), Keys::next(), $this->rotations)->key(), 'While staging, the current key signs.');
        $rotation->switchSigning(Fixtures::now());
        $this->assertSameKey(Keys::next(), new ConsoleSigning(Keys::console(), Keys::next(), $this->rotations)->key());
        $rotation->finish(Fixtures::now());
        $this->assertSameKey(Keys::next(), new ConsoleSigning(Keys::console(), Keys::next(), $this->rotations)->key());
    }

    public function testTheRotationGuardsItsPhases(): void
    {
        $rotation = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), ['shop', 'shop', 'blog'], str_repeat('a', 200), Fixtures::now());

        self::assertSame(['shop', 'blog'], $rotation->unsettled(KeyOperation::Stage));
        self::assertSame(180, mb_strlen((string) $rotation->startedBy()));
        self::assertSame(26, \strlen($rotation->id()));
        self::assertSame($rotation->id().':stage:shop', $rotation->requestId(KeyOperation::Stage, 'shop'));
        self::assertSame($rotation->id().':retire:shop', $rotation->requestId(KeyOperation::Retire, 'shop'));
        self::assertTrue($rotation->previousKey()->equals(Keys::console()->verifyingKey()));
        self::assertTrue($rotation->nextKey()->equals(Keys::next()->verifyingKey()));
        self::assertNull(KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), [], null, Fixtures::now())->startedBy());

        $this->assertInvalid(\sprintf('The rotation %s cannot be switched to the next key now.', $rotation->id()), static fn () => $rotation->switchSigning(Fixtures::now()));
        $this->assertInvalid(\sprintf('The rotation %s cannot be finished now.', $rotation->id()), static fn () => $rotation->finish(Fixtures::now()));
        $this->assertInvalid(\sprintf('The rotation %s does not reach the site "none".', $rotation->id()), static fn (): RotationStep => $rotation->record(KeyOperation::Stage, 'none', Outcome::succeeded(), Fixtures::now()));

        self::assertSame(RotationStep::Failed, $rotation->record(KeyOperation::Stage, 'shop', Outcome::failed('x'), Fixtures::now()));
        self::assertSame(RotationStep::Done, $rotation->record(KeyOperation::Stage, 'shop', Outcome::succeeded(), Fixtures::now()));
        self::assertSame(RotationStep::Pending, $rotation->progress()[0]['retire'], 'A stage leaves the retirement as it was.');
        $rotation->record(KeyOperation::Stage, 'blog', Outcome::succeeded(), Fixtures::now());
        $rotation->switchSigning(Fixtures::now());
        self::assertSame(RotationPhase::Retiring, $rotation->phase());
        $this->assertInvalid(\sprintf('The rotation %s cannot be switched to the next key now.', $rotation->id()), static fn () => $rotation->switchSigning(Fixtures::now()));
        $this->assertInvalid(\sprintf('The rotation %s cannot be finished now.', $rotation->id()), static fn () => $rotation->finish(Fixtures::now()));

        $rotation->record(KeyOperation::Retire, 'shop', Outcome::succeeded(), Fixtures::now());
        self::assertSame(RotationStep::Done, $rotation->progress()[0]['stage'], 'A retirement leaves the stage as it was.');
        self::assertSame(['blog'], $rotation->unsettled(KeyOperation::Retire));

        $this->assertInvalid('The next key of the console is its current key.', static fn (): KeyRotation => KeyRotation::start(Keys::console()->verifyingKey(), Keys::console()->verifyingKey(), [], null, Fixtures::now()));
        $this->assertInvalid('A rotation reaches at most 1000 sites.', static fn (): KeyRotation => KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), array_map(static fn (int $i): string => 's'.$i, range(0, 1000)), null, Fixtures::now()));
        self::assertCount(1000, KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), array_map(static fn (int $i): string => 's'.$i, range(1, 1000)), null, Fixtures::now())->progress());
    }

    public function testTheSteps(): void
    {
        self::assertTrue(RotationStep::Done->isSettled());
        self::assertTrue(RotationStep::Skipped->isSettled());
        self::assertFalse(RotationStep::Pending->isSettled());
        self::assertFalse(RotationStep::Failed->isSettled());
        self::assertFalse(RotationPhase::Staging->signsWithNext());
        self::assertTrue(RotationPhase::Retiring->signsWithNext());
        self::assertTrue(RotationPhase::Finished->signsWithNext());
    }

    public function testTheMemoryStoreFindsTheNewestRotation(): void
    {
        $first = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), [], null, Fixtures::now());
        $this->rotations->save($first);
        $first->switchSigning(Fixtures::now());
        $first->finish(Fixtures::now());

        $second = KeyRotation::start(Keys::next()->verifyingKey(), Keys::console()->verifyingKey(), [], null, Fixtures::now());
        $this->rotations->save($second);

        self::assertSame($second, $this->rotations->unfinished());
        self::assertSame($first, $this->rotations->latestTo(Keys::next()->verifyingKey()->toBase64()));
        self::assertSame($second, $this->rotations->latestTo(Keys::console()->verifyingKey()->toBase64()));
        self::assertNull($this->rotations->latestTo(Keys::site()->verifyingKey()->toBase64()));
        self::assertSame($first, $this->rotations->get($first->id()));
        self::assertNull($this->rotations->get('none'));
    }

    public function testEveryStepIsSaved(): void
    {
        $saves = new RecordingKeyRotations($this->rotations);

        $this->rotating($saves)->run(null);
        self::assertSame([
            RotationPhase::Staging,
            RotationPhase::Staging,
            RotationPhase::Staging,
            RotationPhase::Retiring,
            RotationPhase::Retiring,
            RotationPhase::Retiring,
            RotationPhase::Finished,
        ], $saves->phases, 'The start, the two stages, the switch, the two retirements and the end.');
    }

    public function testTheStartTheSwitchAndTheEndAreSavedOnTheirOwn(): void
    {
        $saves = new RecordingKeyRotations($this->rotations);

        $this->rotating($saves, new InMemorySiteRepository())->run(null);

        self::assertSame([RotationPhase::Staging, RotationPhase::Retiring, RotationPhase::Finished], $saves->phases);
    }

    public function testASkippedSiteIsSaved(): void
    {
        $this->client->keys['blog'] = static fn (KeyRequest $request): Outcome => Outcome::failed('Down.');
        $saves = new RecordingKeyRotations($this->rotations);
        $rotations = $this->rotating($saves);
        $rotations->run(null);
        self::assertSame([RotationPhase::Staging, RotationPhase::Staging], $saves->phases, 'The start and the failed stage.');

        $this->sites->remove(Fixtures::site('blog'));
        $rotations->run(null);

        self::assertSame([
            RotationPhase::Staging,
            RotationPhase::Staging,
            RotationPhase::Staging,
            RotationPhase::Staging,
            RotationPhase::Retiring,
            RotationPhase::Retiring,
            RotationPhase::Finished,
        ], $saves->phases, 'The skip, the stage of the other site, the switch, its retirement and the end.');
    }

    public function testTheSitesOfARotationAreCountedOnce(): void
    {
        $rotation = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), ['shop', 'shop', 'blog'], null, Fixtures::now());

        self::assertSame([0, 1], array_keys($rotation->progress()));
    }

    public function testAnActorIsCutByCharacters(): void
    {
        $rotation = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), [], str_repeat("\u{e9}", 200), Fixtures::now());

        self::assertSame(str_repeat("\u{e9}", 180), $rotation->startedBy());
    }

    public function testTheColumnsOfARotation(): void
    {
        $columns = [];

        foreach (new ReflectionClass(KeyRotation::class)->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attribute) {
                $column = $attribute->newInstance();
                $columns[(string) $column->name] = [$column->length, $column->nullable, $column->options['fixed'] ?? false];
            }
        }

        self::assertSame([
            'id' => [26, false, true],
            'previous_key' => [64, false, false],
            'next_key' => [64, false, false],
            'phase' => [16, false, false],
            'started_by' => [180, true, false],
        ], array_intersect_key($columns, array_flip(['id', 'previous_key', 'next_key', 'phase', 'started_by'])));
    }

    private function rotating(KeyRotationRepository $rotations, ?InMemorySiteRepository $sites = null): KeyRotations
    {
        $journal = new Journal($this->journal, $this->clock);
        $registry = new SiteRegistry($sites ?? $this->sites, new InMemorySnapshotRepository(), $journal, $this->clock);
        $this->signing = new ConsoleSigning(Keys::console(), Keys::next(), $rotations);

        return new KeyRotations($registry, $rotations, $this->client, $this->signing, $journal, $this->clock);
    }

    private function signing(): ConsoleSigning
    {
        return new ConsoleSigning(Keys::console(), Keys::next(), $this->rotations);
    }

    private function rotations(ConsoleSigning $signing, ?InMemorySiteRepository $sites = null): KeyRotations
    {
        $journal = new Journal($this->journal, $this->clock);
        $registry = new SiteRegistry($sites ?? $this->sites, new InMemorySnapshotRepository(), $journal, $this->clock);
        $this->signing = $signing;

        return new KeyRotations($registry, $this->rotations, $this->client, $signing, $journal, $this->clock);
    }

    /**
     * An agent that answers every step, and records which key the console signs with at that moment.
     *
     * @return Closure(KeyRequest): Outcome
     */
    private function agent(string $id): Closure
    {
        return function (KeyRequest $request) use ($id): Outcome {
            $this->sent[] = [$request->operation, $request->key->toBase64()];
            $key = $this->signing?->key();
            $this->signedWith[$id][] = true === $key?->verifyingKey()->equals(Keys::console()->verifyingKey()) ? 'current' : 'next';

            return Outcome::succeeded(KeyOperation::Stage === $request->operation ? 'staged' : 'retired');
        };
    }

    /**
     * @return list<array{JournalAction, ?string, bool, string}> the oldest first
     */
    private function lines(): array
    {
        return array_map(static fn (JournalEntry $entry): array => [$entry->action(), $entry->siteId(), $entry->succeeded(), $entry->message()], array_reverse($this->journal->recent(50)));
    }

    private function assertSameKey(SigningKey $expected, ?SigningKey $actual, string $message = ''): void
    {
        self::assertNotNull($actual, $message);
        self::assertSame($expected->verifyingKey()->toBase64(), $actual->verifyingKey()->toBase64(), $message);
    }

    private function assertRefused(RotationRefused $expected, ConsoleSigning $signing, string $message = ''): void
    {
        try {
            $this->rotations($signing)->run(null);
            self::fail('Refused: '.$expected->getMessage());
        } catch (RotationRefused $rotationRefused) {
            self::assertSame($expected->getMessage(), $rotationRefused->getMessage(), $message);
        }
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertInvalid(string $message, Closure $call): void
    {
        try {
            $call();
            self::fail($message);
        } catch (InvalidValue $invalidValue) {
            self::assertSame($message, $invalidValue->getMessage());
        }
    }
}
