<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Register;

/**
 * What a register is to the shop.
 *
 * A mobile register is a seller's till on their phone: it takes cash and cards that need no PIN, and waits for the
 * network. The main register is the tablet at the counter, one per location: it alone takes the payments that ask for
 * a PIN, picks up a sale a seller started on a phone, and keeps selling without the network.
 */
enum RegisterRole: string
{
    case Main = 'main';
    case Mobile = 'mobile';
}
