<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Contract;

use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;

/**
 * Which units of a SKU leave first, between the shop's own and the consigned ones. `Application\ConfiguredIssueOrder`
 * reads the bundle's configuration; an application with a rule of its own — by product family, by consignor's
 * contract — aliases this interface to its service.
 */
interface IssueOrderPolicy
{
    public function issueOrder(string $sku): IssueOrder;
}
