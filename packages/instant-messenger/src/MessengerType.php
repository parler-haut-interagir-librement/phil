<?php

declare(strict_types=1);

namespace Phil\InstantMessenger;

use UnitEnum;

/**
 * A messaging service, and how a handle on it is written: a Telegram user name, a WhatsApp phone number, a Matrix ID.
 * An enum implements it, each case a service; `KnownMessenger` holds the international ones, and an application
 * declares its own the same way.
 *
 * A handle is read from what a person types — `@ada`, `t.me/ada`, `+1 514 555-0123` — and kept in one canonical form.
 */
interface MessengerType extends UnitEnum
{
    /**
     * The canonical handle the input stands for on this service, or `null` when it is not one.
     */
    public function normalize(string $input): ?string;

    /**
     * The handle as people read it: `@ada` on Telegram.
     */
    public function display(string $handle): string;

    /**
     * The address that opens a conversation with the handle — `https://t.me/ada`, `xmpp:ada@example.org` —, or `null`
     * when the service has none.
     */
    public function link(string $handle): ?string;
}
