<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use Phil\ContactBundle\Infrastructure\Form\PersonNameTransformer;
use Phil\ContactBundle\Infrastructure\Form\PersonNameType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Forms;

#[CoversClass(PersonNameType::class)]
#[CoversClass(PersonNameTransformer::class)]
final class PersonNameTypeTest extends TestCase
{
    public function testANameIsSubmittedInItsParts(): void
    {
        $form = Forms::createFormFactory()->create(PersonNameType::class);
        $form->submit(['honorific' => 'Dr.', 'given' => 'Ada', 'additional' => " Augusta \t Byron  ".str_repeat(' ', 12), 'family' => 'Lovelace', 'secondFamily' => 'King', 'generation' => 'II', 'credential' => 'PhD', 'order' => 'family-first', 'culture' => 'en_GB']);

        $name = $form->getData();
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertInstanceOf(PersonName::class, $name);
        self::assertTrue($name->equals(PersonName::of('Ada', 'Lovelace', null, ['Augusta', 'Byron'], 'King', 'Dr.', 'II', 'PhD', 'en-GB', NameOrder::FamilyFirst)));
    }

    public function testTheCultureOfTheFormWins(): void
    {
        $form = Forms::createFormFactory()->create(PersonNameType::class, null, ['culture' => 'nl-BE']);
        $form->submit(['given' => 'Herman', 'particle' => 'Van', 'family' => 'Rompuy']);

        self::assertFalse($form->has('culture'));
        self::assertSame('Van Rompuy, Herman', $form->getData()?->sortKey());
        self::assertSame('nl-BE', new PersonNameTransformer('nl-BE')->reverseTransform(['honorific' => null, 'given' => 'Ada', 'additional' => null, 'particle' => null, 'family' => null, 'secondFamily' => null, 'generation' => null, 'credential' => null, 'order' => null, 'culture' => 'fr'])?->culture);
    }

    public function testANameIsShownInItsFields(): void
    {
        $name = PersonName::of('Ada', 'Lovelace', additional: ['Augusta', 'Byron'], culture: 'en', order: NameOrder::GivenFirst);
        $view = Forms::createFormFactory()->create(PersonNameType::class, $name)->createView();

        self::assertSame(['Ada', 'Augusta Byron', 'Lovelace', 'given-first', 'en', ''], [$view['given']->vars['value'], $view['additional']->vars['value'], $view['family']->vars['value'], $view['order']->vars['value'], $view['culture']->vars['value'], $view['particle']->vars['value']]);
        self::assertSame(['autocomplete' => 'given-name'], $view['given']->vars['attr']);
        self::assertSame(['autocomplete' => 'family-name'], $view['family']->vars['attr']);
        self::assertSame(['autocomplete' => 'additional-name'], $view['additional']->vars['attr']);
        self::assertSame(['autocomplete' => 'honorific-prefix'], $view['honorific']->vars['attr']);
        self::assertSame(['autocomplete' => 'honorific-suffix'], $view['generation']->vars['attr']);
        self::assertSame([], $view['particle']->vars['attr']);
        self::assertSame(['autocomplete' => 'off', 'spellcheck' => 'false'], $view['culture']->vars['attr']);
        self::assertSame(['person_name.given', 'person_name.order', 'person_name.order.culture', 'person_name.culture', false], [$view['given']->vars['label'], $view['order']->vars['label'], $view['order']->vars['placeholder'], $view['culture']->vars['label'], $view['given']->vars['required']]);
        $choices = $view['order']->vars['choices'];
        self::assertIsArray($choices);
        self::assertInstanceOf(ChoiceView::class, $choices[1]);
        self::assertSame('person_name.order.family-first', $choices[1]->label);
        self::assertSame(['phil_contact', 'This name is not valid.', null], [$view->vars['translation_domain'], Forms::createFormFactory()->create(PersonNameType::class)->getConfig()->getOption('invalid_message'), Forms::createFormFactory()->create(PersonNameType::class)->getConfig()->getOption('culture')]);
    }

    public function testNoFieldOfTheNameIsRequired(): void
    {
        $form = Forms::createFormFactory()->create(PersonNameType::class);

        foreach (['honorific', 'given', 'additional', 'particle', 'family', 'secondFamily', 'generation', 'credential', 'order', 'culture'] as $field) {
            self::assertFalse($form->get($field)->isRequired(), $field);
        }
    }

    public function testANameWithoutGivenNorFamilyNameIsNone(): void
    {
        $form = Forms::createFormFactory()->create(PersonNameType::class);
        $form->submit(['honorific' => 'Dr.', 'additional' => 'Augusta']);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
        self::assertNull(new PersonNameTransformer()->reverseTransform(null));
        self::assertSame(['honorific' => null, 'given' => null, 'additional' => null, 'particle' => null, 'family' => null, 'secondFamily' => null, 'generation' => null, 'credential' => null, 'order' => null, 'culture' => null], new PersonNameTransformer()->transform(null));
        self::assertNull(new PersonNameTransformer()->transform(PersonName::of('Ada'))['additional']);
        self::assertSame([[], null], [new PersonNameTransformer()->reverseTransform(['honorific' => null, 'given' => null, 'additional' => null, 'particle' => null, 'family' => 'Lovelace', 'secondFamily' => null, 'generation' => null, 'credential' => null, 'order' => null])?->additional, new PersonNameTransformer()->reverseTransform(['honorific' => null, 'given' => 'Ada', 'additional' => '  ', 'particle' => null, 'family' => null, 'secondFamily' => null, 'generation' => null, 'credential' => null, 'order' => null])?->culture]);
    }

    public function testABadNameMakesTheFormInvalid(): void
    {
        $form = Forms::createFormFactory()->create(PersonNameType::class);
        $form->submit(['given' => 'Ada', 'particle' => 'de']);

        self::assertFalse($form->isValid());
        self::assertNull($form->getData());

        try {
            new PersonNameTransformer()->reverseTransform(['honorific' => null, 'given' => 'Ada', 'additional' => null, 'particle' => null, 'family' => null, 'secondFamily' => null, 'generation' => null, 'credential' => null, 'order' => null, 'culture' => 'français']);
            self::fail('A bad culture was read.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('"français" is not a language tag.', $transformationFailedException->getMessage());
            self::assertNotNull($transformationFailedException->getPrevious());
        }
    }
}
