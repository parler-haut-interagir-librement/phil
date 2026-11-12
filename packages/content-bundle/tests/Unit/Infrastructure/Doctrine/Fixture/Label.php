<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A root identified by a code whose `fromString()` refuses a malformed string with a `DomainError` that is
 * not an `InvalidArgumentException`. The metadata is read, never written.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_label')]
class Label extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING)]
    private LabelCode $id;

    public function __construct()
    {
        $this->id = LabelCode::fromString('LBL');
    }

    public function id(): LabelCode
    {
        return $this->id;
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return LabelDimension::class;
    }
}
