<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Privacy\FormEntriesPrivacy;
use Phil\FormsBundle\Infrastructure\Security\TokenCurrentSubject;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An access or erasure request finds the entries about a person by the identifier they were known by, or by the
 * address of a form's designated email field.
 */
#[CoversClass(FormEntriesPrivacy::class)]
#[CoversClass(DoctrineFormRepository::class)]
#[CoversClass(TokenCurrentSubject::class)]
final class PrivacyTest extends TestCase
{
    private DoctrineFormRepository $repository;

    private HmacEmailDigester $digester;

    private Form $form;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new DoctrineFormRepository(Database::entityManager());
        $this->digester = new HmacEmailDigester('key');
        $this->form = Forms::registration();
        $this->form->identifyBy('email');

        $this->repository->save($this->form);
    }

    public function testTheEntriesAboutAPersonAreExported(): void
    {
        $known = $this->entry(Forms::values(), '2026-11-01 10:00', 'member-7');
        $byAddress = $this->entry(['email' => 'Ada@Example.com'] + Forms::values(), '2026-11-02 10:00');
        $this->entry(['email' => 'grace@example.com'] + Forms::values(), '2026-11-03 10:00', 'member-8');
        $privacy = new FormEntriesPrivacy($this->repository, $this->digester);

        self::assertSame('forms', $privacy->module()->value);
        self::assertSame([$known->id], $this->ids($privacy->export(DataSubject::fromString('member-7'))));
        self::assertSame([$known->id, $byAddress->id], $this->ids($privacy->export(DataSubject::fromString('ada@example.com'))), 'every entry carrying the address, oldest first');
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));

        $entries = $privacy->export(DataSubject::fromString('ADA@example.com'))['entries'] ?? null;
        self::assertIsArray($entries);
        self::assertSame([
            'id' => $byAddress->id,
            'form' => 'inscription',
            'title' => 'Inscription au tournoi',
            'submitted_at' => '2026-11-02T10:00:00+00:00',
            'delete_after' => '2026-12-02T10:00:00+00:00',
            'locale' => 'fr',
            'values' => $byAddress->values,
        ], $entries[1] ?? null, 'the address found however it is written');
    }

    public function testErasingDeletesTheEntriesAndCanBeRunAgain(): void
    {
        $this->entry(Forms::values(), '2026-11-01 10:00', 'member-7');
        $this->entry(Forms::values(), '2026-11-02 10:00');
        $other = $this->entry(['email' => 'grace@example.com'] + Forms::values(), '2026-11-03 10:00');
        $privacy = new FormEntriesPrivacy($this->repository, $this->digester);

        $report = $privacy->erase(DataSubject::fromString('ada@example.com'));
        self::assertSame(['forms', ['phil_form_entry' => ErasureEffect::Deleted]], [$report->module->value, $report->effects]);
        self::assertSame([$other->id], array_map(static fn (Entry $entry): string => $entry->id, $this->repository->entries($this->form)));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('ada@example.com')), 'the same report once there is nothing left');
    }

    public function testTimesAreExportedInUtcWhateverTheDefaultZone(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $entityManager = Database::entityManager();
            $repository = new DoctrineFormRepository($entityManager);
            $form = Forms::registration();
            $repository->save($form);
            $entry = $form->submit(Forms::values(), new DateTimeImmutable('2026-11-02 14:00 UTC'), 'fr', 'member-7', $this->digester);
            $repository->save($entry);
            $entityManager->clear();

            $exported = new FormEntriesPrivacy($repository, $this->digester)->export(DataSubject::fromString('member-7'))['entries'] ?? null;
            self::assertIsArray($exported);
            self::assertIsArray($exported[0] ?? null);
            self::assertSame(['2026-11-02T14:00:00+00:00', '2026-12-02T14:00:00+00:00'], [$exported[0]['submitted_at'] ?? null, $exported[0]['delete_after'] ?? null]);
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testAnIdentifierWithASpaceFindsItsEntries(): void
    {
        $entry = $this->entry(Forms::values(), '2026-11-01 10:00', 'Jean Dupont');
        $privacy = new FormEntriesPrivacy($this->repository, $this->digester);

        // A data subject holds no whitespace: the request names the person by the key their entries are kept under.
        $subject = DataSubject::fromString(Entry::subjectKey('Jean Dupont'));
        self::assertSame([$entry->id], $this->ids($privacy->export($subject)));
        $privacy->erase($subject);
        self::assertSame([], $privacy->export($subject));
    }

    public function testTheSignedInUserIsTheSubject(): void
    {
        $tokens = new TokenStorage();
        $subject = new TokenCurrentSubject($tokens);
        self::assertNull($subject->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('member-7', null), 'main'));
        self::assertSame('member-7', $subject->current());
    }

    /**
     * @param array<string, string|bool> $values
     */
    private function entry(array $values, string $at, ?string $subject = null): Entry
    {
        $entry = $this->form->submit($values, new DateTimeImmutable($at.' UTC'), 'fr', $subject, $this->digester);
        $this->repository->save($entry);

        return $entry;
    }

    /**
     * @param array<string, mixed> $export
     *
     * @return list<mixed>
     */
    private function ids(array $export): array
    {
        $entries = $export['entries'] ?? [];
        self::assertIsArray($entries);

        return array_values(array_map(static fn (mixed $entry): mixed => \is_array($entry) ? $entry['id'] ?? null : null, $entries));
    }
}
