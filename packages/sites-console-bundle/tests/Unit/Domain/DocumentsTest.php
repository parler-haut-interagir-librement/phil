<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\CheckState;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
#[CoversClass(Fields::class)]
#[CoversClass(MalformedDocument::class)]
#[CoversClass(StatusDocument::class)]
#[CoversClass(HealthSummary::class)]
#[CoversClass(CheckState::class)]
#[CoversClass(Settings::class)]
#[CoversClass(ConfigurationRequest::class)]
#[CoversClass(UpdateRequest::class)]
#[CoversClass(Outcome::class)]
final class DocumentsTest extends TestCase
{
    public function testAStatusReadsBackWhatItWrites(): void
    {
        $status = Fixtures::status('shop', ['symfony/http-kernel' => 'v8.2.0', 'phil/url' => '0.1.4']);
        $json = Json::encode($status->toArray());

        self::assertSame('{"site":"shop","generated_at":"2026-11-20T10:00:00+00:00","php":"8.5.1","symfony":"8.2.0","packages":{"symfony/http-kernel":"v8.2.0","phil/url":"0.1.4"},"health":{"status":"up","checks":[{"name":"database","status":"up","summary":"ok"}]}}', $json);
        $read = StatusDocument::fromJson($json);
        self::assertSame(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0'], $read->packages);
        self::assertSame('2026-11-20T10:00:00+00:00', $read->generatedAt->format(\DATE_ATOM));
        self::assertSame('8.5.1', $read->php);
        self::assertSame('8.2.0', $read->symfony);
        self::assertSame('shop', $read->site);
        self::assertEquals($status->health, $read->health);
    }

    public function testAStatusWithoutHealthOrPackages(): void
    {
        $read = StatusDocument::fromJson('{"site":"shop","generated_at":"2026-11-20T10:00:00+00:00","php":"8.5.1","symfony":"8.2.0","packages":[]}');

        self::assertSame([], $read->packages);
        self::assertSame(HealthState::Unknown, $read->health->state);
        self::assertSame([], $read->health->checks);
        self::assertSame([], StatusDocument::fromArray(['site' => 'a', 'generated_at' => '2026-11-20T10:00:00+00:00', 'php' => '8', 'symfony' => '8', 'health' => ['status' => 'down']])->packages);
    }

    #[DataProvider('malformedStatuses')]
    public function testAMalformedStatusIsRefused(string $json, string $reason): void
    {
        $this->expectExceptionObject(MalformedDocument::because($reason));
        StatusDocument::fromJson($json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedStatuses(): iterable
    {
        $base = ['site' => 'shop', 'generated_at' => '2026-11-20T10:00:00+00:00', 'php' => '8.5.1', 'symfony' => '8.2.0', 'packages' => []];
        $with = static fn (array $changes): string => json_encode(array_merge($base, $changes), \JSON_THROW_ON_ERROR);

        yield 'not json' => ['{', 'The document is not JSON within the allowed depth.'];
        yield 'too deep' => [str_repeat('[', 9).str_repeat(']', 9), 'The document is not JSON within the allowed depth.'];
        yield 'a list' => ['[1]', '"document" is an object.'];
        yield 'a scalar' => ['1', '"document" is an object.'];
        yield 'no site' => [$with(['site' => null]), '"site" is a text of 1 to 64 characters without control characters.'];
        yield 'empty site' => [$with(['site' => '']), '"site" is a text of 1 to 64 characters without control characters.'];
        yield 'long site' => [$with(['site' => str_repeat('a', 65)]), '"site" is a text of 1 to 64 characters without control characters.'];
        yield 'control in site' => [$with(['site' => "a\x7f"]), '"site" is a text of 1 to 64 characters without control characters.'];
        yield 'bad date' => [$with(['generated_at' => '2026-11-20 10:00']), '"generated_at" is a date in the ATOM format.'];
        yield 'bad php' => [$with(['php' => '8 5']), '"php" is a version.'];
        yield 'bad symfony' => [$with(['symfony' => '.8']), '"symfony" is a version.'];
        yield 'packages list' => [$with(['packages' => ['a']]), '"packages" is an object.'];
        yield 'bad package name' => [$with(['packages' => ['Phil/Url' => '1']]), '"packages" maps package names to versions.'];
        yield 'bad package version' => [$with(['packages' => ['phil/url' => '1 0']]), '"packages" maps package names to versions.'];
        yield 'digits as a name' => [$with(['packages' => ['5' => '1.0']]), '"packages" maps package names to versions.'];
        yield 'number version' => [$with(['packages' => ['phil/url' => 1]]), '"packages" maps package names to versions.'];
        yield 'health list' => [$with(['health' => [1]]), '"health" is an object.'];
        yield 'bad health' => [$with(['health' => ['status' => 'fine']]), '"status" is up, degraded, down or unknown.'];
        yield 'checks object' => [$with(['health' => ['status' => 'up', 'checks' => ['a' => 1]]]), '"checks" is a list.'];
        yield 'bad check' => [$with(['health' => ['status' => 'up', 'checks' => [['name' => 'db', 'status' => 'fine']]]]), '"status" is up, degraded, down or unknown.'];
        yield 'check name' => [$with(['health' => ['status' => 'up', 'checks' => [['name' => str_repeat('a', 101), 'status' => 'up']]]]), '"name" is a text of 1 to 100 characters without control characters.'];
        yield 'too many checks' => [$with(['health' => ['status' => 'up', 'checks' => array_fill(0, 101, ['name' => 'a', 'status' => 'up'])]]), '"checks" holds at most 100 items.'];
    }

    public function testAStatusListsAtMostTwoThousandPackages(): void
    {
        $packages = [];

        for ($i = 0; $i < 2001; ++$i) {
            $packages['vendor/p'.$i] = '1.0';
        }

        $array = Fixtures::status()->toArray();
        $array['packages'] = \array_slice($packages, 0, 2000);
        self::assertCount(2000, StatusDocument::fromArray($array)->packages);

        $array['packages'] = $packages;
        $this->expectExceptionObject(MalformedDocument::because('"packages" holds at most 2000 packages.'));
        StatusDocument::fromArray($array);
    }

    public function testADocumentHasASizeLimit(): void
    {
        self::assertSame(['a' => 'b'], Json::decodeObject('{"a":"b"}', 9));
        self::assertSame([], Json::decodeObject('{}'));

        $this->expectExceptionObject(MalformedDocument::because('The document is larger than 8 bytes.'));
        Json::decodeObject('{"a":"b"}', 8);
    }

    public function testJsonIsWrittenWithoutEscapes(): void
    {
        self::assertSame('{"a":"é/b","n":1.0}', Json::encode(['a' => 'é/b', 'n' => 1.0]));
        self::assertSame('', Fields::optionalString(['a' => ''], 'a', 3));
        self::assertSame('', Fields::optionalString([], 'a', 3));
        self::assertSame('abc', Fields::optionalString(['a' => 'abc'], 'a', 3));
        self::assertSame('ééé', Fields::string(['a' => 'ééé'], 'a', 3));
        self::assertSame([1, 2], Fields::list([1, 2], 'l', 2));
    }

    public function testACheckSummaryIsCut(): void
    {
        $check = new CheckState('db', HealthState::Up, str_repeat('é', 201));

        self::assertSame(200, mb_strlen($check->summary));
        self::assertSame(['name' => 'db', 'status' => 'up', 'summary' => str_repeat('é', 200)], $check->toArray());
        self::assertSame('', CheckState::fromArray(['name' => 'db', 'status' => 'down'])->summary);
    }

    public function testSettingsAreCheckedAndSorted(): void
    {
        $settings = Settings::of(['mail.footer' => 'Hello', 'a-b_c.0' => null, 'flag' => true, 'n' => 1, 'f' => 1.5, 'long' => str_repeat('é', 1000)]);

        self::assertSame(['a-b_c.0', 'f', 'flag', 'long', 'mail.footer', 'n'], $settings->keys());
        self::assertSame('Hello', $settings->values['mail.footer']);
        self::assertCount(50, Settings::of(array_fill_keys(array_map(static fn (int $i): string => 'k'.$i, range(1, 50)), 1))->values);
    }

    #[DataProvider('badSettings')]
    public function testBadSettingsAreRefused(mixed $values, string $reason): void
    {
        $this->expectExceptionObject(InvalidValue::because($reason));
        Settings::of($values);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badSettings(): iterable
    {
        yield 'list' => [['a', 'b'], 'The settings are an object of keys and values.'];
        yield 'scalar' => ['a', 'The settings are an object of keys and values.'];
        yield 'empty' => [[], 'A push sends 1 to 50 settings.'];
        yield 'too many' => [array_fill_keys(array_map(static fn (int $i): string => 'k'.$i, range(1, 51)), 1), 'A push sends 1 to 50 settings.'];
        yield 'uppercase key' => [['Mail' => 1], 'The key "Mail" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.'];
        yield 'leading dot' => [['.mail' => 1], 'The key ".mail" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.'];
        yield 'long key' => [[str_repeat('a', 101) => 1], 'The key "'.str_repeat('a', 100).'" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.'];
        yield 'trailing newline' => [["mail\n" => 1], "The key \"mail\n\" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone."];
        yield 'digits alone' => [['5' => 1], 'The key "5" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.'];
        yield 'array value' => [['mail' => ['a']], 'The value of "mail" is a text of at most 1000 characters, a number, a boolean or null.'];
        yield 'long value' => [['mail' => str_repeat('a', 1001)], 'The value of "mail" is a text of at most 1000 characters, a number, a boolean or null.'];
    }

    public function testRequestsReadBackWhatTheyWrite(): void
    {
        $configuration = new ConfigurationRequest('01J', Settings::of(['b' => 2, 'a' => 'x']));
        self::assertSame('{"id":"01J","settings":{"a":"x","b":2}}', $configuration->toJson());
        self::assertEquals($configuration, ConfigurationRequest::fromJson($configuration->toJson()));

        $update = new UpdateRequest('01J:shop', 'phil/url', '0.1.5');
        self::assertSame('{"id":"01J:shop","package":"phil/url","version":"0.1.5"}', $update->toJson());
        self::assertEquals($update, UpdateRequest::fromJson($update->toJson()));
    }

    public function testRequestsKeepTheirLimits(): void
    {
        try {
            ConfigurationRequest::fromJson('{"id":"x","settings":{}}');
            self::fail('Empty settings are refused.');
        } catch (MalformedDocument $malformedDocument) {
            self::assertSame('A push sends 1 to 50 settings.', $malformedDocument->getMessage());
        }

        try {
            UpdateRequest::fromJson('{"id":"x","package":"Phil","version":"1"}');
            self::fail('A bad package is refused.');
        } catch (MalformedDocument $malformedDocument) {
            self::assertSame('"package" is a package name and "version" a version.', $malformedDocument->getMessage());
        }

        try {
            UpdateRequest::fromJson('{"id":"x","package":"phil/url","version":"1 0"}');
            self::fail('A bad version is refused.');
        } catch (MalformedDocument $malformedDocument) {
            self::assertSame('"package" is a package name and "version" a version.', $malformedDocument->getMessage());
        }

        $this->expectExceptionObject(MalformedDocument::because('The document is larger than 10 bytes.'));
        UpdateRequest::fromJson('{"id":"x","package":"phil/url","version":"1"}', 10);
    }

    public function testAConfigurationRequestHasASizeLimit(): void
    {
        $this->expectExceptionObject(MalformedDocument::because('The document is larger than 10 bytes.'));
        ConfigurationRequest::fromJson('{"id":"x","settings":{"a":1}}', 10);
    }

    public function testOutcomes(): void
    {
        self::assertSame('{"outcome":"succeeded","replayed":false,"message":"done"}', Outcome::succeeded('done')->toJson());
        self::assertSame('{"outcome":"failed","replayed":true,"message":"a b"}', Outcome::failed(" a\nb ")->replay()->toJson());
        self::assertSame('', Outcome::succeeded()->message);
        self::assertSame(500, mb_strlen(Outcome::failed(str_repeat('é', 501))->message));

        $read = Outcome::fromJson('{"outcome":"failed","replayed":true,"message":"no"}');
        self::assertFalse($read->succeeded);
        self::assertTrue($read->replayed);
        self::assertSame('no', $read->message);

        $plain = Outcome::fromJson('{"outcome":"succeeded"}');
        self::assertTrue($plain->succeeded);
        self::assertFalse($plain->replayed);
        self::assertSame('', $plain->message);
    }

    #[DataProvider('badOutcomes')]
    public function testBadOutcomesAreRefused(string $json): void
    {
        $this->expectExceptionObject(MalformedDocument::because('"outcome" is succeeded or failed, and "replayed" a boolean.'));
        Outcome::fromJson($json);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOutcomes(): iterable
    {
        yield 'unknown outcome' => ['{"outcome":"maybe"}'];
        yield 'replayed text' => ['{"outcome":"failed","replayed":"yes"}'];
    }
}
