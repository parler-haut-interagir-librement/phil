<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationMemory\Memory\InMemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemorySegmentRepository::class)]
#[CoversClass(InMemoryCommentRepository::class)]
#[CoversClass(InMemoryTermRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function stores(): array
    {
        return [new InMemorySegmentRepository(), new InMemoryTermRepository(), new InMemoryStore(), new InMemoryCommentRepository()];
    }
}
