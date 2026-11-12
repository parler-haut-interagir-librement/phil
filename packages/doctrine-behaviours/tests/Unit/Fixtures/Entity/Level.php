<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

enum Level: string
{
    case Beginner = 'beginner';
    case Advanced = 'advanced';
}
