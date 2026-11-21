<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use Closure;
use ErrorException;
use Jose\Component\Core\JWK;
use OpenSSLAsymmetricKey;
use Override;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\LoadedKeys;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\FileSigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\SealKeyCommand;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The sources of the signing keys: the database, whose key is loaded once per process, and key files, read once,
 * sealed or not; the keys sign without being rebuilt, and the commands that rotate and seal them.
 */
#[CoversClass(SigningKeys::class)]
#[CoversClass(FileSigningKeys::class)]
#[CoversClass(LoadedKeys::class)]
#[CoversClass(PrivateKey::class)]
#[CoversClass(RotateKeysCommand::class)]
#[CoversClass(SealKeyCommand::class)]
final class SigningKeySourcesTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-identity-keys-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ((array) glob($this->directory.'/*') as $file) {
            unlink((string) $file);
        }

        rmdir($this->directory);
    }

    public function testTheKeyOfTheDatabaseIsLoadedOncePerProcess(): void
    {
        $services = new Services();
        $cipher = new RecordingCipher();
        $loaded = new LoadedKeys();
        $keys = new SigningKeys(new InMemorySigningKeyRepository(), $cipher, new ProviderSettings('https://id.example', SigningAlgorithm::ES256), $services->clock, new LockFactory(new InMemoryStore()), null, $loaded);

        $first = $keys->active();
        $opened = \count(array_filter($cipher->contexts, static fn (string $context): bool => $context === 'signing-key:'.$first->kid));
        self::assertSame($first, $keys->active());
        self::assertSame($first, $keys->active());
        self::assertSame(1, $loaded->count());
        self::assertCount($opened, array_filter($cipher->contexts, static fn (string $context): bool => $context === 'signing-key:'.$first->kid));

        $keys->rotate(true);
        $keys->rotate(true);

        $second = $keys->active();
        self::assertNotSame($first->kid, $second->kid);
        self::assertSame($second, $keys->active());
        self::assertSame(2, $loaded->count());
    }

    public function testTheLoadedKeysKeepAFewAndDropTheOldestFirst(): void
    {
        $loaded = new LoadedKeys();
        $calls = [];
        $load = static function (string $name) use (&$calls): Closure {
            return static function () use ($name, &$calls): PrivateKey {
                $calls[] = $name;

                return PrivateKey::generate(SigningAlgorithm::EdDSA, $name);
            };
        };

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $loaded->get($name, $load($name));
        }

        self::assertSame('a', $loaded->get('a', $load('a'))->kid);
        self::assertSame(4, $loaded->count());
        self::assertSame(['a', 'b', 'c', 'd'], $calls);

        $loaded->get('e', $load('e'));
        self::assertSame(LoadedKeys::MAX, $loaded->count());
        $loaded->get('b', $load('b'));
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $calls);
        $loaded->get('a', $load('a'));
        self::assertSame(['a', 'b', 'c', 'd', 'e', 'a'], $calls);
    }

    public function testEachAlgorithmSignsWithItsLoadedKey(): void
    {
        foreach ([SigningAlgorithm::ES256, SigningAlgorithm::EdDSA] as $algorithm) {
            $key = PrivateKey::generate($algorithm, 'k');
            $public = new JWK($key->publicJwk());

            for ($i = 0; $i < 20; ++$i) {
                $token = Jws::encode(['n' => $i], $key);
                self::assertSame(['n' => $i], Jws::decode($token, static fn (): JWK => $public));
                self::assertSame(64, \strlen(Base64Url::decode(explode('.', $token)[2])));
            }

            self::assertSame($public->thumbprint('sha256'), $key->thumbprint());
            self::assertSame(43, \strlen($key->thumbprint()));
        }
    }

    public function testAnRsaKeyWithoutItsChineseRemainderMembersSignsToo(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::RS256, 'k');
        /** @var array<string, string> $members */
        $members = json_decode($key->material(), true);
        $bare = PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', json_encode(array_diff_key($members, ['dp' => true, 'dq' => true, 'qi' => true]), \JSON_THROW_ON_ERROR));
        $public = new JWK($key->publicJwk());

        self::assertSame(['a' => 1], Jws::decode(Jws::encode(['a' => 1], $bare), static fn (): JWK => $public));
        self::assertSame(['a' => 2], Jws::decode(Jws::encode(['a' => 2], $key), static fn (): JWK => $public));
        self::assertSame(384, \strlen(Base64Url::decode(explode('.', Jws::encode(['a' => 3], $key))[2])));
    }

    public function testAnEd25519KeyWhosePublicHalfIsNotItsOwnIsRefused(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA, 'k');
        $other = PrivateKey::generate(SigningAlgorithm::EdDSA, 'other');
        /** @var array<string, string> $members */
        $members = json_decode($key->material(), true);

        foreach ([
            ['x' => $other->publicJwk()['x']] + $members,
            ['d' => Base64Url::encode(str_repeat("\1", 31))] + $members,
        ] as $material) {
            try {
                PrivateKey::fromMaterial(SigningAlgorithm::EdDSA, 'k', json_encode($material, \JSON_THROW_ON_ERROR));
                self::fail('Accepted.');
            } catch (InvalidValue $invalid) {
                self::assertStringContainsString('Ed25519', $invalid->getMessage());
            }
        }
    }

    public function testTheFilesSignTheFirstAndPublishThemAll(): void
    {
        $first = PrivateKey::generate(SigningAlgorithm::ES256, 'ignored');
        $second = PrivateKey::generate(SigningAlgorithm::ES256, 'ignored');
        $ec = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $ec);
        openssl_pkey_export($ec, $pem);
        self::assertIsString($pem);
        file_put_contents($this->directory.'/first.json', "\n".$first->material()."\n");
        file_put_contents($this->directory.'/second.pem', $pem);
        file_put_contents($this->directory.'/third.json', $second->material());

        $keys = new FileSigningKeys([['path' => $this->directory.'/first.json'], ['path' => $this->directory.'/second.pem', 'kid' => 'named'], ['path' => $this->directory.'/third.json', 'kid' => null]], SigningAlgorithm::ES256);
        $active = $keys->active();

        self::assertSame($first->thumbprint(), $active->kid);
        self::assertSame($active, $keys->active());
        self::assertSame([$first->thumbprint(), 'named', $second->thumbprint()], array_map(static fn (array $jwk): string => $jwk['kid'], $keys->jwks()['keys']));
        self::assertArrayNotHasKey('d', $keys->jwks()['keys'][1]);
        self::assertSame('named', $keys->publicKey('named')?->get('kid'));
        self::assertNull($keys->publicKey('unknown'));
        self::assertSame(['a' => 1], Jws::decode(Jws::encode(['a' => 1], $active), $keys->publicKey(...)));

        unlink($this->directory.'/first.json');
        self::assertSame($active, $keys->active(), 'The files are read once.');
    }

    public function testSealedFilesAreOpenedWithTheirContext(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA, 'k');
        $cipher = new RecordingCipher();
        file_put_contents($this->directory.'/key.sealed', $cipher->seal($key->material(), FileSigningKeys::SEAL_CONTEXT));
        $cipher->contexts = [];

        $keys = new FileSigningKeys([['path' => $this->directory.'/key.sealed', 'kid' => 'sealed']], SigningAlgorithm::EdDSA, $cipher);
        self::assertSame('sealed', $keys->active()->kid);
        self::assertSame([FileSigningKeys::SEAL_CONTEXT], $cipher->contexts);
        self::assertSame($key->publicJwk()['x'], $keys->jwks()['keys'][0]['x']);
    }

    public function testAFileSourceRefusesWhatItCannotRead(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA, 'k');
        file_put_contents($this->directory.'/key.json', $key->material());
        file_put_contents($this->directory.'/empty', " \n");
        file_put_contents($this->directory.'/large', str_repeat('a', FileSigningKeys::MAX_FILE_SIZE + 1));
        file_put_contents($this->directory.'/limit', str_repeat('a', FileSigningKeys::MAX_FILE_SIZE));
        // A warning of PHP fails the test: a directory is refused before it is read.
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        foreach ([
            [[['path' => $this->directory.'/missing']], 'cannot be read'],
            [[['path' => $this->directory]], 'cannot be read'],
            [[['path' => $this->directory.'/empty']], 'cannot be read'],
            [[['path' => $this->directory.'/large']], 'cannot be read'],
            [[['path' => $this->directory.'/limit']], 'not a private key'],
            [[['path' => $this->directory.'/key.json'], ['path' => $this->directory.'/key.json']], 'Two signing key files'],
            [[], 'at least one file'],
        ] as [$files, $message]) {
            try {
                new FileSigningKeys($files, SigningAlgorithm::EdDSA)->jwks();
                self::fail('Accepted: '.$message);
            } catch (InvalidValue $invalid) {
                self::assertStringContainsString($message, $invalid->getMessage());
            }
        }

        try {
            new FileSigningKeys([['path' => $this->directory.'/key.json']], SigningAlgorithm::ES256)->active();
            self::fail('Accepted a key of another algorithm.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString('ES256', $invalidValue->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    public function testTheRotationCanBeLeftToAnOperator(): void
    {
        $services = new Services();
        $store = new InMemorySigningKeyRepository();
        $keys = new SigningKeys($store, new RecordingCipher(), new ProviderSettings('https://id.example', SigningAlgorithm::EdDSA), $services->clock, new LockFactory(new InMemoryStore()));
        $application = new Application();
        $application->addCommand(new RotateKeysCommand($keys, false));

        $tester = new CommandTester($application->find('phil:identity-provider:rotate-keys'));

        $all = static fn (): array => self::all($store);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Automatic rotation is off', $tester->getDisplay());
        self::assertSame([], $all());
        self::assertSame(0, $tester->execute(['--force' => true]));
        self::assertMatchesRegularExpression('/Activated: (?!none)[\w-]{16}\. Created: (?!none)[\w-]{16}\./', $tester->getDisplay());
        self::assertCount(1, $all());
        self::assertSame(0, $tester->execute(['--now' => true]));
        self::assertMatchesRegularExpression('/Activated: none\. Created: (?!none)[\w-]{16}\./', $tester->getDisplay());
        self::assertCount(2, $all());

        $automatic = new Application();
        $automatic->addCommand(new RotateKeysCommand($keys));

        $tester = new CommandTester($automatic->find('phil:identity-provider:rotate-keys'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Keys removed:', $tester->getDisplay());
    }

    /**
     * @return list<SigningKey>
     *
     * @phpstan-impure
     */
    private static function all(InMemorySigningKeyRepository $store): array
    {
        return $store->all();
    }

    public function testAKeyFileIsSealedForTheFileSource(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::ES256, 'k');
        file_put_contents($this->directory.'/key.json', $key->material());
        file_put_contents($this->directory.'/other.json', PrivateKey::generate(SigningAlgorithm::EdDSA, 'k')->material());
        $cipher = new RecordingCipher();
        $application = new Application();
        $application->addCommand(new SealKeyCommand($cipher, SigningAlgorithm::ES256));

        $tester = new CommandTester($application->find('phil:identity-provider:seal-key'));

        self::assertSame(0, $tester->execute(['path' => $this->directory.'/key.json']));
        $sealed = trim($tester->getDisplay(true));
        self::assertStringStartsWith('sealed.{', $sealed);
        self::assertSame([FileSigningKeys::SEAL_CONTEXT], $cipher->contexts);
        file_put_contents($this->directory.'/key.sealed', $sealed);
        self::assertSame($key->publicJwk()['x'], new FileSigningKeys([['path' => $this->directory.'/key.sealed']], SigningAlgorithm::ES256, $cipher)->jwks()['keys'][0]['x']);

        self::assertSame(1, $tester->execute(['path' => $this->directory.'/missing']));
        self::assertStringContainsString('cannot be read', $this->oneLine($tester->getDisplay()));
        self::assertSame(1, $tester->execute(['path' => $this->directory.'/other.json']));
        self::assertStringContainsString('ES256', $tester->getDisplay());
    }

    /**
     * The console wraps its blocks at 80 columns, and the path of a package installed on its own is long.
     */
    private function oneLine(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }

    public function testTheSealCommandRefusesWhatItCannotRead(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::ES256, 'k');
        file_put_contents($this->directory.'/blank', " \n");
        file_put_contents($this->directory.'/limit', str_repeat('a', FileSigningKeys::MAX_FILE_SIZE));
        file_put_contents($this->directory.'/large', str_repeat('a', FileSigningKeys::MAX_FILE_SIZE + 1));
        file_put_contents($this->directory.'/padded.json', "\n  ".$key->material()."\n\n");
        $cipher = new RecordingCipher();
        $application = new Application();
        $application->addCommand(new SealKeyCommand($cipher, SigningAlgorithm::ES256));

        $tester = new CommandTester($application->find('phil:identity-provider:seal-key'));

        // A warning of PHP fails the test: a directory is refused before it is read.
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        try {
            foreach (['blank' => true, 'large' => true, 'limit' => false] as $file => $unreadable) {
                self::assertSame(1, $tester->execute(['path' => $this->directory.'/'.$file]), $file);
                self::assertSame($unreadable, str_contains($this->oneLine($tester->getDisplay()), 'cannot be read'), $file);
            }

            self::assertSame(1, $tester->execute(['path' => $this->directory]));
            self::assertStringContainsString('cannot be read', $this->oneLine($tester->getDisplay()));
        } finally {
            restore_error_handler();
        }

        self::assertSame(0, $tester->execute(['path' => $this->directory.'/padded.json']));
        self::assertStringStartsWith('sealed.', trim($tester->getDisplay(true)));
    }
}
