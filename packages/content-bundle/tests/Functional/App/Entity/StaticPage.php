<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A root whose identifier the database generates: resource key `static_page`. The identifier is protected rather
 * than private, as Doctrine writes it after the insert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_static_page')]
class StaticPage extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    protected ?int $id = null;

    #[Override]
    public static function dimensionContentClass(): string
    {
        return StaticPageDimension::class;
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
