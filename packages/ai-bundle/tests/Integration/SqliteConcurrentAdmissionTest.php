<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Override;
use Phil\AiBundle\Application\Quota;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The concurrent admissions on SQLite, in a file both processes open.
 */
#[CoversClass(Quota::class)]
final class SqliteConcurrentAdmissionTest extends ConcurrentAdmissionTestCase
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
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The extension pdo_sqlite is not loaded.');
        }

        $file = tempnam(sys_get_temp_dir(), 'phil-ai-quota-');

        if (false === $file) {
            self::fail('No temporary file.');
        }

        $this->file = $file;

        return 'sqlite:///'.$file;
    }
}
