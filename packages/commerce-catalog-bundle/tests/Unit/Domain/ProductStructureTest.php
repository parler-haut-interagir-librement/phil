<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Domain;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Model\ProductTranslation;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Families and their variants, composite products and their parts, and the translations of a product's name.
 */
#[CoversClass(Product::class)]
#[CoversClass(ProductComponent::class)]
#[CoversClass(ProductTranslation::class)]
#[CoversClass(CatalogRefused::class)]
final class ProductStructureTest extends TestCase
{
    public function testAFamilyNamesItsAxes(): void
    {
        $shirt = $this->shirt();

        self::assertSame(['size', 'colour'], $shirt->axes());
        self::assertTrue($shirt->isFamily());
        self::assertNull($shirt->family());
        self::assertSame([], $shirt->options());

        $shirt->defineAxes([]);
        self::assertFalse($shirt->isFamily());
        self::assertFalse($this->product('PLAIN')->isFamily());
    }

    public function testAVariantTakesItsFamilysTraits(): void
    {
        $shirt = $this->shirt();
        $shirt->sellThrough(['web']);
        $shirt->changeOffer(Offer::of(Visibility::Unlisted, false, false));

        $variant = Product::variant($shirt, 'SHIRT-M-RED', ['colour' => 'red', 'size' => 'M']);
        self::assertEquals(Offer::of(Visibility::Unlisted, false, false), $variant->offer());

        self::assertSame('SHIRT-M-RED', $variant->sku);
        self::assertSame('SHIRT', $variant->family());
        self::assertSame(['size' => 'M', 'colour' => 'red'], $variant->options());
        self::assertSame('T-shirt — M / red', $variant->name());
        self::assertSame(2_500, $variant->price->minorUnits);
        self::assertSame('goods', $variant->taxCategory());
        self::assertSame(ProductKind::Physical, $variant->kind);
        self::assertSame(['web'], $variant->channels());
        self::assertFalse($variant->isFamily());
        self::assertSame([], $variant->axes());

        $priced = Product::variant($shirt, 'SHIRT-XL-RED', ['size' => 'XL', 'colour' => 'red'], Money::of(2_900, Currency::of('CAD')), 'Big red T-shirt');
        self::assertSame('Big red T-shirt', $priced->name());
        self::assertSame(2_900, $priced->price->minorUnits);
        self::assertSame(str_repeat('v', 64), Product::variant($shirt, 'S1', ['size' => str_repeat('v', 64), 'colour' => 'red'])->options()['size']);
    }

    /**
     * A value is counted in characters, not in bytes: 40 accented letters are 80 bytes.
     */
    public function testAValueIsBoundedInCharacters(): void
    {
        $accented = str_repeat('é', 64);

        self::assertSame($accented, Product::variant($this->shirt(), 'S1', ['size' => $accented, 'colour' => 'red'])->options()['size']);

        $this->expectException(InvalidValue::class);

        Product::variant($this->shirt(), 'S2', ['size' => $accented.'é', 'colour' => 'red']);
    }

    public function testTheAxesAreAListWithoutDuplicates(): void
    {
        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size', 'size', 'colour']);

        self::assertSame(['size', 'colour'], $shirt->axes());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'an axis left out' => [['size' => 'M'], 'A variant of SHIRT gives its axis colour a value of 1 to 64 characters, not blank.'];
        yield 'a blank value' => [['size' => ' ', 'colour' => 'red'], 'A variant of SHIRT gives its axis size a value of 1 to 64 characters, not blank.'];
        yield 'a value too long' => [['size' => str_repeat('v', 65), 'colour' => 'red'], 'gives its axis size a value'];
        yield 'another option' => [['size' => 'M', 'colour' => 'red', 'fit' => 'slim'], 'A variant of SHIRT gives values to its axes size, colour only.'];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidOptions')]
    public function testAVariantGivesEachAxisOneValue(array $options, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Product::variant($this->shirt(), 'SHIRT-X', $options);
    }

    public function testAProductWithoutAxesHasNoVariants(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product PLAIN names no axes: it has no variants.');

        Product::variant($this->product('PLAIN'), 'PLAIN-1', []);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidAxes(): iterable
    {
        yield 'six axes' => [['a', 'b', 'c', 'd', 'e', 'f'], 'A family has up to 5 axes.'];
        yield 'uppercase' => [['Size'], '"Size" is not an axis name: up to 64 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a digit first' => [['1size'], 'is not an axis name'];
        yield 'too long' => [[str_repeat('a', 65)], 'is not an axis name'];
        yield 'a trailing newline' => [["size\n"], 'is not an axis name'];
    }

    /**
     * @param list<string> $axes
     */
    #[DataProvider('invalidAxes')]
    public function testAnInvalidAxisIsRefused(array $axes, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->product('PLAIN')->defineAxes($axes);
    }

    public function testTheAxesTakeTheirBounds(): void
    {
        $product = $this->product('PLAIN');
        $product->defineAxes(['a', 'b', 'c', 'e', 'e', str_repeat('z', 64)]);

        self::assertCount(5, $product->axes());
    }

    public function testAVariantOrACompositeNamesNoAxes(): void
    {
        $variant = Product::variant($this->shirt(), 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red']);

        try {
            $variant->defineAxes(['fit']);
            self::fail('A variant named axes.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The product SHIRT-M-RED is a variant or a composite: it names no axes.', $invalidValue->getMessage());
        }

        $box = $this->product('BOX');
        $box->composeOf([ProductComponent::of('DICE')]);
        $this->expectException(InvalidValue::class);

        $box->defineAxes(['fit']);
    }

    public function testACompositeHoldsItsParts(): void
    {
        $box = $this->product('BOX');
        $box->composeOf([ProductComponent::of('BOOK'), ProductComponent::of('DICE', 2)]);

        self::assertTrue($box->isComposite());
        self::assertSame([['BOOK', 1], ['DICE', 2]], array_map(static fn (ProductComponent $part): array => [$part->sku, $part->quantity], $box->components()));

        $box->composeOf([]);
        self::assertFalse($box->isComposite());
        self::assertSame([], $box->components());

        $big = $this->product('BIG');
        $big->composeOf(array_map(static fn (int $n): ProductComponent => ProductComponent::of('P'.$n), range(1, 50)));
        self::assertCount(50, $big->components());
    }

    /**
     * @return iterable<string, array{list<ProductComponent>, string}>
     */
    public static function invalidParts(): iterable
    {
        yield 'itself' => [[ProductComponent::of('BOX')], 'The product BOX names BOX once at most, and never itself.'];
        yield 'twice' => [[ProductComponent::of('DICE'), ProductComponent::of('DICE', 2)], 'The product BOX names DICE once at most, and never itself.'];
        yield 'fifty-one parts' => [array_map(static fn (int $n): ProductComponent => ProductComponent::of('P'.$n), range(1, 51)), 'A composite product has up to 50 parts.'];
    }

    /**
     * @param list<ProductComponent> $parts
     */
    #[DataProvider('invalidParts')]
    public function testInvalidPartsAreRefused(array $parts, string $message): void
    {
        $box = $this->product('BOX');
        $box->composeOf([ProductComponent::of('BOOK')]);

        try {
            $box->composeOf($parts);
            self::fail('The parts were taken.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame($message, $invalidValue->getMessage());
        }

        self::assertSame(['BOOK'], array_map(static fn (ProductComponent $part): string => $part->sku, $box->components()));
    }

    public function testAFamilyIsMadeOfNoParts(): void
    {
        $shirt = $this->shirt();
        $shirt->composeOf([]);
        self::assertFalse($shirt->isComposite());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product SHIRT is a family: it is chosen through its variants, not made of parts.');

        $shirt->composeOf([ProductComponent::of('DICE')]);
    }

    public function testAPartTakesItsBounds(): void
    {
        $part = ProductComponent::of(str_repeat('A', 64), 1_000);

        self::assertSame(['sku' => str_repeat('A', 64), 'quantity' => 1_000], $part->toArray());
        self::assertSame(1, ProductComponent::of('A')->quantity);
        self::assertSame(['B', 3], [ProductComponent::fromArray(['sku' => 'B', 'quantity' => 3])->sku, ProductComponent::fromArray(['sku' => 'B', 'quantity' => 3])->quantity]);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidComponents(): iterable
    {
        yield 'a SKU with a space' => ['A B', 1, '"A B" is not a SKU: up to 64 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'a SKU too long' => [str_repeat('A', 65), 1, 'is not a SKU'];
        yield 'a SKU with a newline' => ["A\n", 1, 'is not a SKU'];
        yield 'no unit' => ['A', 0, 'A part of a composite product holds 1 to 1000 units; 0 is not.'];
        yield 'too many units' => ['A', 1_001, 'A part of a composite product holds 1 to 1000 units; 1001 is not.'];
    }

    #[DataProvider('invalidComponents')]
    public function testAnInvalidPartIsRefused(string $sku, int $quantity, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ProductComponent::of($sku, $quantity);
    }

    public function testANameIsTranslated(): void
    {
        $product = $this->product('DICE');

        self::assertSame(ProductTranslation::class, Product::translationClass());
        self::assertSame(['name'], Product::translatedFields());

        $translation = $product->newTranslation(Locale::fromString('fr'));
        self::assertSame($product, $translation->translatable());
        self::assertSame('fr', $translation->locale()->value);
        self::assertSame('', $translation->name());

        $translation->rename('Jeu de dés');
        self::assertSame('Jeu de dés', $translation->name());
        $translation->rename(str_repeat('é', 255));
        self::assertSame(255, mb_strlen($translation->name()));

        foreach ([' ', str_repeat('é', 256)] as $refused) {
            try {
                $translation->rename($refused);
                self::fail('The name was taken.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('The product DICE has a name of 1 to 255 characters in fr.', $invalidValue->getMessage());
            }
        }
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame('The SKU A already names a product.', CatalogRefused::taken('A')->getMessage());
        self::assertSame('A variant of SHIRT already has the values M / red.', CatalogRefused::sameOptions('SHIRT', ['size' => 'M', 'colour' => 'red'])->getMessage());
        self::assertSame('The composite product BOX names X, which the catalogue does not hold.', CatalogRefused::unknownPart('BOX', 'X')->getMessage());
        self::assertSame('The composite product BOX names X, a family or a composite: its parts are products of their own.', CatalogRefused::nestedPart('BOX', 'X')->getMessage());
        self::assertSame('No category has the key games.', CatalogRefused::unknownCategory('games')->getMessage());
    }

    private function shirt(): Product
    {
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $shirt->defineAxes(['size', 'colour', 'size']);

        return $shirt;
    }

    private function product(string $sku): Product
    {
        return Product::create($sku, ProductKind::Physical, 'Product', Money::of(1_000, Currency::of('CAD')), 'goods');
    }
}
