<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use Override;
use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Console\PurgeCommand;
use Phil\FormsAutosave\Infrastructure\Http\DoneCookie;
use Phil\FormsAutosave\Infrastructure\Http\SubmittedDraft;
use Phil\FormsAutosave\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Phil\FormsAutosave\Infrastructure\Security\TokenDraftOwner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(KeyManagementSealer::class)]
#[CoversClass(TokenDraftOwner::class)]
#[CoversClass(DoneCookie::class)]
#[CoversClass(SubmittedDraft::class)]
#[CoversClass(PurgeCommand::class)]
final class AdaptersTest extends TestCase
{
    public function testTheKeyManagementSealerSealsInAContextAndOpensOnlyThere(): void
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('k', 32), 'k2' => str_repeat('l', 32)]));
        $sealer = new KeyManagementSealer($kms, 'k1');
        $sealed = $sealer->seal('{"a":1}', 'ctx');
        $bytes = base64_decode($sealed, true);

        self::assertIsString($bytes);
        self::assertSame('k1', Envelope::fromBytes($bytes)->keyId);
        self::assertStringNotContainsString('"a"', $bytes);
        self::assertNotSame($sealed, $sealer->seal('{"a":1}', 'ctx'), 'a fresh data key each time');
        self::assertSame('{"a":1}', $sealer->open($sealed, 'ctx'));
        self::assertSame('{"a":1}', new KeyManagementSealer($kms, 'k2')->open($sealed, 'ctx'), 'opened under the key it names');
        self::assertNull($sealer->open($sealed, 'other'));
        self::assertNull($sealer->open('not sealed', 'ctx'));
        self::assertNull($sealer->open(base64_encode('not an envelope'), 'ctx'));
        self::assertNull($sealer->open('*'.$sealed, 'ctx'), 'strict base64');
        self::assertNull(new KeyManagementSealer(new SodiumKms(new InMemoryKeyLoader(['k2' => str_repeat('l', 32)])), 'k2')->open($sealed, 'ctx'), 'a master key the KMS no longer holds');
    }

    public function testTheKeyManagementSealerOpensNothingWhenTheKmsFails(): void
    {
        $sealed = new KeyManagementSealer(new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('k', 32)])), 'k1')->seal('{"a":1}', 'ctx');
        $failing = new class implements DataKeyGeneratorInterface {
            #[Override]
            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                throw new RuntimeException('The KMS is unreachable.');
            }

            #[Override]
            public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
            {
                throw new RuntimeException('The KMS is unreachable.');
            }

            #[Override]
            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                throw new RuntimeException('The KMS is unreachable.');
            }
        };

        self::assertNull(new KeyManagementSealer($failing, 'k1')->open($sealed, 'ctx'));
    }

    public function testTheKeyManagementSealerRefusesAMasterKeyTheKmsDoesNotHold(): void
    {
        $this->expectException(KeyNotFoundException::class);

        new KeyManagementSealer(new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('k', 32)])), 'k9')->seal('{"a":1}', 'ctx');
    }

    public function testTheOwnerIsTheSignedInUserOfSecurity(): void
    {
        $tokens = new TokenStorage();
        $owner = new TokenDraftOwner($tokens);
        self::assertNull($owner->current());

        $tokens->setToken(new NullToken());
        self::assertNull($owner->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.com', null), 'main'));
        self::assertSame('ada@example.com', $owner->current());
    }

    public function testTheKeysOfSentFormsAreSetInACookieForADay(): void
    {
        $listener = new DoneCookie(new MockClock('2026-11-08 15:00:00 UTC'));
        $first = 'aaaaaaaaaaaaaaaa';
        $request = new Request([], [], [DoneCookie::ATTRIBUTE => [$this->submitted($first)]]);
        $cookie = $this->cookie($listener, $request);

        self::assertNotNull($cookie);
        self::assertSame(['phil_forms_autosave_done', $first, '/', null, false, false, 'lax', false], [
            $cookie->getName(), $cookie->getValue(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly(), $cookie->getSameSite(), $cookie->isRaw(),
        ]);
        self::assertSame('2026-11-09 15:00:00', gmdate('Y-m-d H:i:s', $cookie->getExpiresTime()));

        $secure = Request::create('https://example.com/', 'POST');
        $secure->attributes->set(DoneCookie::ATTRIBUTE, [$this->submitted($first)]);
        self::assertTrue($this->cookie($listener, $secure)?->isSecure());
    }

    public function testTheCookieAddsToTheKeysItHadAndKeepsTheTenLatest(): void
    {
        $listener = new DoneCookie(new MockClock());
        $keys = array_map(static fn (int $i): string => str_repeat((string) $i, 16), range(0, 9));
        $request = new Request([], [], [DoneCookie::ATTRIBUTE => [$this->submitted('bbbbbbbbbbbbbbbb'), $this->submitted($keys[1])]], [DoneCookie::COOKIE => implode('.', [...$keys, 'bad!', 'short'])]);

        self::assertSame(implode('.', [...\array_slice($keys, 2), 'bbbbbbbbbbbbbbbb', $keys[1]]), $this->cookie($listener, $request)?->getValue());
        self::assertSame(10, DoneCookie::KEEP);

        $fresh = new Request([], [], [DoneCookie::ATTRIBUTE => [7, $this->submitted('cccccccccccccccc'), $this->submitted('cccccccccccccccc')]]);
        self::assertSame('cccccccccccccccc', $this->cookie($listener, $fresh)?->getValue());
    }

    public function testNoCookieWithoutASentForm(): void
    {
        $listener = new DoneCookie(new MockClock());

        foreach ([
            new Request(),
            new Request([], [], [DoneCookie::ATTRIBUTE => []]),
            new Request([], [], [DoneCookie::ATTRIBUTE => $this->submitted('aaaaaaaaaaaaaaaa')]),
            new Request([], [], [DoneCookie::ATTRIBUTE => ['aaaaaaaaaaaaaaaa']]),
            new Request([], [], [DoneCookie::ATTRIBUTE => [$this->submitted('aaaaaaaaaaaaaaaa', valid: false)]]),
        ] as $request) {
            self::assertNull($this->cookie($listener, $request));
        }
    }

    public function testThePurgeDeletesTheExpiredDrafts(): void
    {
        $clock = new MockClock('2026-11-08 15:00:00 UTC');
        $store = new InMemoryDraftStore();
        $keys = new DraftKeys('secret');
        $store->insert(Draft::first($keys->owner('ada'), DraftKey::of('aaaaaaaaaaaaaaaa'), 'x', $clock->now(), 60));
        $store->insert(Draft::first($keys->owner('ada'), DraftKey::of('bbbbbbbbbbbbbbbb'), 'x', $clock->now(), 60));

        $clock->sleep(60);
        $tester = new CommandTester(new PurgeCommand(new Drafts($store, new PlainSealer(), $keys, $clock, 60)));

        self::assertSame(0, $tester->execute([]));
        self::assertSame("2 expired draft(s) deleted.\n", $tester->getDisplay());
        self::assertSame('phil:forms-autosave:purge', new PurgeCommand(new Drafts($store, new PlainSealer(), $keys, $clock))->getName());
    }

    public function testTheServerCopiesOfValidFormsAreDeleted(): void
    {
        $clock = new MockClock('2026-11-08 15:00:00 UTC');
        $drafts = new Drafts(new InMemoryDraftStore(), new PlainSealer(), new DraftKeys('secret'), $clock, 60);
        $keys = ['aaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbb', 'cccccccccccccccc'];

        foreach ($keys as $key) {
            $drafts->save('ada', DraftKey::of($key), null, DraftContent::of('v', []));
        }

        $request = new Request([], [], [DoneCookie::ATTRIBUTE => [
            $this->submitted($keys[0], 'ada'),
            $this->submitted($keys[1], 'ada', false),
            $this->submitted($keys[2]),
        ]]);
        $this->cookie(new DoneCookie($clock, $drafts), $request);

        self::assertNull($drafts->read('ada', DraftKey::of($keys[0])));
        self::assertNotNull($drafts->read('ada', DraftKey::of($keys[1])), 'an invalid form keeps its draft');
        self::assertNotNull($drafts->read('ada', DraftKey::of($keys[2])), 'a form without a server copy to delete');
        self::assertSame($keys[0].'.'.$keys[2], $this->cookie(new DoneCookie($clock), $request)?->getValue());
    }

    private function submitted(string $key, ?string $user = null, bool $valid = true): SubmittedDraft
    {
        $form = Forms::createFormFactory()->createNamed('post', FormType::class);
        $form->submit([]);

        if (!$valid) {
            $form->addError(new FormError('Wrong.'));
        }

        return new SubmittedDraft($form, DraftKey::of($key), $user);
    }

    private function cookie(DoneCookie $listener, Request $request): ?Cookie
    {
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        $listener($event);

        return $event->getResponse()->headers->getCookies()[0] ?? null;
    }
}
