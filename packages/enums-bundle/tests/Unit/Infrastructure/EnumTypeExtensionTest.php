<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\Enums\Attribute\Group;
use Phil\Enums\Collection\CaseCollection;
use Phil\EnumsBundle\Infrastructure\Form\EnumTypeExtension;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Priority;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use UnitEnum;

#[CoversClass(EnumTypeExtension::class)]
final class EnumTypeExtensionTest extends TestCase
{
    public function testItExtendsTheEnumType(): void
    {
        self::assertSame([EnumType::class], [...EnumTypeExtension::getExtendedTypes()]);
    }

    public function testWithoutCriterionEveryCaseIsOffered(): void
    {
        self::assertSame(OrderStatus::cases(), $this->choices(['class' => OrderStatus::class]));
    }

    public function testAGroupNarrowsTheChoices(): void
    {
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT], $this->choices(['class' => OrderStatus::class, 'group' => 'customer']));
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], $this->choices(['class' => OrderStatus::class, 'group' => ['customer', 'staff']]));
    }

    public function testOnlyExceptAndWhereMetaNarrowTheChoices(): void
    {
        self::assertSame([OrderStatus::PENDING, OrderStatus::REFUNDED], $this->choices(['class' => OrderStatus::class, 'only' => [OrderStatus::REFUNDED, OrderStatus::PENDING]]));
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], $this->choices(['class' => OrderStatus::class, 'except' => [OrderStatus::REFUNDED]]));
        self::assertSame([OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED], $this->choices(['class' => OrderStatus::class, 'where_meta' => [Priority::class => 2]]));
    }

    public function testACaseCollectionIsAcceptedAsChoices(): void
    {
        self::assertSame([OrderStatus::CANCELLED], $this->choices(['class' => OrderStatus::class, 'choices' => CaseCollection::of([OrderStatus::CANCELLED])]));
    }

    public function testExplicitChoicesStayAsGivenWithoutCriterion(): void
    {
        self::assertSame([OrderStatus::REFUNDED], $this->choices(['class' => OrderStatus::class, 'choices' => [OrderStatus::REFUNDED]]));
    }

    public function testAGroupMustBeAStringOrAList(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(EnumType::class, null, ['class' => OrderStatus::class, 'group' => 5]);
    }

    public function testATranslatableCaseIsItsOwnLabel(): void
    {
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT, OrderStatus::CANCELLED, OrderStatus::REFUNDED], $this->labels(['class' => OrderStatus::class]));
    }

    public function testAnotherCaseIsLabelledByItsLabelOrItsName(): void
    {
        self::assertSame(['Round', 'BigSquare', 'Blank', 'Éclair'], $this->labels(['class' => Shape::class]));
    }

    public function testAStringKeyIsTheLabel(): void
    {
        self::assertSame('Rond', EnumTypeExtension::label(Shape::Circle, 'Rond'));
        self::assertSame('Round', EnumTypeExtension::label(Shape::Circle, 0));
        self::assertSame(OrderStatus::PENDING, EnumTypeExtension::label(OrderStatus::PENDING, 0));
    }

    public function testDeprecatedCasesAreLeftOutOnlyWhenAsked(): void
    {
        self::assertSame(Access::cases(), $this->choices(['class' => Access::class]));
        self::assertSame([Access::Read, Access::Write], $this->choices(['class' => Access::class, 'without_deprecated' => true]));
        self::assertSame([Access::Write], $this->choices(['class' => Access::class, 'without_deprecated' => true, 'except' => [Access::Read]]));
        self::assertSame([Access::Write], $this->choices(['class' => Access::class, 'without_deprecated' => true, 'choices' => [Access::from(4), 'not a case' => 'x', Access::Write]]));
    }

    public function testWithoutDeprecatedMustBeABoolean(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(EnumType::class, null, ['class' => Access::class, 'without_deprecated' => 'yes']);
    }

    public function testChoicesAreGroupedByTheFirstValueOfAMetaProperty(): void
    {
        $choices = $this->factory()->create(EnumType::class, null, ['class' => OrderStatus::class, 'group_by_meta' => Group::class])->createView()->vars['choices'];
        self::assertIsArray($choices);

        self::assertSame(['customer', 'staff', 3], array_keys($choices));
        self::assertInstanceOf(ChoiceGroupView::class, $choices['customer']);
        self::assertSame([OrderStatus::PENDING, OrderStatus::IN_TRANSIT], array_map(static fn (mixed $view): mixed => $view instanceof ChoiceView ? $view->data : null, array_values($choices['customer']->choices)));
        self::assertInstanceOf(ChoiceGroupView::class, $choices['staff']);
        self::assertSame([OrderStatus::CANCELLED], array_map(static fn (mixed $view): mixed => $view instanceof ChoiceView ? $view->data : null, array_values($choices['staff']->choices)));
        self::assertInstanceOf(ChoiceView::class, $choices[3]);
        self::assertSame(OrderStatus::REFUNDED, $choices[3]->data);
    }

    public function testAnIntegerMetaValueGroupsToo(): void
    {
        $choices = $this->factory()->create(EnumType::class, null, ['class' => OrderStatus::class, 'group_by_meta' => Priority::class])->createView()->vars['choices'];
        self::assertIsArray($choices);

        self::assertSame([1, 2, 3], array_keys($choices));
        self::assertInstanceOf(ChoiceGroupView::class, $choices[2]);
        self::assertSame('2', $choices[2]->label);
    }

    public function testWithoutGroupByMetaTheGroupByOptionIsKept(): void
    {
        $choices = $this->factory()->create(EnumType::class, null, ['class' => Access::class, 'group_by' => static fn (): string => 'all'])->createView()->vars['choices'];
        self::assertIsArray($choices);

        self::assertSame(['all'], array_keys($choices));
    }

    public function testThePreferredGroupIsOfferedFirst(): void
    {
        $view = $this->factory()->create(EnumType::class, null, ['class' => Access::class, 'preferred_group' => 'editor'])->createView();
        self::assertIsArray($view->vars['preferred_choices']);

        self::assertSame([Access::Write], array_map(static fn (mixed $choice): mixed => $choice instanceof ChoiceView ? $choice->data : null, array_values($view->vars['preferred_choices'])));

        $view = $this->factory()->create(EnumType::class, null, ['class' => Access::class, 'preferred_group' => ['nothing'], 'preferred_choices' => [Access::Read]])->createView();
        self::assertSame([], $view->vars['preferred_choices']);

        $view = $this->factory()->create(EnumType::class, null, ['class' => Access::class, 'preferred_choices' => [Access::Read]])->createView();
        self::assertIsArray($view->vars['preferred_choices']);
        self::assertCount(1, $view->vars['preferred_choices']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<mixed>
     */
    private function choices(array $options): array
    {
        return array_map(static fn (ChoiceView $view): mixed => $view->data, array_values($this->views($options)));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<mixed>
     */
    private function labels(array $options): array
    {
        return array_map(static fn (ChoiceView $view): mixed => $view->label, array_values($this->views($options)));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<array-key, ChoiceView>
     */
    private function views(array $options): array
    {
        $choices = $this->factory()->create(EnumType::class, null, $options)->createView()->vars['choices'];
        self::assertIsArray($choices);

        $views = [];

        foreach ($choices as $key => $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertInstanceOf(UnitEnum::class, $choice->data);
            $views[$key] = $choice;
        }

        return $views;
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addTypeExtension(new EnumTypeExtension())->getFormFactory();
    }
}
