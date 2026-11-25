# Changelog

All notable changes to `phil/commerce-promotions-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `phil/doctrine-behaviours` 0.3,
  `symfony/translation-contracts` 3.8), and of each development requirement (`doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Changed

- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.

## 0.1.0-alpha - 2026-11-20

### Added

- `Promotion`, `PromotionAction` and `PromotionCode`: a share off, an amount off, N for X, a price for each complete
  bundle of `BundleGroup`s, free shipping; conditions of products and excluded products, channels, customer groups,
  delivery methods, products owned, code, period, uses in all and per customer, minimum, `after` and
  `incompatibleWith`; `claim()`, which counts only under the limit, and `Promotion::release()`; `relabel()`. The period,
  `starts_at` and `ends_at`, has the DBAL type `datetime_utc_immutable`: given in any time zone, written and read back
  in UTC; the SQL is that of a `DATETIME`, so a table created before needs no migration, but periods written in
  another time zone than UTC are converted once to UTC.
- `PromotionTranslation`, the label in one locale, on the translations of `phil/doctrine-behaviours`;
  `PromotionTexts` and `PromotionTextSet`, which read a list of labels in one query along a fallback chain, and the
  configuration `phil_commerce_promotions.translations.locales`.
- `PromotionRedemption`, with the customer, and `PromotionCustomerUse`; `PromotionRefused`.
- `PromotionOrder`, which sorts promotions by code and then by their `after` lists with Symfony's
  `BeforeAfterSorter`, and sets a loop aside.
- `PromotionEvaluator`, which reads the order's lines, channel, customer group, customer, delivery method and currency
  once per evaluation, matches promotion codes whatever the case the buyer typed, each looked up once, keeps each
  bundle's discount on each product's own line, and labels the discounts in the buyer's locale;
  `PromotionEvaluator::originOf()` and the origin of each discount, `promotion:<code>[:<promotion code>]`.
- `CustomerOwnership`, the port an application implements for the promotions of the owners of a product.
- `PromotionProcessor`, the core's `OrderProcessor`, before its `TaxOrderProcessor`, its `ManualDiscountProcessor`
  and the logistics module's `ShippingProcessor`; `FreeShippingProcessor`, after that one, which waives the delivery
  charge under the promotion's label and origin.
- `Promotions`, for batches of codes and counting uses: `redeem()` counts what the placed order got, from the origins
  of its discounts, once per order, a retried call included, even for a promotion withdrawn since placement, for the
  customer too, and returns the redemptions it added.
- `PromotionRepository`, with `claimUse()`, `customerUses()`, `claimCustomerUse()`, `findRedemption()` and
  `addRedemption()`, and its Doctrine adapter. Uses of promotions, codes and customers are claimed with an atomic
  `UPDATE` against their limits, inside the placing transaction.
- `PhilCommercePromotionsBundle`, extension alias `phil_commerce_promotions`, which refuses to load without
  `PhilCommerceBundle` and `PhilCommerceCatalogBundle`.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
