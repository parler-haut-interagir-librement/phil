<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCardsBundle\Application\CardRequest;
use Phil\SocialCardsBundle\Infrastructure\Store\DirectoryCardRequestStore;
use Phil\SocialCardsBundle\Tests\Support\Directories;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DirectoryCardRequestStore::class)]
final class DirectoryCardRequestStoreTest extends TestCase
{
    public function testRequestsKeptAsJson(): void
    {
        $directory = Directories::make('requests');
        $store = new DirectoryCardRequestStore($directory.'/');
        $fingerprint = str_repeat('ab', 16);
        $request = new CardRequest('default', new CardContent('Été', null, 'fr'));

        self::assertNull($store->find($fingerprint));
        self::assertTrue($store->save($fingerprint, $request));
        self::assertFalse($store->save($fingerprint, new CardRequest('other', new CardContent('Other'))));
        self::assertEquals($request, $store->find($fingerprint));
        self::assertSame('{"template":"default","title":"Été","subtitle":null,"locale":"fr"}', file_get_contents($directory.'/ab/'.$fingerprint.'.json'));
        self::assertSame(['.', '..', $fingerprint.'.json'], scandir($directory.'/ab'));
        self::assertEquals($request, new DirectoryCardRequestStore($directory)->find($fingerprint));

        Directories::remove($directory);
    }

    public function testWhatCannotBeRead(): void
    {
        $directory = Directories::make('requests');
        $store = new DirectoryCardRequestStore($directory);

        foreach (['cd' => 'not json', 'ef' => '"a string"', 'a1' => '{"template":"d"}', 'b2' => '[[[[["too deep"]]]]]'] as $prefix => $json) {
            mkdir($directory.'/'.$prefix);
            file_put_contents($directory.'/'.$prefix.'/'.str_repeat($prefix, 16).'.json', $json);
            self::assertNull($store->find(str_repeat($prefix, 16)), $json);
        }

        mkdir($directory.'/56/'.str_repeat('56', 16).'.json', 0o777, true);
        self::assertNull($store->find(str_repeat('56', 16)), 'A directory is no request.');

        $request = new CardRequest('d', new CardContent('T'));
        $deep = str_repeat('78', 16);
        mkdir($directory.'/78');
        file_put_contents($directory.'/78/'.$deep.'.json', '{"template":"d","title":"T","subtitle":null,"locale":"en"}');
        self::assertEquals($request, $store->find($deep), 'Three levels of JSON are read.');

        Directories::remove($directory);
    }

    public function testARequestThatCannotBeWritten(): void
    {
        $directory = Directories::make('requests');
        file_put_contents($directory.'/ab', 'a file where a directory goes');

        try {
            new DirectoryCardRequestStore($directory)->save(str_repeat('ab', 16), new CardRequest('d', new CardContent('T')));
            self::fail('The request cannot be written.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The request of the card "%s" cannot be written in "%s".', str_repeat('ab', 16), $directory.'/ab'), $runtimeException->getMessage());
        }

        mkdir($directory.'/cd/'.str_repeat('cd', 16).'.json.x', 0o777, true);
        chmod($directory.'/cd', 0o555);

        if (is_writable($directory.'/cd')) {
            Directories::remove($directory);
            self::markTestSkipped('This user writes in a directory whatever its mode, as root does.');
        }

        try {
            new DirectoryCardRequestStore($directory)->save(str_repeat('cd', 16), new CardRequest('d', new CardContent('T')));
            self::fail('The request cannot be written in a directory that is read-only.');
        } catch (RuntimeException) {
            self::assertSame(['.', '..', str_repeat('cd', 16).'.json.x'], scandir($directory.'/cd'), 'No temporary file is left.');
        } finally {
            chmod($directory.'/cd', 0o755);
        }

        Directories::remove($directory);

        $this->expectException(InvalidCardTemplate::class);
        new DirectoryCardRequestStore($directory)->find('../../etc/passwd');
    }
}
