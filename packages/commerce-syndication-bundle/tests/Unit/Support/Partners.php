<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\StandardWebhooks\Ed25519SecretKey;

/**
 * Partners and their keys, for the tests: one key pair per role, fixed so that signatures are reproducible.
 */
final class Partners
{
    public static function supplierKey(): Ed25519SecretKey
    {
        return Ed25519SecretKey::fromBytes(str_repeat("\x01", 32));
    }

    public static function resellerKey(): Ed25519SecretKey
    {
        return Ed25519SecretKey::fromBytes(str_repeat("\x02", 32));
    }

    public static function ourKey(): Ed25519SecretKey
    {
        return Ed25519SecretKey::fromBytes(str_repeat("\x03", 32));
    }

    public static function supplier(string $code = 'supplier'): Partner
    {
        return Partner::of($code, 'The supplier', PartnerRole::Supplier, 'https://supplier.example/syndication', self::supplierKey()->publicKey()->toString());
    }

    /**
     * @param list<string> $skus
     */
    public static function reseller(string $code = 'reseller', array $skus = ['TEA-1'], int $share = 300_000): Partner
    {
        $reseller = Partner::of($code, 'The reseller', PartnerRole::Reseller, 'https://reseller.example/syndication', self::resellerKey()->publicKey()->toString());
        $reseller->feed($skus, $share);

        return $reseller;
    }
}
