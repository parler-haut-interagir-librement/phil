<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit;

use Closure;
use Phil\ContentAccess\Exception\InvalidViewer;
use Phil\ContentAccess\Viewer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Viewer::class)]
#[CoversClass(InvalidViewer::class)]
final class ViewerTest extends TestCase
{
    public function testAnAnonymousViewerHoldsOnlyItsAddress(): void
    {
        $viewer = Viewer::anonymous('192.0.2.7');

        self::assertNull($viewer->id);
        self::assertSame([], $viewer->roles);
        self::assertNull($viewer->verifiedEmailDomain);
        self::assertSame('192.0.2.7', $viewer->ip);
        self::assertFalse($viewer->isSignedIn());
        self::assertNull(Viewer::anonymous()->ip);
    }

    public function testAMemberKeepsItsRolesOnceAndItsDomainCanonical(): void
    {
        $viewer = Viewer::member('member-42', ['editor', 'editor', 'ROLE_STAFF'], '@Library.Example.ORG.', '2001:db8::1');

        self::assertSame('member-42', $viewer->id);
        self::assertSame(['editor', 'ROLE_STAFF'], $viewer->roles);
        self::assertSame('library.example.org', $viewer->verifiedEmailDomain);
        self::assertSame('2001:db8::1', $viewer->ip);
        self::assertTrue($viewer->isSignedIn());
    }

    public function testAMemberWithoutOptionsHasNoRoleDomainNorAddress(): void
    {
        $viewer = Viewer::member('m');

        self::assertSame([], $viewer->roles);
        self::assertNull($viewer->verifiedEmailDomain);
        self::assertNull($viewer->ip);
    }

    public function testTheLongestIdentifierAndAHundredRolesAreAccepted(): void
    {
        $roles = array_map(static fn (int $i): string => 'r'.$i, range(1, Viewer::MAX_ROLES));

        $viewer = Viewer::member(str_repeat('a', 255), $roles);

        self::assertCount(100, $viewer->roles);
    }

    /**
     * @return iterable<string, array{Closure(): Viewer, string}>
     */
    public static function refused(): iterable
    {
        yield 'an empty identifier' => [static fn (): Viewer => Viewer::member(''), "The viewer's identifier is empty, longer than 255 bytes or holds a control character."];
        yield 'a long identifier' => [static fn (): Viewer => Viewer::member(str_repeat('a', 256)), "The viewer's identifier is empty, longer than 255 bytes or holds a control character."];
        yield 'a control character' => [static fn (): Viewer => Viewer::member("a\nb"), "The viewer's identifier is empty, longer than 255 bytes or holds a control character."];
        yield 'a delete character' => [static fn (): Viewer => Viewer::member("a\x7F"), "The viewer's identifier is empty, longer than 255 bytes or holds a control character."];
        yield 'too many roles' => [static fn (): Viewer => Viewer::member('m', array_map(static fn (int $i): string => 'r'.$i, range(0, 100))), "The viewer's roles are more than 100."];
        yield 'a role with a space' => [static fn (): Viewer => Viewer::member('m', ['an editor']), "The viewer's role is not an identifier."];
        yield 'a role after a newline' => [static fn (): Viewer => Viewer::member('m', ["editor\n"]), "The viewer's role is not an identifier."];
        yield 'a domain with a space' => [static fn (): Viewer => Viewer::member('m', [], 'exa mple.org'), "The viewer's email domain is not a host name."];
        yield 'an empty domain' => [static fn (): Viewer => Viewer::member('m', [], '@'), "The viewer's email domain is not a host name."];
        yield 'a label ending with a dash' => [static fn (): Viewer => Viewer::member('m', [], 'example-.org'), "The viewer's email domain is not a host name."];
        yield 'a domain then a newline' => [static fn (): Viewer => Viewer::member('m', [], "example.org\nx"), "The viewer's email domain is not a host name."];
        yield 'not an address' => [static fn (): Viewer => Viewer::anonymous('192.0.2'), "The viewer's address is not an IP address."];
        yield 'a member without an address' => [static fn (): Viewer => Viewer::member('m', [], null, 'localhost'), "The viewer's address is not an IP address."];
    }

    /**
     * @param Closure(): Viewer $build
     */
    #[DataProvider('refused')]
    public function testARefusedViewerSaysWhyWithoutItsValue(Closure $build, string $message): void
    {
        $this->expectException(InvalidViewer::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testADomainIsTrimmedAndAcceptedAtItsLongest(): void
    {
        $label = str_repeat('a', 63);

        self::assertSame('example.org', Viewer::domain('  example.org  '));
        self::assertSame($label.'.org', Viewer::domain($label.'.org'));
        self::assertSame('xn--bcher-kva.example', Viewer::domain('xn--bcher-kva.example'));
        self::assertSame('localhost', Viewer::domain('localhost'));
    }

    public function testALabelLongerThanSixtyThreeIsRefused(): void
    {
        $this->expectException(InvalidViewer::class);

        Viewer::domain(str_repeat('a', 64).'.org');
    }

    public function testADomainLongerThanTwoHundredFiftyThreeIsRefused(): void
    {
        $this->expectException(InvalidViewer::class);

        Viewer::domain(implode('.', array_fill(0, 4, str_repeat('a', 63))).'.org');
    }
}
