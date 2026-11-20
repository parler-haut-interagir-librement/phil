<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application\Contract;

use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A form the register shows to enter a draw made in front of the customer — real dice rolled on the counter — instead
 * of the drawer the option names drawing it. The package that provides the drawer provides its form: the register
 * renders the template it names, and hands back what the cashier entered.
 *
 * A service implementing it is tagged `phil_commerce_pos.till_draw_form` by autoconfiguration.
 */
interface TillDrawForm
{
    /**
     * Whether it enters the draws of this drawer, as an option names it.
     */
    public function supports(string $drawer): bool;

    /**
     * The Twig template of the form for one unit, rendered with `draw` — a `PendingDraw` — and `unit`, numbered from 1.
     */
    public function template(string $drawer): string;

    /**
     * Reads what the cashier entered for one unit, numbered from 1.
     *
     * @param array<string, mixed> $entered the form's fields, as the request sent them
     *
     * @return string the draw, as a receipt shows it: 1 to 1000 characters
     *
     * @throws InvalidValue when the entry is not a draw this drawer can give
     */
    public function read(string $drawer, array $entered, OrderLine $line, string $option, int $unit): string;
}
