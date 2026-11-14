<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Domain;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subject::class)]
#[CoversClass(UnknownSetting::class)]
final class SubjectTest extends TestCase
{
    public function testAUserAndTheSite(): void
    {
        $user = Subject::user('ada@example.com');
        $site = Subject::site();

        self::assertSame([Scope::User, 'ada@example.com', 'user:ada@example.com'], [$user->scope, $user->id, $user->key()]);
        self::assertSame([Scope::Site, 'site', 'site:site'], [$site->scope, $site->id, $site->key()]);
        self::assertSame(str_repeat('x', 190), Subject::user(str_repeat('x', 190))->id);
    }

    public function testAUserIsAnIdentifier(): void
    {
        foreach (['', str_repeat('x', 191), 'a b', "a\tb", "ab\n", "a\x00b", "a\x7Fb"] as $id) {
            try {
                Subject::user($id);
                self::fail(json_encode($id, \JSON_THROW_ON_ERROR));
            } catch (InvalidSetting $invalidSetting) {
                self::assertSame(\sprintf('The user "%s" is not an identifier: at most 190 bytes, without a space or a control character.', $id), $invalidSetting->getMessage());
            }
        }
    }

    public function testTheUnknownAreNamed(): void
    {
        self::assertSame('No setting is declared under the key "a.b".', UnknownSetting::named('a.b')->getMessage());
        self::assertSame('No section is declared under the key "a".', UnknownSetting::section('a')->getMessage());
    }
}
