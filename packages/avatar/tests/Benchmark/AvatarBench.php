<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Benchmark;

use Phil\Avatar\AvatarGenerator;
use Phil\Avatar\AvatarOptions;
use Phil\Avatar\Initials;

/**
 * One avatar of each style, the way a list of members draws them one by one, and the initials of a long name in two
 * scripts.
 */
final readonly class AvatarBench
{
    private AvatarGenerator $generator;

    public function __construct()
    {
        $this->generator = new AvatarGenerator();
    }

    public function benchInitials(): void
    {
        $this->generator->svg('member-42', new AvatarOptions(name: 'Ada King Lovelace'));
    }

    public function benchShapes(): void
    {
        $this->generator->svg('member-42', new AvatarOptions(style: 'shapes'));
    }

    public function benchPixels(): void
    {
        $this->generator->svg('member-42', new AvatarOptions(style: 'pixels'));
    }

    public function benchLongName(): void
    {
        Initials::of(str_repeat('Ada Lovelace محمد علي ', 40), 3);
    }
}
