<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Unit;

use Phil\Slug\LeadingWordsRemoved;
use Phil\Slug\Sluggers;
use Phil\Slug\TransliteratingSlugger;
use Phil\Slug\UnknownSlugger;
use Phil\SlugBundle\Infrastructure\Twig\SlugExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlugExtension::class)]
final class SlugExtensionTest extends TestCase
{
    public function testATextIsSluggedByTheNamedSlugger(): void
    {
        $default = new TransliteratingSlugger();
        $extension = new SlugExtension(new Sluggers(['default' => $default, 'names' => new LeadingWordsRemoved($default, ['the'])], 'default'));

        self::assertSame(['the-guild', 'guild', '', '', ''], [$extension->slug('The Guild'), $extension->slug('The Guild', 'names'), $extension->slug(' ?! '), $extension->slug(null), $extension->slug(null, 'names')]);
    }

    public function testAnUnknownSluggerIsAnError(): void
    {
        $this->expectException(UnknownSlugger::class);

        new SlugExtension(new Sluggers(['default' => new TransliteratingSlugger()], 'default'))->slug(null, 'nope');
    }
}
