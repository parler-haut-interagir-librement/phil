<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Benchmark;

use Generator;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;

/**
 * The denylist is asked about every context and extra key of every log record, and about every long option name of
 * a logged command line: it normalises the key, then scans the prefix, suffix and fragment patterns.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"privacy", "log-redaction"})
 */
final class DenylistBench
{
    /**
     * @var array<string, Denylist>
     */
    private array $lists = [];

    public function setUp(): void
    {
        $this->lists['small'] = Denylist::of('password', 'token', 'secret', 'authorization', 'cookie');
        $this->lists['typical'] = Denylist::fromList([
            'password', 'token', 'secret', 'authorization', 'cookie', 'set-cookie', 'api_key', 'query_string',
            'iban', 'card_number', 'cvv', 'otp', 'totp_secret', 'refresh_token', 'access_token',
            '*token*', '*secret*', 'seed*',
        ]);

        $large = ['password'];

        for ($i = 0; $i < 60; ++$i) {
            $large[] = 'denied_key_'.$i;
        }

        for ($i = 0; $i < 7; ++$i) {
            $large[] = 'stem'.$i.'*';
            $large[] = '*tail'.$i;
            $large[] = '*frag'.$i.'*';
        }

        $this->lists['large'] = Denylist::fromList($large);
    }

    /**
     * @ParamProviders({"lists", "keys"})
     *
     * @param array{list: string, key: string} $params
     */
    public function benchDenies(array $params): bool
    {
        return $this->lists[$params['list']]->denies($params['key']);
    }

    /**
     * @return Generator<string, array{list: string}>
     */
    public function lists(): Generator
    {
        yield '5 exact' => ['list' => 'small'];
        yield '15 exact + 3 patterns' => ['list' => 'typical'];
        yield '61 exact + 21 patterns' => ['list' => 'large'];
    }

    /**
     * @return Generator<string, array{key: string}>
     */
    public function keys(): Generator
    {
        yield 'exact hit' => ['key' => 'password'];
        yield 'miss (every pattern scanned)' => ['key' => 'X-Forwarded-For-Client-Address'];
    }
}
