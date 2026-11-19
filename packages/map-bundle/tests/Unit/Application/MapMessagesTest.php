<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Application;

use Phil\MapBundle\Application\MapMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(MapMessages::class)]
final class MapMessagesTest extends TestCase
{
    public function testEnglishWithoutATranslator(): void
    {
        $messages = new MapMessages();

        self::assertSame('One person in this area', $messages->trans('neighbourhood.area', ['%count%' => 1]));
        self::assertSame('4 people in this area', $messages->trans('neighbourhood.area', ['%count%' => 4]));
        self::assertSame('Places on the map', $messages->trans('list.places'));
    }

    public function testTheTranslatorOfTheApplication(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource('php', __DIR__.'/../../../translations/phil_map.fr.php', 'fr', MapMessages::DOMAIN);

        $messages = new MapMessages($translator);

        self::assertSame('4 personnes dans ce secteur', $messages->trans('neighbourhood.area', ['%count%' => 4]));
        self::assertSame('Réservé', $messages->trans('booth.taken'));
    }

    public function testEnglishWhenTheTranslatorHasNoMessage(): void
    {
        self::assertSame('Availability unknown', new MapMessages(new Translator('de'))->trans('booth.unknown'));
    }

    public function testTheTranslationsHaveTheSameKeysAsTheEnglishOfTheCode(): void
    {
        $english = require __DIR__.'/../../../translations/phil_map.en.php';
        $french = require __DIR__.'/../../../translations/phil_map.fr.php';

        self::assertSame(MapMessages::ENGLISH, $english);
        self::assertIsArray($french);
        self::assertSame(array_keys(MapMessages::ENGLISH), array_keys($french));
    }
}
