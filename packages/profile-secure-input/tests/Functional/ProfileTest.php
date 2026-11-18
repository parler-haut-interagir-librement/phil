<?php

declare(strict_types=1);

namespace Phil\Profile\SecureInput\Tests\Functional;

use Override;
use Phil\BotDetectionBundle\PhilBotDetectionBundle;
use Phil\Profile\SecureInput\Tests\Functional\App\TestKernel;
use Phil\RateLimit\StoreLocality;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SpamProtection\Token\TokenCodec;
use Phil\SpamProtectionBundle\PhilSpamProtectionBundle;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\PhilUploadScanBundle;
use Phil\Url\Url;
use Phil\UrlBundle\PhilUrlBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The profile has no code: what it promises is that its members install together and that an application boots with
 * every bundle they bring.
 */
#[CoversNothing]
final class ProfileTest extends KernelTestCase
{
    /** The bundles the members bring, those of the profile's own requires and those they require in turn. */
    private const array BUNDLES = [
        PhilUrlBundle::class,
        PhilRateLimitBundle::class,
        PhilBotDetectionBundle::class,
        PhilSpamProtectionBundle::class,
        PhilUploadScanBundle::class,
    ];

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheApplicationBootsWithEveryBundleOfTheMembers(): void
    {
        $kernel = self::bootKernel();

        // A kernel names each bundle after its class, whatever object stands for it.
        foreach (self::BUNDLES as $bundle) {
            self::assertArrayHasKey(new ReflectionClass($bundle)->getShortName(), $kernel->getBundles(), $bundle.' is registered.');
        }

        self::assertTrue(self::getContainer()->has('kernel'));
    }

    public function testEveryBundleRequiredByTheProfileIsRegistered(): void
    {
        foreach ($this->members() as $package) {
            if (str_ends_with($package, '-bundle')) {
                self::assertContains($this->bundleClass($package), self::BUNDLES, $package.' brings a bundle the application registers.');
            }
        }
    }

    public function testTheLibrariesOfTheMembersAreInstalled(): void
    {
        foreach ([SafeHttpClient::class, Url::class, StoreLocality::class, TokenCodec::class, ScanResult::class] as $class) {
            self::assertFileExists((string) new ReflectionClass($class)->getFileName(), $class.' is installed.');
        }
    }

    public function testEveryMemberIsAPhilPackageAtAPublishedMinor(): void
    {
        $members = $this->members();

        self::assertSame(['phil/rate-limit-bundle', 'phil/safe-http', 'phil/spam-protection-bundle', 'phil/upload-scan-bundle', 'phil/url-bundle'], $members);

        foreach ($this->constraints() as $package => $constraint) {
            self::assertMatchesRegularExpression('/^\^0\.\d+$/', $constraint, $package.' follows its latest published minor, without a stability flag.');
        }
    }

    /**
     * The `phil/*` packages the profile requires, sorted.
     *
     * @return list<string>
     */
    private function members(): array
    {
        $members = array_keys($this->constraints());
        sort($members);

        return $members;
    }

    /**
     * @return array<string, string>
     */
    private function constraints(): array
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('metapackage', $composer['type'] ?? null);
        $require = $composer['require'] ?? null;
        self::assertIsArray($require);
        $constraints = [];

        foreach ($require as $package => $constraint) {
            if (\is_string($package) && \is_string($constraint) && str_starts_with($package, 'phil/')) {
                $constraints[$package] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * The class of a bundle from its package name: `phil/spam-protection-bundle` registers
     * `Phil\SpamProtectionBundle\PhilSpamProtectionBundle`.
     */
    private function bundleClass(string $package): string
    {
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', substr($package, \strlen('phil/'), -\strlen('-bundle')))));

        return \sprintf('Phil\\%sBundle\\Phil%sBundle', $name, $name);
    }
}
