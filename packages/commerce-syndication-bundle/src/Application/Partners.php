<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;

/**
 * The partners this application trades with: the suppliers it resells, the resellers it feeds.
 */
final readonly class Partners
{
    public function __construct(
        private PartnerRepository $partners,
    ) {
    }

    public function register(string $code, string $name, PartnerRole $role, string $endpoint, string $publicKey): Partner
    {
        $partner = Partner::of($code, $name, $role, $endpoint, $publicKey);
        $this->partners->addPartner($partner);

        return $partner;
    }

    /**
     * The SKUs a reseller is fed, and the share of their retail price it keeps, in parts per million.
     *
     * @param list<string> $skus
     */
    public function feed(string $reseller, array $skus, int $share): void
    {
        $this->get($reseller)->feed($skus, $share);
    }

    public function get(string $code): Partner
    {
        return $this->partners->findPartner($code) ?? throw UnknownPartner::code($code);
    }
}
