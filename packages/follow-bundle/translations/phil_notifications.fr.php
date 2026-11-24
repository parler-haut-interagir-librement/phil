<?php

declare(strict_types=1);

/*
 * The titles and bodies of the notification types the bundle declares, in the domain of phil/notifications-bundle, in French.
 */
return [
    'follow.activity.title' => 'Nouvelle activité sur %subject%',
    'follow.activity.body' => '{1}%actor% a ajouté quelque chose.|]1,Inf[%count% nouvelles activités.',
    'follow.activity_quiet.title' => 'Encore de l’activité sur %subject%',
    'follow.activity_quiet.body' => '{1}Une de plus depuis votre dernière notification.|]1,Inf[%count% de plus depuis votre dernière notification.',
    'follow.requested.title' => '%actor% vous a envoyé une demande',
    'follow.requested.body' => 'Acceptez-la ou refusez-la depuis vos demandes.',
    'follow.accepted.title' => '%actor% a accepté votre demande',
    'follow.accepted.body' => 'Vous êtes maintenant informé de son activité.',
    'follow.followed.title' => '%actor% vous suit',
    'follow.followed.body' => 'Cette personne est informée de votre activité.',
];
