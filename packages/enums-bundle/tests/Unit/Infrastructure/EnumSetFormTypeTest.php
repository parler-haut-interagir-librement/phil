<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Form\EnumSetType;
use Phil\EnumsBundle\Infrastructure\Form\EnumTypeExtension;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(EnumSetType::class)]
final class EnumSetFormTypeTest extends TestCase
{
    public function testItIsAnExpandedMultipleEnumType(): void
    {
        $form = $this->factory()->create(EnumSetType::class, null, ['class' => Access::class]);

        self::assertSame(EnumType::class, new EnumSetType()->getParent());
        self::assertTrue($form->getConfig()->getOption('multiple'));
        self::assertTrue($form->getConfig()->getOption('expanded'));
        self::assertFalse($this->factory()->create(EnumSetType::class, null, ['class' => Access::class, 'expanded' => false])->getConfig()->getOption('expanded'));
    }

    public function testTheSetChecksItsCases(): void
    {
        $form = $this->factory()->create(EnumSetType::class, EnumSet::of(Access::class, Access::Write), ['class' => Access::class]);

        self::assertSame(['2'], $form->getViewData());
        self::assertSame([], $this->factory()->create(EnumSetType::class, null, ['class' => Access::class])->getViewData());
    }

    public function testASubmissionIsASet(): void
    {
        $form = $this->factory()->create(EnumSetType::class, null, ['class' => Access::class]);
        $form->submit(['1', '2']);

        $data = $form->getData();
        self::assertInstanceOf(EnumSet::class, $data);
        self::assertSame([Access::Read, Access::Write], [...$data]);
    }

    public function testAnEmptySubmissionIsTheEmptySet(): void
    {
        $form = $this->factory()->create(EnumSetType::class, EnumSet::of(Access::class, Access::Read), ['class' => Access::class]);
        $form->submit([]);

        $data = $form->getData();
        self::assertInstanceOf(EnumSet::class, $data);
        self::assertTrue($data->isEmpty());
        self::assertSame(Access::class, $data->enum());
    }

    public function testTheEnumTypeOptionsApply(): void
    {
        $form = $this->factory()->create(EnumSetType::class, null, ['class' => Access::class, 'group' => 'editor']);
        $form->submit(['2']);

        self::assertTrue($form->isSynchronized());
        self::assertCount(1, $form->createView()->children);
    }

    public function testASetOfAnotherEnumIsRefused(): void
    {
        $form = $this->factory()->create(EnumSetType::class, null, ['class' => Access::class]);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('Expected an EnumSet of '.Access::class.', got '.EnumSet::class.'.');

        $form->setData(EnumSet::of(Shape::class, Shape::Circle));
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addTypeExtension(new EnumTypeExtension())->getFormFactory();
    }
}
