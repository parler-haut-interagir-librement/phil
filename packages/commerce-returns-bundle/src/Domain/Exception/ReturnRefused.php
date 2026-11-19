<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Exception;

use DomainException;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A return refused, or a step it cannot take.
 */
final class ReturnRefused extends DomainException implements DomainError
{
    public static function unknown(string $id): self
    {
        return new self(\sprintf('No return is known as %s.', $id));
    }

    public static function notPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: nothing of it can come back.', $orderNumber));
    }

    public static function tooLate(string $orderNumber, int $days): self
    {
        return new self(\sprintf('The order %s is past its %d days of returns.', $orderNumber, $days));
    }

    public static function notInOrder(string $orderNumber, string $line): self
    {
        return new self(\sprintf('The order %s has no line %s.', $orderNumber, $line));
    }

    public static function tooMany(string $line, int $asked, int $left): self
    {
        return new self(\sprintf('%d of %s cannot come back: %d can.', $asked, $line, $left));
    }

    public static function transition(string $id, ReturnState $from, ReturnState $to): self
    {
        return new self(\sprintf('The return %s cannot go from %s to %s.', $id, $from->value, $to->value));
    }

    public static function conditionMissing(string $id, string $line): self
    {
        return new self(\sprintf('The return %s needs the condition of %s.', $id, $line));
    }

    public static function noStoreCredit(): self
    {
        return new self('Store credit needs phil/commerce-stored-value-bundle, and a customer to credit.');
    }

    public static function notAnExchange(string $orderNumber): self
    {
        return new self(\sprintf('The order %s cannot take an exchange: it is not placed, or in another currency.', $orderNumber));
    }

    public static function notAuthorized(string $id, ReturnState $state): self
    {
        return new self(\sprintf('The return %s is %s: only an authorized return gets a label or a slip.', $id, $state->value));
    }

    public static function partOfSet(string $orderNumber, string $part, string $set): self
    {
        return new self(\sprintf('In the order %s, %s came in the set %s: the set comes back whole, never a part of it.', $orderNumber, $part, $set));
    }

    public static function tooManyRequests(): self
    {
        return new self('Too many returns were asked for in a short time: try again later.');
    }

    public static function noLabels(): self
    {
        return new self('Return labels need a carrier adapter: implement ReturnLabels.');
    }

    public static function noSlips(): self
    {
        return new self("Return slips need a renderer and the core's document register: implement ReturnSlipRenderer.");
    }

    public static function noReceipt(string $orderNumber): self
    {
        return new self(\sprintf('The order %s has no receipt: a credit note reverses one.', $orderNumber));
    }

    public static function alreadyCredited(string $id, string $number): self
    {
        return new self(\sprintf('The return %s is already credited by %s.', $id, $number));
    }

    public static function creditedWhole(string $line, string $number): self
    {
        return new self(\sprintf('The line %s is already credited whole by %s.', $line, $number));
    }

    public static function notSettled(string $id, ReturnState $state): self
    {
        return new self(\sprintf('The return %s is %s: a credit note waits until its goods were received.', $id, $state->value));
    }
}
