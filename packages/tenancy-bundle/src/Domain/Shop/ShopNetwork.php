<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Shop;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The shops of one application, each a tenant, and the catalogues each one sells: its own, and those of the shops
 * it includes. A general shop that includes two sub-sites shows their catalogues beside its own and takes payment
 * for all of them at its single checkout; each sub-site keeps its own shop, catalogue and cart.
 *
 * Inclusion is one level deep: a shop that includes others is not itself included, and an included shop includes
 * nothing. A shop never includes itself. The rules keep every lookup bounded by the number of shops.
 *
 * Shops linked by an inclusion share their customer accounts: see {@see self::sharesAccounts()}.
 */
final readonly class ShopNetwork
{
    /**
     * @param array<string, list<TenantId>> $includes the included shops, by the reference of the shop that includes them
     * @param array<string, TenantId>       $shops    the shops that include others, by their reference
     */
    private function __construct(
        private array $includes,
        private array $shops,
    ) {
    }

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * @param array<array-key, list<string>> $includes the references of the included shops, by the reference of each shop
     */
    public static function fromReferences(array $includes): self
    {
        $network = [];
        $shops = [];

        foreach ($includes as $shop => $included) {
            $shop = TenantId::fromString((string) $shop);
            $shops[$shop->value] = $shop;
            $seen = [];

            foreach ($included as $reference) {
                $catalogue = TenantId::fromString($reference);

                if ($catalogue->equals($shop)) {
                    throw InvalidValue::because(\sprintf('The shop "%s" cannot include itself.', $shop->value));
                }

                if (isset($seen[$catalogue->value])) {
                    throw InvalidValue::because(\sprintf('The shop "%s" includes "%s" twice.', $shop->value, $catalogue->value));
                }

                $seen[$catalogue->value] = $catalogue;
            }

            $network[$shop->value] = array_values($seen);
        }

        foreach ($shops as $shop) {
            foreach ($network[$shop->value] as $catalogue) {
                if ([] !== ($network[$catalogue->value] ?? [])) {
                    throw InvalidValue::because(\sprintf('The shop "%s" is included by "%s" and includes shops of its own: inclusion is one level deep.', $catalogue->value, $shop->value));
                }
            }
        }

        return new self($network, $shops);
    }

    /**
     * The catalogues a shop sells, its own first, then those it includes in the order they were declared.
     *
     * @return non-empty-list<TenantId>
     */
    public function catalogues(TenantId $shop): array
    {
        return [$shop, ...$this->includes[$shop->value] ?? []];
    }

    /**
     * Whether a product of this catalogue may be put in this shop's cart and paid at its checkout.
     */
    public function sells(TenantId $shop, TenantId $catalogue): bool
    {
        return array_any($this->catalogues($shop), static fn (TenantId $sold): bool => $sold->equals($catalogue));
    }

    /**
     * The shops that include this one, in the order they were declared.
     *
     * @return list<TenantId>
     */
    public function includedBy(TenantId $catalogue): array
    {
        $shops = [];

        foreach ($this->shops as $shop) {
            if (array_any($this->includes[$shop->value], static fn (TenantId $one): bool => $one->equals($catalogue))) {
                $shops[] = $shop;
            }
        }

        return $shops;
    }

    /**
     * Whether a customer account signed in on one shop is signed in on the other: the same shop, or a shop and one
     * it includes, or two shops included by the same one.
     */
    public function sharesAccounts(TenantId $one, TenantId $other): bool
    {
        if ($one->equals($other) || $this->sells($one, $other) || $this->sells($other, $one)) {
            return true;
        }

        $including = $this->includedBy($other);

        return array_any($this->includedBy($one), static fn (TenantId $shop): bool => array_any($including, static fn (TenantId $same): bool => $same->equals($shop)));
    }
}
