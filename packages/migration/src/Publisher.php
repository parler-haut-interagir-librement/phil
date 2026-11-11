<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * Sends one message of an announcement on a chat transport.
 */
interface Publisher
{
    public function publish(string $transport, string $message): void;
}
