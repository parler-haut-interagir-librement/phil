<?php

declare(strict_types=1);

/*
 * The titles and bodies of the notification types the bundle declares, in the domain of phil/notifications-bundle.
 */
return [
    'follow.activity.title' => 'New activity on %subject%',
    'follow.activity.body' => '{1}%actor% added something.|]1,Inf[%count% new activities.',
    'follow.activity_quiet.title' => 'More activity on %subject%',
    'follow.activity_quiet.body' => '{1}One more since your last notification.|]1,Inf[%count% more since your last notification.',
    'follow.requested.title' => '%actor% sent you a request',
    'follow.requested.body' => 'Accept or reject it from your requests.',
    'follow.accepted.title' => '%actor% accepted your request',
    'follow.accepted.body' => 'You now hear of their activity.',
    'follow.followed.title' => '%actor% follows you',
    'follow.followed.body' => 'They hear of your activity.',
];
