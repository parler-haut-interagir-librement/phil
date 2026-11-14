<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Contract\ChoicePlaces;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;

/**
 * The places of the choices that have a capacity: taken by the entries that keep them, given back when an entry is
 * refused, cannot be kept, or is removed.
 */
#[CoversClass(Submissions::class)]
#[CoversClass(DoctrineFormRepository::class)]
final class ChoicePlacesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineFormRepository $repository;

    private Form $form;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineFormRepository($this->entityManager);
        $this->form = Form::create('workshops', 'Workshops', [
            Field::of('morning', 'Morning', FieldType::Choice, choices: ['Painting', 'Dice', 'Open'], capacities: ['Painting' => 2, 'Dice' => 1]),
            Field::of('evening', 'Evening', FieldType::Choice, choices: ['Quiz', 'Open'], capacities: ['Quiz' => 1]),
        ]);
        $this->repository->save($this->form);
    }

    private function submissions(?FormRepository $forms = null): Submissions
    {
        return new Submissions($forms ?? $this->repository, new MutableClock(new DateTimeImmutable('2026-11-11 12:00 UTC')), new HmacEmailDigester('key'), places: $this->repository);
    }

    public function testAnEntryTakesAPlaceInEachChoiceThatHasACapacity(): void
    {
        $submissions = $this->submissions();
        $first = $submissions->submit($this->form, ['morning' => 'Painting', 'evening' => 'Quiz']);
        $submissions->submit($this->form, ['morning' => 'Open', 'evening' => 'Open']);

        self::assertSame([1, 1, 0, 0], [
            $this->repository->taken($this->form, 'morning', 'Painting'),
            $this->repository->taken($this->form, 'evening', 'Quiz'),
            $this->repository->taken($this->form, 'morning', 'Dice'),
            $this->repository->taken($this->form, 'morning', 'Open'),
        ]);
        self::assertSame(['evening' => ['Quiz']], $submissions->fullChoices($this->form));

        try {
            $submissions->submit($this->form, ['morning' => 'Painting', 'evening' => 'Quiz']);
            self::fail('full');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame(['evening' => 'full'], $invalidEntry->errors);
        }

        self::assertSame(1, $this->repository->taken($this->form, 'morning', 'Painting'), 'the place taken in the morning is given back');
        self::assertCount(2, $this->repository->entries($this->form));

        $this->repository->remove($first);
        self::assertSame([0, 0], [$this->repository->taken($this->form, 'morning', 'Painting'), $this->repository->taken($this->form, 'evening', 'Quiz')], 'removed, its places are given back');
        $this->repository->release($this->form, 'evening', 'Quiz');
        self::assertSame(0, $this->repository->taken($this->form, 'evening', 'Quiz'), 'never below none');
        self::assertSame([], $submissions->fullChoices($this->form));
    }

    public function testThePlacesAreCountedUnderTheCapacity(): void
    {
        self::assertFalse($this->repository->take($this->form, 'morning', 'Dice', 0), 'no capacity, no place');
        self::assertSame(0, $this->repository->taken($this->form, 'morning', 'Dice'));
        self::assertSame([true, true, false, false], [
            $this->repository->take($this->form, 'morning', 'Painting', 2),
            $this->repository->take($this->form, 'morning', 'Painting', 2),
            $this->repository->take($this->form, 'morning', 'Painting', 2),
            $this->repository->take($this->form, 'morning', 'Painting', 2),
        ]);
        self::assertTrue($this->repository->take($this->form, 'morning', 'Painting', 3), 'a capacity raised offers a place again');
        self::assertSame(3, $this->repository->taken($this->form, 'morning', 'Painting'));
        self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM phil_form_choice_count'));

        $this->repository->remove($this->form);
        self::assertSame(0, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM phil_form_choice_count'), 'the counts leave with the form');
    }

    public function testEveryFullChoiceIsListedAsTextWhateverItsKey(): void
    {
        $form = Form::create('years', 'Years', [
            Field::of('first', 'First', FieldType::Choice, choices: ['2024', 'open'], capacities: ['2024' => 1]),
            Field::of('second', 'Second', FieldType::Choice, choices: ['7', 'open'], capacities: ['7' => 1]),
        ]);
        $this->repository->save($form);
        $submissions = $this->submissions();
        $submissions->submit($form, ['first' => '2024', 'second' => '7']);

        self::assertSame(['first' => ['2024'], 'second' => ['7']], $submissions->fullChoices($form));
    }

    public function testAChoiceWithoutCapacityBeforeAChoiceWithOneDoesNotKeepItsPlaceFromBeingTaken(): void
    {
        $this->submissions()->submit($this->form, ['morning' => 'Open', 'evening' => 'Quiz']);

        self::assertSame(1, $this->repository->taken($this->form, 'evening', 'Quiz'));
    }

    public function testOnlyTheChoicesThatHaveACapacityGiveTheirPlaceBack(): void
    {
        $this->repository->take($this->form, 'morning', 'Open', 5);
        $entry = $this->submissions()->submit($this->form, ['morning' => 'Open', 'evening' => 'Quiz']);

        $this->repository->remove($entry);

        self::assertSame([1, 0], [$this->repository->taken($this->form, 'morning', 'Open'), $this->repository->taken($this->form, 'evening', 'Quiz')]);
    }

    public function testTheCountsOfOtherFormsStayWhenAFormLeaves(): void
    {
        $other = Form::create('other', 'Other', [Field::of('slot', 'Slot', FieldType::Choice, choices: ['A'], capacities: ['A' => 3])]);
        $this->repository->save($other);
        $this->repository->take($other, 'slot', 'A', 3);
        $this->repository->take($this->form, 'morning', 'Dice', 1);

        $this->repository->remove($this->form);

        self::assertSame(1, $this->repository->taken($other, 'slot', 'A'));
    }

    public function testThePlacesAreGivenBackWhenTheEntryCannotBeKept(): void
    {
        $failing = new readonly class($this->repository) implements FormRepository {
            public function __construct(
                private DoctrineFormRepository $inner,
            ) {
            }

            #[Override]
            public function save(Form|Entry ...$objects): void
            {
                throw new RuntimeException('The database is down.');
            }

            #[Override]
            public function remove(Form|Entry ...$objects): void
            {
            }

            #[Override]
            public function get(string $id): ?Form
            {
                return $this->inner->get($id);
            }

            #[Override]
            public function byKey(string $key): ?Form
            {
                return null;
            }

            #[Override]
            public function forms(): array
            {
                return [];
            }

            #[Override]
            public function entries(Form $form, int $limit = 50, int $offset = 0): array
            {
                return [];
            }

            #[Override]
            public function entry(string $id): ?Entry
            {
                return null;
            }

            #[Override]
            public function expired(DateTimeImmutable $now, int $limit): array
            {
                return [];
            }

            #[Override]
            public function about(string $subject, string $emailDigest): array
            {
                return [];
            }
        };

        try {
            $this->submissions($failing)->submit($this->form, ['morning' => 'Dice', 'evening' => 'Quiz']);
            self::fail('down');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The database is down.', $runtimeException->getMessage());
        }

        self::assertSame([0, 0], [$this->repository->taken($this->form, 'morning', 'Dice'), $this->repository->taken($this->form, 'evening', 'Quiz')]);
    }

    public function testWithoutPlacesNothingIsCountedAndTheEvaluatorAndSealerServe(): void
    {
        $form = Form::create('a', 'A', [
            Field::of('size', 'Size', FieldType::Choice, choices: ['S'], capacities: ['S' => 1]),
            Field::of('note', 'Note', FieldType::Text, shownWhen: ['==' => [['var' => 'size'], 'S']], sensitive: true),
        ]);
        $this->repository->save($form);
        $logic = new class implements ConditionEvaluator {
            #[Override]
            public function fields(array $condition): array
            {
                return [];
            }

            #[Override]
            public function check(array $condition, array $fields): void
            {
            }

            #[Override]
            public function holds(array $condition, array $values): bool
            {
                return true;
            }
        };
        $sealer = new class implements FieldSealer {
            #[Override]
            public function seal(#[SensitiveParameter] string $value, string $context): string
            {
                return 'sealed';
            }

            #[Override]
            public function open(string $sealed, string $context): string
            {
                return $sealed;
            }
        };
        $submissions = new Submissions($this->repository, new MutableClock(new DateTimeImmutable('2026-11-11 12:00 UTC')), new HmacEmailDigester('key'), logic: $logic, sealer: $sealer);

        $entry = $submissions->submit($form, ['size' => 'S', 'note' => 'n']);
        $submissions->submit($form, ['size' => 'S']);
        self::assertSame(['size' => 'S', 'note' => 'sealed'], $entry->values);
        self::assertSame([[], 0], [$submissions->fullChoices($form), $this->repository->taken($form, 'size', 'S')]);
        self::assertSame(['size' => 'S', 'note' => 'n'], new Submissions($this->repository, new MutableClock(new DateTimeImmutable()), new HmacEmailDigester('key'), allowUnsealed: true)->submit($form, ['size' => 'S', 'note' => 'n'], tiers: ['x'])->values, "the bundle's conditions, and no sealer when the application allows it");
    }

    public function testATierChoosesTheFieldsOfTheEntry(): void
    {
        $form = Form::create('t', 'T', [Field::of('a', 'A', FieldType::Text), Field::of('vip', 'VIP', FieldType::Text, tiers: ['vip'])]);
        $this->repository->save($form);

        self::assertSame(['a' => 'x', 'vip' => 'y'], $this->submissions()->submit($form, ['a' => 'x', 'vip' => 'y'], tiers: ['vip'])->values);
        self::assertSame(['a' => 'x'], $this->submissions()->submit($form, ['a' => 'x', 'vip' => 'y'])->values);
        self::assertInstanceOf(ChoicePlaces::class, $this->repository);
    }
}
