<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

/**
 * Where an activity opens: in the activity page itself, framed in it, or in a window of its own.
 */
enum LaunchWindow: string
{
    /** The page shows the activity's own text, and the button that marks it done. */
    case Page = 'page';

    /** The page frames the content, served from the content origin. */
    case Embedded = 'embedded';

    /** The content opens in a window of its own. */
    case NewWindow = 'new_window';
}
