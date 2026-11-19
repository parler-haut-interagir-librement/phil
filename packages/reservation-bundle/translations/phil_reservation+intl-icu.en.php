<?php

declare(strict_types=1);

return [
    'form.start' => 'Start',
    'form.end' => 'End',
    'form.quantity' => 'How many',
    'form.order' => 'The end comes after the start.',
    'violation.quantity' => '{max, plural, one {Only one unit can be booked.} other {From 1 to # units can be booked.}}',
    'violation.too_soon' => '{minutes, plural, =0 {This time has passed.} one {Book at least # minute ahead.} other {Book at least # minutes ahead.}}',
    'violation.too_far_ahead' => '{minutes, plural, one {Book at most # minute ahead.} other {Book at most # minutes ahead.}}',
    'violation.too_short' => '{minutes, plural, one {A booking lasts at least # minute.} other {A booking lasts at least # minutes.}}',
    'violation.too_long' => '{minutes, plural, one {A booking lasts at most # minute.} other {A booking lasts at most # minutes.}}',
    'violation.off_grid' => '{minutes, plural, one {Bookings start and end on the minute.} other {Bookings start and end on steps of # minutes.}}',
    'violation.unavailable' => 'This time is outside the opening hours.',
    'violation.full' => '{free, plural, =0 {This time is already booked.} one {Only # unit is left at this time.} other {Only # units are left at this time.}}',
];
