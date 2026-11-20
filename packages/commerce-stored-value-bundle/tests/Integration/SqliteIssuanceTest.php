<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Integration;

use Override;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The same, on a SQLite file every process opens: no server needed.
 */
#[CoversClass(GiftCards::class)]
#[CoversClass(StoreCredits::class)]
#[CoversClass(DoctrineStoredValueRepository::class)]
final class SqliteIssuanceTest extends ServerTestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        parent::tearDown();
        if (null !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Override]
    protected function url(): string
    {
        $this->file ??= sys_get_temp_dir().'/phil-commerce-stored-value-'.bin2hex(random_bytes(6)).'.sqlite';

        return 'sqlite:'.$this->file;
    }
}
