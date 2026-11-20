<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Override;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceCatalogBundle\Application\Contract\ProductListing;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Application\ProductPage;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;
use SortDirection;

/**
 * `ProductListing` on DBAL, without entities, so that a list of any length is read in the memory of a page: the rows of
 * `phil_commerce_catalog_product`, each joined to its family's, read by SKU from the cursor on, then the names of the
 * page in the locale asked, in one query.
 *
 * Active, listed products are read in batches of the page's size; a channel's selection, kept as JSON, is
 * then checked on each row. A page reads ten batches at most: a channel that sells few of many products may get a short
 * page, whose cursor still reads on.
 */
final readonly class DbalProductListing implements ProductListing
{
    public const string TABLE = 'phil_commerce_catalog_product';

    public const string TRANSLATIONS = 'phil_commerce_catalog_product_translation';

    /** The batches a page reads, at most. */
    public const int MAX_BATCHES = 10;

    public function __construct(
        private Connection $connection,
        private ?DeclaredLocales $locales = null,
    ) {
    }

    #[Override]
    public function page(?string $channel, ?string $after = null, int $limit = 50, ?string $locale = null, bool $families = true, SortDirection $direction = SortDirection::Ascending): ProductPage
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException(\sprintf('A page lists 1 to %d products, not %d.', self::MAX_LIMIT, $limit));
        }

        $products = [];

        for ($batch = 0; $batch < self::MAX_BATCHES && \count($products) < $limit; ++$batch) {
            $rows = $this->rows($after, $limit, $direction);

            foreach ($rows as $row) {
                $after = $this->text($row['sku']);
                $product = $this->listed($row, $channel, $families);

                if ($product instanceof ListedProduct && \count($products) < $limit) {
                    $products[] = $product;
                } elseif ($product instanceof ListedProduct) {
                    // A product past the page's end: the next page starts with it.
                    return new ProductPage($this->named($products, $locale), $products[$limit - 1]->sku);
                }
            }

            if (\count($rows) < $limit) {
                return new ProductPage($this->named($products, $locale), null);
            }
        }

        return new ProductPage($this->named($products, $locale), $after);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(?string $after, int $limit, SortDirection $direction): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('p.sku', 'p.kind', 'p.name', 'p.price', 'p.currency', 'p.tax_category', 'p.attributes', 'p.orderable', 'p.price_shown', 'p.channels', 'p.family', 'p.options', 'p.axes')
            ->addSelect('f.active AS family_active', 'f.visibility AS family_visibility', 'f.orderable AS family_orderable', 'f.price_shown AS family_price_shown', 'f.channels AS family_channels')
            ->from(self::TABLE, 'p')
            ->leftJoin('p', self::TABLE, 'f', 'f.sku = p.family')
            ->where('p.active = :active')
            ->andWhere('p.visibility = :listed')
            ->orderBy('p.sku', SortDirection::Ascending === $direction ? 'ASC' : 'DESC')
            ->setMaxResults($limit)
            ->setParameter('active', true, ParameterType::BOOLEAN)
            ->setParameter('listed', Visibility::Listed->value);

        if (null !== $after) {
            $query->andWhere(SortDirection::Ascending === $direction ? 'p.sku > :after' : 'p.sku < :after')->setParameter('after', $after);
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * The product of a row, unless the channel does not list it, or it is a family and families are left out.
     *
     * @param array<string, mixed> $row
     */
    private function listed(array $row, ?string $channel, bool $families): ?ListedProduct
    {
        $axes = $this->list($row['axes']);

        if ((!$families && [] !== $axes) || !$this->sells($this->list($row['channels']), $channel)) {
            return null;
        }

        $offer = Offer::of(Visibility::Listed, $this->flag($row['orderable']) && $this->flag($row['price_shown']), $this->flag($row['price_shown']));

        if (null !== $row['family']) {
            // A variant is listed while its family is: active, sold through the channel, listed itself.
            if (!$this->flag($row['family_active']) || Visibility::Listed->value !== $row['family_visibility'] || !$this->sells($this->list($row['family_channels']), $channel)) {
                return null;
            }

            $offer = $offer->stricter(Offer::of(Visibility::Listed, $this->flag($row['family_orderable']) && $this->flag($row['family_price_shown']), $this->flag($row['family_price_shown'])));
        }

        return new ListedProduct(
            $this->text($row['sku']),
            ProductKind::from($this->text($row['kind'])),
            $this->text($row['name']),
            Money::of($this->integer($row['price']), Currency::of($this->text($row['currency']))),
            $this->text($row['tax_category']),
            $offer,
            $this->sorted($this->map($row['attributes'])),
            null === $row['family'] ? null : $this->text($row['family']),
            $this->map($row['options']),
            $axes,
        );
    }

    /**
     * @param list<string> $channels the selection; every channel when empty
     */
    private function sells(array $channels, ?string $channel): bool
    {
        return [] === $channels || \in_array($channel, $channels, true);
    }

    /**
     * @param list<ListedProduct> $products
     *
     * @return list<ListedProduct>
     */
    private function named(array $products, ?string $locale): array
    {
        $chain = $this->chain($locale);

        if ([] === $chain || [] === $products) {
            return $products;
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('t.product', 't.locale', 't.name')
            ->from(self::TRANSLATIONS, 't')
            ->where('t.product IN (:skus)')
            ->andWhere('t.locale IN (:locales)')
            ->setParameter('skus', array_map(static fn (ListedProduct $product): string => $product->sku, $products), ArrayParameterType::STRING)
            ->setParameter('locales', $chain, ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();
        $names = [];

        foreach ($rows as $row) {
            if ('' !== $row['name']) {
                $names[$this->text($row['product'])][$this->text($row['locale'])] = $this->text($row['name']);
            }
        }

        return array_map(static function (ListedProduct $product) use ($names, $chain): ListedProduct {
            foreach ($chain as $locale) {
                if (isset($names[$product->sku][$locale])) {
                    return $product->named($names[$product->sku][$locale]);
                }
            }

            return $product;
        }, $products);
    }

    /**
     * The locales of a served locale's chain, in order; none without declared locales or for a locale not served.
     *
     * @return list<string>
     */
    private function chain(?string $locale): array
    {
        if (null === $locale || !$this->locales instanceof DeclaredLocales) {
            return [];
        }

        try {
            $locale = Locale::fromString($locale);
        } catch (InvalidLocaleDimension) {
            return [];
        }

        return $this->locales->isServed($locale) ? array_map(static fn (Locale $each): string => $each->value, $this->locales->chainFor($locale)->locales) : [];
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * A flag column: 0 and 1 on MySQL and SQLite, a boolean on PostgreSQL.
     */
    private function flag(mixed $value): bool
    {
        return (bool) $value;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The attributes by name, as the product keeps them: MySQL gives the keys of a JSON object in an order of its own.
     *
     * @param array<string, string> $map
     *
     * @return array<string, string>
     */
    private function sorted(array $map): array
    {
        ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @return list<string>
     */
    private function list(mixed $json): array
    {
        $decoded = \is_string($json) ? json_decode($json, true, 4, \JSON_THROW_ON_ERROR) : null;

        return \is_array($decoded) ? array_values(array_map(strval(...), array_filter($decoded, \is_scalar(...)))) : [];
    }

    /**
     * @return array<string, string>
     */
    private function map(mixed $json): array
    {
        $decoded = \is_string($json) ? json_decode($json, true, 4, \JSON_THROW_ON_ERROR) : null;
        $map = [];

        foreach (\is_array($decoded) ? $decoded : [] as $key => $value) {
            if (\is_scalar($value)) {
                $map[(string) $key] = (string) $value;
            }
        }

        return $map;
    }
}
