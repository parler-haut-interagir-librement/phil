<?php

declare(strict_types=1);

namespace Phil\Contact;

/**
 * What a phone line of a card is for. Whether a number is toll-free or a mobile is told by its numbering plan; the
 * role says how the card's owner uses it.
 */
enum PhoneRole: string
{
    case Voice = 'voice';
    case Mobile = 'mobile';
    case Fax = 'fax';
    /** Text messages only. */
    case Text = 'text';
    case Pager = 'pager';

    /**
     * The vCard `TYPE` of the role.
     */
    public function vCardType(): string
    {
        return match ($this) {
            self::Mobile => 'cell',
            default => $this->value,
        };
    }
}
