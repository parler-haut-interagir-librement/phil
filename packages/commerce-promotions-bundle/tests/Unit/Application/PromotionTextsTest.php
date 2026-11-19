<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use LogicException;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Application\PromotionTexts;
use Phil\CommercePromotionsBundle\Application\PromotionTextSet;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Infrastructure\Doctrine\DoctrinePromotionRepository;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\StoredDatabase;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The labels of promotions read from stored translations, and the receipts' labels in the buyer's locale.
 */
#[CoversClass(PromotionTexts::class)]
#[CoversClass(PromotionTextSet::class)]
#[CoversClass(PromotionEvaluator::class)]
final class PromotionTextsTest extends TestCase
{
    private EntityManager $manager;

    private LocaleAwareInterface $current;

    private PromotionTexts $texts;

    private Promotion $sale;

    private Promotion $other;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = StoredDatabase::entityManager();
        $this->current = new class implements LocaleAwareInterface {
            public string $locale = 'fr_CA';

            #[Override]
            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            #[Override]
            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $this->texts = new PromotionTexts(new Translations($this->manager), DeclaredLocales::fromArray(['en' => [], 'fr' => ['en'], 'fr_CA' => ['fr']]), $this->current);

        $this->sale = Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD'));
        $this->other = Promotion::percentOff('other', 'Other', 100_000, Currency::of('CAD'))->when(skus: ['NONE']);
        $this->manager->persist($this->sale);
        $this->manager->persist($this->other);
        $this->texts->translate($this->sale, 'fr')->relabel('Solde');
        $this->texts->translate($this->other, 'fr');
        $this->manager->flush();
    }

    public function testALabelIsReadAlongTheChain(): void
    {
        $set = $this->texts->of([$this->sale, $this->other]);

        self::assertSame('Solde', $set->label($this->sale));
        self::assertTrue($set->isTranslated($this->sale));
        self::assertSame('Other', $set->label($this->other));
        self::assertFalse($set->isTranslated($this->other));

        self::assertSame('Sale', $this->texts->of([$this->sale], 'en')->label($this->sale));
        self::assertFalse($this->texts->of([$this->sale], 'en')->isTranslated($this->sale));
        self::assertSame('Sale', $this->texts->of([$this->sale], 'de')->label($this->sale));
        self::assertSame('Sale', $this->texts->of([$this->sale], 'not a locale!')->label($this->sale));
        self::assertSame('Sale', $this->texts->of([])->label($this->sale));

        $this->current->setLocale('fr');
        self::assertSame('Solde', $this->texts->of([$this->sale])->label($this->sale));
        self::assertSame(['fr'], $this->texts->locales($this->sale));
    }

    public function testATranslationIsWrittenInItsOwnLocale(): void
    {
        $english = $this->texts->translate($this->sale, 'en');
        self::assertSame('', $english->label());
        self::assertSame('en', $english->locale()->value);
        self::assertSame($english, $this->texts->translate($this->sale, 'en'));
        self::assertSame('Solde', $this->texts->translate($this->sale, 'fr')->label());
    }

    public function testWithoutTranslationsEveryLabelIsItsOwn(): void
    {
        $texts = new PromotionTexts();

        self::assertSame('Sale', $texts->of([$this->sale], 'fr')->label($this->sale));
        self::assertSame([], $texts->locales($this->sale));
        self::assertSame('Sale', new PromotionTexts(new Translations($this->manager))->of([$this->sale], 'fr')->label($this->sale));
        self::assertSame('Sale', new PromotionTexts(locales: DeclaredLocales::fromArray(['fr' => []]))->of([$this->sale], 'fr')->label($this->sale));
        self::assertSame('Sale', new PromotionTexts(new Translations($this->manager), DeclaredLocales::fromArray(['fr' => []]))->of([$this->sale])->label($this->sale));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The promotions are translated with phil/doctrine-behaviours and phil_commerce_promotions.translations.locales: declare the locales served.');

        $texts->translate($this->sale, 'fr');
    }

    /**
     * The discount carries the label in the buyer's locale, read once for every promotion of the evaluation.
     */
    public function testTheDiscountIsLabelledInTheBuyersLocale(): void
    {
        $order = Order::open('1', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'PDF'), 'PDF', $price, 'goods'), 1, $price);
        $evaluator = new PromotionEvaluator(new DoctrinePromotionRepository($this->manager), $this->texts);

        $applied = $evaluator->evaluate($order, new DateTimeImmutable());
        self::assertSame('Solde', $applied[0]['adjustments'][0]->label);

        $this->current->setLocale('en');
        self::assertSame('Sale', $evaluator->evaluate($order, new DateTimeImmutable())[0]['adjustments'][0]->label);
    }
}
