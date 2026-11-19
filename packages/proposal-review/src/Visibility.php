<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

/**
 * When a reviewer sees the other reviews of a proposal.
 */
enum Visibility: string
{
    /** Never: each reviewer sees their own review only. */
    case Never = 'never';

    /** Once their own review of the proposal is in, so that the others do not sway it. */
    case AfterOwnReview = 'after-own-review';

    /** At any time. */
    case Always = 'always';
}
