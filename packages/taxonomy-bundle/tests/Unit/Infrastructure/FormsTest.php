<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit\Infrastructure;

use Locale;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Infrastructure\Form\CategoryType;
use Phil\TaxonomyBundle\Infrastructure\Form\TagsTransformer;
use Phil\TaxonomyBundle\Infrastructure\Form\TagsType;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\CountingRepository;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(TagsType::class)]
#[CoversClass(TagsTransformer::class)]
#[CoversClass(CategoryType::class)]
final class FormsTest extends TestCase
{
    public function testTagsAreTextSeparatedByCommas(): void
    {
        $form = $this->forms(new Taxonomy(new DoctrineTaxonomyRepository(Database::entityManager())))->create(TagsType::class, ['jeux', 'soirée']);

        self::assertSame('jeux, soirée', $form->createView()->vars['value']);
        $form->submit(' Jeux, , soirée, JEUX, rpg ');
        self::assertSame(['Jeux', 'soirée', 'rpg'], $form->getData());
        self::assertSame([false, 'taxonomy.help.tags', 'phil_taxonomy', TextType::class], [$form->getConfig()->getOption('required'), $form->getConfig()->getOption('help'), $form->getConfig()->getOption('translation_domain'), new TagsType()->getParent()]);
        self::assertSame(['', [], ['a'], ['Élan']], [new TagsTransformer()->transform(null), new TagsTransformer()->reverseTransform(null), new TagsTransformer()->reverseTransform('a'), new TagsTransformer()->reverseTransform('Élan, élan, ÉLAN')]);
    }

    public function testTagsAreBoundedInNumberAndLength(): void
    {
        $forms = $this->forms(new Taxonomy(new DoctrineTaxonomyRepository(Database::entityManager())));
        self::assertSame(20, $forms->create(TagsType::class)->getConfig()->getOption('max_tags'));

        $form = $forms->create(TagsType::class, null, ['max_tags' => 3]);
        $form->submit('a, b, A, c');
        self::assertTrue($form->isSynchronized());
        self::assertSame(['a', 'b', 'c'], $form->getData(), 'a repeated tag is not counted');

        $form = $forms->create(TagsType::class, null, ['max_tags' => 3]);
        $form->submit('a, b, c, d');
        self::assertFalse($form->isSynchronized());
        $failure = $form->getTransformationFailure();
        self::assertNotNull($failure);
        self::assertSame(['taxonomy.tags.too_many', ['{{ max }}' => '3']], [$failure->getInvalidMessage(), $failure->getInvalidMessageParameters()]);

        $form = $forms->create(TagsType::class);
        $form->submit('ok, '.str_repeat('é', Tag::MAX_LENGTH + 1));
        self::assertFalse($form->isSynchronized());
        $failure = $form->getTransformationFailure();
        self::assertNotNull($failure);
        self::assertSame(['taxonomy.tags.too_long', ['{{ max }}' => (string) Tag::MAX_LENGTH]], [$failure->getInvalidMessage(), $failure->getInvalidMessageParameters()]);

        $form = $forms->create(TagsType::class);
        $form->submit(str_repeat('é', Tag::MAX_LENGTH));
        self::assertTrue($form->isSynchronized(), 'a tag at the length limit is taken');

        try {
            $forms->create(TagsType::class, null, ['max_tags' => 0]);
            self::fail('A limit under one tag was accepted.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('"max_tags"', $invalidOptionsException->getMessage());
        }

        self::assertSame(1, $forms->create(TagsType::class, null, ['max_tags' => 1])->getConfig()->getOption('max_tags'));
        self::assertSame(Taxonomy::MAX_TAGS, $forms->create(TagsType::class, null, ['max_tags' => Taxonomy::MAX_TAGS])->getConfig()->getOption('max_tags'));

        try {
            $forms->create(TagsType::class, null, ['max_tags' => 'x']);
            self::fail('A limit that is not a number was accepted.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('"max_tags"', $invalidOptionsException->getMessage());
        }

        try {
            $forms->create(TagsType::class, null, ['max_tags' => Taxonomy::MAX_TAGS + 1]);
            self::fail('A limit above the one of setTags() was accepted.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('"max_tags"', $invalidOptionsException->getMessage());
        }

        $twenty = implode(',', array_map(static fn (int $i): string => 't'.$i, range(1, 20)));
        self::assertCount(20, new TagsTransformer()->reverseTransform($twenty), 'twenty tags by default');

        try {
            new TagsTransformer()->reverseTransform($twenty.',t21');
            self::fail('A twenty-first tag was taken by default.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame([0, 'taxonomy.tags.too_many'], [$transformationFailedException->getCode(), $transformationFailedException->getInvalidMessage()]);
        }

        $start = hrtime(true);
        $many = new TagsTransformer(\PHP_INT_MAX)->reverseTransform(implode(',', array_map(static fn (int $i): string => 'a'.$i, range(1, 50_000))));
        self::assertCount(50_000, $many);
        self::assertLessThan(2_000_000_000, hrtime(true) - $start, 'tags are told apart in linear time');
    }

    public function testACategoryIsPickedFromTheTree(): void
    {
        $taxonomy = new Taxonomy(new DoctrineTaxonomyRepository(Database::entityManager()));
        $games = $taxonomy->createCategory('games', null, ['en' => 'Games', 'fr' => 'Jeux']);
        $board = $taxonomy->createCategory('board', $games, ['fr' => 'Plateau']);
        $chess = $taxonomy->createCategory('chess', $board);
        $forms = $this->forms($taxonomy);

        $form = $forms->create(CategoryType::class, null, ['locale' => 'fr']);
        self::assertSame([['Jeux', $games->id], ['— Plateau', $board->id], ['— — chess', $chess->id]], $this->choices($form->createView()->vars['choices']));
        $form->submit($board->id);
        self::assertSame($board, $form->getData());

        $several = $forms->create(CategoryType::class, null, ['multiple' => true, 'locale' => 'en']);
        $several->submit([$games->id, $chess->id]);
        self::assertSame([$games, $chess], $several->getData());
        self::assertSame([ChoiceType::class, false], [new CategoryType($taxonomy)->getParent(), $several->getConfig()->getOption('choice_translation_domain')]);

        $default = Locale::getDefault();
        Locale::setDefault('fr_CA');

        try {
            self::assertSame('Jeux', $this->choices($forms->create(CategoryType::class)->createView()->vars['choices'])[0][0]);
        } finally {
            Locale::setDefault($default);
        }

        try {
            $forms->create(CategoryType::class, null, ['locale' => 12]);
            self::fail('A locale that is not a string was accepted.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('"locale"', $invalidOptionsException->getMessage());
        }

        $twins = $taxonomy->createCategory('other', $games, ['fr' => 'Autre']);
        $cousin = $taxonomy->createCategory('other-board', $board, ['fr' => 'Autre']);
        $sibling = $taxonomy->createCategory('miniatures', $games, ['fr' => 'Autre']);
        self::assertSame([['Jeux', $games->id], ['— Plateau', $board->id], ['— — chess', $chess->id], ['— — Autre', $cousin->id], ['— Autre', $sibling->id], ['— Autre', $twins->id]], $this->choices($forms->create(CategoryType::class, null, ['locale' => 'fr'])->createView()->vars['choices']));
        $label = $form->getConfig()->getOption('choice_label');
        self::assertIsCallable($label);
        self::assertSame('', $label('not a category'));

        $choiceValue = $form->getConfig()->getOption('choice_value');
        self::assertIsCallable($choiceValue);
        self::assertNull($choiceValue(null));
    }

    public function testTheTranslationsOfAPickAreReadOnce(): void
    {
        $repository = new CountingRepository(new DoctrineTaxonomyRepository(Database::entityManager()));
        $taxonomy = new Taxonomy($repository);
        $games = $taxonomy->createCategory('games', null, ['fr' => 'Jeux']);
        $board = $taxonomy->createCategory('board', $games, ['fr' => 'Plateau']);
        $forms = $this->forms($taxonomy);
        $repository->reads = [];

        $form = $forms->create(CategoryType::class, null, ['locale' => 'fr']);
        self::assertSame([['Jeux', $games->id], ['— Plateau', $board->id]], $this->choices($form->createView()->vars['choices']));
        self::assertSame(['categories' => 1, 'translationsOf' => 1], $repository->reads, 'the tree names its choices');

        $label = $form->getConfig()->getOption('choice_label');
        self::assertIsCallable($label);
        $other = $taxonomy->createCategory('other', $board, ['fr' => 'Autre']);
        self::assertSame('— — other', $label($other), 'a category the tree did not hold: its key');

        $repository->reads = [];
        $given = $forms->create(CategoryType::class, null, ['locale' => 'fr', 'choices' => ['Un groupe' => [$board], 'seul' => $other]]);
        self::assertSame(['translationsOf' => 1], $repository->reads, 'choices given are named at once');
        $label = $given->getConfig()->getOption('choice_label');
        self::assertIsCallable($label);
        self::assertSame(['— Plateau', '— — Autre', ''], [$label($board), $label($other), $label(null)]);
        self::assertSame('games', $label($games), 'a category the choices did not hold: its key');
    }

    /**
     * @return list<array{mixed, string}>
     */
    private function choices(mixed $choices): array
    {
        self::assertIsArray($choices);

        return array_values(array_map(static function (mixed $choice): array {
            self::assertInstanceOf(ChoiceView::class, $choice);

            return [$choice->label, $choice->value];
        }, $choices));
    }

    private function forms(Taxonomy $taxonomy): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new CategoryType($taxonomy))->getFormFactory();
    }
}
