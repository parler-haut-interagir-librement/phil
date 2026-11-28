<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Phil\AudienceSegments\Condition\DateParticipant;
use Phil\AudienceSegments\Condition\PersonTagged;
use Phil\AudienceSegments\Condition\PurchaseMade;
use Phil\AudienceSegments\Condition\SelectionMember;
use Phil\AudienceSegments\Condition\SupporterActive;
use Phil\AudienceSegments\Condition\TicketHolder;
use Phil\AudienceSegments\Contract\Participation;
use Phil\AudienceSegments\Contract\PeopleTags;
use Phil\AudienceSegments\Contract\Purchases;
use Phil\AudienceSegments\Contract\Supporters;
use Phil\Rules\Type\ConditionTypes;

/**
 * The audience condition types, each registered when the port it reads is given: an application without a shop gives
 * no `Purchases`, and a segment naming `purchase.made` is then refused when it is read.
 */
final class AudienceConditions
{
    private function __construct()
    {
    }

    public static function types(
        ?Participation $participation = null,
        ?Purchases $purchases = null,
        ?Supporters $supporters = null,
        ?PeopleTags $tags = null,
    ): ConditionTypes {
        $types = [];

        if ($participation instanceof Participation) {
            $types[] = new TicketHolder($participation);
            $types[] = new DateParticipant($participation);
            $types[] = new SelectionMember($participation);
        }

        if ($purchases instanceof Purchases) {
            $types[] = new PurchaseMade($purchases);
        }

        if ($supporters instanceof Supporters) {
            $types[] = new SupporterActive($supporters);
        }

        if ($tags instanceof PeopleTags) {
            $types[] = new PersonTagged($tags);
        }

        return new ConditionTypes(...$types);
    }
}
