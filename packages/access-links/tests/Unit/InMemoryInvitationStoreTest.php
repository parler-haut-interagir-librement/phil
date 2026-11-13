<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use Override;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\InMemoryInvitationStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryInvitationStore::class)]
final class InMemoryInvitationStoreTest extends InvitationStoreTestCase
{
    #[Override]
    protected function store(): InvitationStore
    {
        return new InMemoryInvitationStore();
    }
}
