<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use LogicException;
use Override;
use Phil\FormsBundle\Application\EntryNotifier;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\NotificationFailed;
use Phil\FormsBundle\Application\Retention;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\FormsBundle\Tests\Unit\Fixtures\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(Submissions::class)]
#[CoversClass(Retention::class)]
#[CoversClass(NotificationFailed::class)]
#[CoversClass(DoctrineFormRepository::class)]
final class SubmissionsTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineFormRepository $repository;

    private MutableClock $clock;

    private RecordingLogger $logger;

    /** @var list<string> */
    private array $heard = [];

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineFormRepository($this->entityManager);
        $this->clock = new MutableClock(new DateTimeImmutable('2026-11-04 12:00 UTC'));
        $this->logger = new RecordingLogger();
    }

    public function testAnEntryIsKeptThenEveryoneIsTold(): void
    {
        $form = Forms::registration();
        $this->repository->save($form);
        $entry = new Submissions($this->repository, $this->clock, new HmacEmailDigester('key'), [$this->notifier('first'), $this->failing(), $this->notifier('last')], $this->logger)->submit($form, Forms::values(), 'fr');
        $this->entityManager->clear();

        $kept = $this->repository->entry($entry->id);
        self::assertInstanceOf(Entry::class, $kept);
        self::assertSame(['Ada', 'fr', '2026-11-04T12:00:00+00:00', '2026-12-04T12:00:00+00:00', 'inscription'], [$kept->values['name'], $kept->locale, $kept->submittedAt()->format('c'), $kept->purgeAfter()->format('c'), $kept->form->key]);
        self::assertSame(['first '.$entry->id, 'last '.$entry->id], $this->heard);
        self::assertSame(['error Entry {entry} of form "{form}": {reason} {"entry":"'.$entry->id.'","form":"inscription","reason":"The webhook notification failed: Connection refused.","error":"Phil\\\\FormsBundle\\\\Application\\\\NotificationFailed"}'], $this->logger->logged);
        self::assertNull(new Submissions($this->repository, new NativeClock(), new HmacEmailDigester('key'), [$this->failing()])->submit($kept->form, Forms::values())->locale);
    }

    public function testAnyErrorOfANotifierIsLoggedAndTheOthersStillRun(): void
    {
        $form = Forms::registration();
        $this->repository->save($form);
        $broken = new class implements EntryNotifier {
            public function notify(Entry $entry): void
            {
                throw new LogicException('Unable to render the template "@PhilForms/email.html.twig".');
            }
        };

        $entry = new Submissions($this->repository, $this->clock, new HmacEmailDigester('key'), [$broken, $this->notifier('last')], $this->logger)->submit($form, Forms::values());

        self::assertInstanceOf(Entry::class, $this->repository->entry($entry->id));
        self::assertSame(['last '.$entry->id], $this->heard);
        self::assertSame(['error Entry {entry} of form "{form}": {reason} {"entry":"'.$entry->id.'","form":"inscription","reason":"Unable to render the template \\"@PhilForms/email.html.twig\\".","error":"LogicException"}'], $this->logger->logged);
        self::assertStringNotContainsString('Ada', implode(' ', $this->logger->logged));
    }

    public function testRefusedValuesKeepNothing(): void
    {
        $form = Forms::registration();
        $this->repository->save($form);

        try {
            new Submissions($this->repository, new NativeClock(), new HmacEmailDigester('key'), [$this->notifier('first')])->submit($form, ['name' => 'Ada']);
            self::fail('The entry was taken.');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame(['email' => 'required', 'consent' => 'required'], $invalidEntry->errors);
        }

        self::assertSame([[], []], [$this->repository->entries($form), $this->heard]);
    }

    public function testFormsAndEntriesAreFound(): void
    {
        $registration = Forms::registration();
        $contact = Forms::registration('contact');
        $this->repository->save($registration, $contact);
        $submissions = new Submissions($this->repository, $this->clock, new HmacEmailDigester('key'));
        $ids = [];

        foreach (['2026-11-01', '2026-11-03', '2026-11-02'] as $day) {
            $this->clock->now = new DateTimeImmutable($day.' 12:00 UTC');
            $ids[$day] = $submissions->submit($registration, Forms::values())->id;
        }

        $submissions->submit($contact, Forms::values());
        $this->entityManager->clear();

        self::assertSame(['contact', 'inscription'], array_map(static fn (Form $form): string => $form->key, $this->repository->forms()));
        self::assertSame('contact', $this->repository->byKey('contact')?->key);
        self::assertSame($registration->id, $this->repository->get($registration->id)?->id);
        self::assertNull($this->repository->byKey('none'));
        self::assertSame([$ids['2026-11-03'], $ids['2026-11-02'], $ids['2026-11-01']], $this->ids($this->repository->entries($registration)));
        self::assertSame([$ids['2026-11-02']], $this->ids($this->repository->entries($registration, 1, 1)));

        $more = Forms::registration('more');
        $this->repository->save($more);

        for ($i = 0; $i < 51; ++$i) {
            $submissions->submit($more, Forms::values());
        }

        self::assertCount(50, $this->repository->entries($more));
        self::assertNull($this->repository->entry('none'));
    }

    public function testEntriesAreDeletedOnceTheirTimeIsOver(): void
    {
        $form = Forms::registration();
        $short = Forms::registration('short');
        $short->keepFor(1);

        $this->repository->save($form, $short);
        $submissions = new Submissions($this->repository, $this->clock, new HmacEmailDigester('key'));
        $old = $submissions->submit($form, Forms::values());
        $brief = $submissions->submit($short, Forms::values());
        $this->clock->now = new DateTimeImmutable('2026-11-10 12:00 UTC');
        $recent = $submissions->submit($form, Forms::values());

        self::assertSame(0, new Retention($this->repository, new MutableClock(new DateTimeImmutable('2026-11-05 11:59:59 UTC')))->purge(10));
        self::assertSame([$brief->id], $this->ids($this->repository->expired(new DateTimeImmutable('2026-11-05 07:00 America/Toronto'), 10)));
        self::assertSame([$brief->id, $old->id], $this->ids($this->repository->expired(new DateTimeImmutable('2026-12-04 12:00 UTC'), 10)));
        self::assertSame(1, new Retention($this->repository, new MutableClock(new DateTimeImmutable('2026-12-04 12:00 UTC')))->purge(1));
        self::assertSame(1, new Retention($this->repository, new MutableClock(new DateTimeImmutable('2026-12-04 12:00 UTC')))->purge(10));
        $this->entityManager->clear();
        self::assertNull($this->repository->entry($old->id));
        self::assertSame([$recent->id], $this->ids($this->repository->entries($form)));
        self::assertSame(0, new Retention($this->repository, new NativeClock())->purge(10));

        $other = $submissions->submit($this->repository->byKey('short') ?? $short, Forms::values());
        $this->repository->remove($this->repository->byKey('inscription') ?? $form);
        $this->entityManager->clear();
        self::assertNull($this->repository->entry($recent->id));
        self::assertSame($other->id, $this->repository->entry($other->id)?->id);
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function ids(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->id, $entries);
    }

    private function notifier(string $name): EntryNotifier
    {
        return new readonly class($name, $this) implements EntryNotifier {
            public function __construct(
                private string $name,
                private SubmissionsTest $test,
            ) {
            }

            public function notify(Entry $entry): void
            {
                $this->test->hear($this->name.' '.$entry->id);
            }
        };
    }

    private function failing(): EntryNotifier
    {
        return new class implements EntryNotifier {
            public function notify(Entry $entry): void
            {
                throw NotificationFailed::because('webhook', new RuntimeException('Connection refused.'));
            }
        };
    }

    public function hear(string $what): void
    {
        $this->heard[] = $what;
    }
}
