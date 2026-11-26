<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Catalogue;

/**
 * Who a catalogue is shown to: everyone — visitors signed out included — or the members of one unit of an organization,
 * and of the units below it, as the application's `LearnerUnits` names them.
 */
enum CatalogueAudience: string
{
    case Public = 'public';
    case Unit = 'unit';
}
