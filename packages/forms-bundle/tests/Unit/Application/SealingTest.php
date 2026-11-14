<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\SealedValues;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use Phil\FormsBundle\Domain\Exception\UnsealedSensitiveField;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Phil\FormsBundle\Infrastructure\KeyManagement\KeyManagementFieldSealer;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\FormsBundle\Tests\Unit\Fixtures\SpamProtectionOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms as FormFactories;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Throwable;

/**
 * A form with a sensitive field takes entries only where a sealer is configured, unless the application chose to keep
 * those values as sent; through Symfony's KeyManagement component, an entry's sensitive values go to the database
 * sealed and come back as they were sent.
 */
#[CoversClass(UnsealedSensitiveField::class)]
#[CoversClass(Form::class)]
#[CoversClass(Submissions::class)]
#[CoversClass(EntryType::class)]
#[CoversClass(SealedValues::class)]
final class SealingTest extends TestCase
{
    private const string MESSAGE = 'The field "health" of the form "camp" is sensitive and no sealer is configured: set "phil_forms.sealing.key" to a master key of Symfony\'s KeyManagement component, alias Phil\FormsBundle\Domain\Contract\FieldSealer to a sealer of the application, or set "phil_forms.allow_unsealed_sensitive: true" to keep sensitive values as sent.';

    private EntityManager $entityManager;

    private DoctrineFormRepository $repository;

    private Form $form;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineFormRepository($this->entityManager);
        $this->form = Form::create('camp', 'Camp', [
            Field::of('name', 'Name', FieldType::Text, required: true),
            Field::of('health', 'Health', FieldType::Textarea, sensitive: true),
            Field::of('allergy', 'Allergy', FieldType::Text, sensitive: true),
        ]);
        $this->repository->save($this->form);
    }

    public function testAFormWithASensitiveFieldIsRefusedWithoutASealer(): void
    {
        $this->assertRefused(fn (): mixed => $this->form->submit(['name' => 'Ada', 'health' => 'Asthma'], new DateTimeImmutable()));
        $this->assertRefused(fn (): mixed => $this->form->submit(['name' => 'Ada'], new DateTimeImmutable()), 'even without a sensitive value in the entry');
        $this->assertRefused(fn (): mixed => $this->submissions()->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma']));
        $this->assertRefused(fn (): mixed => $this->formFactory(new EntryType())->create(EntryType::class, null, ['phil_form' => $this->form]), 'before the visitor types anything');
        self::assertSame([], $this->repository->entries($this->form), 'nothing kept');
    }

    public function testRefusingIsTheDefaultOfTheCheckAndOfTheSubmissions(): void
    {
        $this->assertRefused(function (): void {
            $this->form->checkSealing(null);
        }, 'the check');
        $this->assertRefused(fn (): mixed => new Submissions($this->repository, new MutableClock(new DateTimeImmutable('2026-11-12 12:00 UTC')), new HmacEmailDigester('key'))->submit($this->form, ['name' => 'Ada']), 'the submissions');
    }

    public function testAFormWithoutASensitiveFieldNeedsNoSealer(): void
    {
        $plain = Form::create('plain', 'Plain', [Field::of('name', 'Name', FieldType::Text)]);
        $this->repository->save($plain);

        self::assertSame(['name' => 'Ada'], $this->submissions()->submit($plain, ['name' => 'Ada'])->values);
        self::assertSame(['name'], array_keys($this->formFactory(new EntryType())->create(EntryType::class, null, ['phil_form' => $plain])->all()));
    }

    public function testTheApplicationMayKeepSensitiveValuesAsSent(): void
    {
        $entry = $this->submissions(allowUnsealed: true)->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma']);

        self::assertSame([['name' => 'Ada', 'health' => 'Asthma', 'allergy' => null], []], [$entry->values, $entry->sealed]);
        self::assertSame(['name', 'health', 'allergy'], array_keys($this->formFactory(new EntryType(allowUnsealed: true))->create(EntryType::class, null, ['phil_form' => $this->form])->all()));
        self::assertSame(['name' => 'Ada', 'health' => 'Asthma', 'allergy' => null], new SealedValues()->reveal($entry), 'nothing to open');
    }

    public function testSensitiveValuesRoundTripThroughKeyManagement(): void
    {
        $sealer = new KeyManagementFieldSealer($this->kms(), 'forms2026');
        self::assertSame(['name', 'health', 'allergy'], array_keys($this->formFactory(new EntryType($sealer))->create(EntryType::class, null, ['phil_form' => $this->form])->all()));

        $entry = $this->submissions($sealer)->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma', 'allergy' => 'Peanuts']);
        $this->entityManager->clear();
        $kept = $this->repository->entry($entry->id);

        self::assertNotNull($kept);
        self::assertSame(['health', 'allergy'], $kept->sealed);
        self::assertSame('Ada', $kept->values['name']);
        self::assertStringStartsWith('phs1.', (string) $kept->values['health']);
        self::assertStringNotContainsString('Asthma', (string) json_encode($kept->values));
        self::assertSame(['name' => 'Ada', 'health' => 'Asthma', 'allergy' => 'Peanuts'], new SealedValues($sealer)->reveal($kept));
        self::assertSame($entry->id, $kept->id);
    }

    public function testAValueDoesNotOpenUnderAnotherKeyOfTheSameName(): void
    {
        $entry = $this->submissions(new KeyManagementFieldSealer($this->kms(), 'forms2026'))->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma']);
        $other = new KeyManagementFieldSealer(new SodiumKms(new InMemoryKeyLoader(['forms2026' => str_repeat('z', 32)])), 'forms2026');

        $this->expectException(UnreadableValue::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of phil_forms.entry.'.$entry->id.'.health does not open.');

        new SealedValues($other)->reveal($entry);
    }

    public function testAValueDoesNotOpenOnceItsKeyIsGone(): void
    {
        $entry = $this->submissions(new KeyManagementFieldSealer($this->kms(), 'forms2026'))->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma']);
        $rotated = new KeyManagementFieldSealer(new SodiumKms(new InMemoryKeyLoader(['forms2027' => str_repeat('c', 32)])), 'forms2027');

        $this->expectException(UnreadableValue::class);

        new SealedValues($rotated)->reveal($entry);
    }

    public function testNoEntryIsKeptWhenTheMasterKeyIsMissing(): void
    {
        $submissions = $this->submissions(new KeyManagementFieldSealer($this->kms(), 'forms1999'));

        try {
            $submissions->submit($this->form, ['name' => 'Ada', 'health' => 'Asthma']);
            self::fail('An entry was sealed under a master key the KMS does not hold.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(KeyNotFoundException::class, $throwable);
            self::assertSame([], $this->repository->entries($this->form));
        }
    }

    private function assertRefused(callable $build, string $message = ''): void
    {
        try {
            $build();
            self::fail('Not refused: '.$message);
        } catch (UnsealedSensitiveField $unsealedSensitiveField) {
            self::assertSame(self::MESSAGE, $unsealedSensitiveField->getMessage(), $message);
        }
    }

    private function submissions(?KeyManagementFieldSealer $sealer = null, bool $allowUnsealed = false): Submissions
    {
        return new Submissions($this->repository, new MutableClock(new DateTimeImmutable('2026-11-12 12:00 UTC')), new HmacEmailDigester('key'), sealer: $sealer, allowUnsealed: $allowUnsealed);
    }

    private function formFactory(EntryType $type): FormFactoryInterface
    {
        return FormFactories::createFormFactoryBuilder()->addTypeExtension(new SpamProtectionOption())->addType($type)->getFormFactory();
    }

    private function kms(): SodiumKms
    {
        return new SodiumKms(new InMemoryKeyLoader(['forms2026' => str_repeat('b', 32)]));
    }
}
