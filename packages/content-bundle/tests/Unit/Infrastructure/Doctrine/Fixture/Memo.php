<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Symfony\Component\Uid\Uuid;

/**
 * A root identified by a `Uuid` of `symfony/uid`, whose `fromString()` refuses a malformed string with an
 * `InvalidArgumentException`.
 *
 * The `uuid` type is the one of Symfony's Doctrine bridge, which the tests do not register: the metadata is
 * read, never written.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_memo')]
class Memo extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'uuid')]
    private Uuid $id;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return MemoDimension::class;
    }
}
