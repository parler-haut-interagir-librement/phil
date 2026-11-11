<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit\Infrastructure;

use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

#[CoversClass(MarkupFormatType::class)]
final class MarkupFormatTypeTest extends TestCase
{
    public function testTheEnabledFormatsAreOffered(): void
    {
        $form = $this->factory()->create(MarkupFormatType::class);
        $choices = $form->createView()->vars['choices'];
        self::assertIsArray($choices);

        $offered = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $offered[] = [$choice->value, $choice->label];
        }

        self::assertSame([['ujo', 'Block editor (UJO)'], ['bbcode', 'BBCode'], ['text', 'Plain text']], $offered);
        self::assertSame('phil_markup', $form->createView()->vars['choice_translation_domain']);

        $form->submit('bbcode');
        self::assertSame('bbcode', $form->getData());
    }

    public function testAFormatNotEnabledIsNotValid(): void
    {
        $form = $this->factory()->create(MarkupFormatType::class);
        $form->submit('markdown');

        self::assertFalse($form->isSynchronized());
        self::assertNull($form->getData());
    }

    public function testEveryFormatHasALabel(): void
    {
        self::assertSame(MarkupFormat::values(), array_keys(MarkupFormatType::LABELS));
    }

    /**
     * A format an add-on brings is offered with the label it gives; a name without a label is its own label.
     */
    public function testTheFormatOfAnAddOnIsOfferedWithItsLabel(): void
    {
        $type = new MarkupFormatType(new MarkupFormats(['shout', 'markdown', 'plain'], 'shout', labels: ['shout' => 'Shout!', 'markdown' => 'Not this one']));
        $form = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([$type], []))->getFormFactory()->create(MarkupFormatType::class);
        $choices = $form->createView()->vars['choices'];
        self::assertIsArray($choices);

        self::assertSame([['shout', 'Shout!'], ['markdown', 'Markdown'], ['plain', 'plain']], array_map(static fn (mixed $choice): array => $choice instanceof ChoiceView ? [$choice->value, $choice->label] : [], array_values($choices)));

        $form->submit('shout');
        self::assertSame('shout', $form->getData());
    }

    private function factory(): FormFactoryInterface
    {
        $type = new MarkupFormatType(new MarkupFormats(['ujo', 'bbcode', 'text'], 'bbcode'));

        return Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([$type], []))->getFormFactory();
    }
}
