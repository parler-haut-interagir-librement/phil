<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Inherited;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'memo')]
#[TenantScoped(field: 'tenantRef')]
final class Memo extends Stamped
{
}
