<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Benchmark;

use Generator;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddress\TopLevelDomains;

/**
 * Every form that takes an address validates it, and an import validates thousands: the syntax, the top-level domain,
 * the lists and the suggestion, without the DNS, must cost a few microseconds, and the longest domain far from every known one no more
 * than a known one.
 *
 * @BeforeMethods("setUp")
 */
final class EmailValidatorBench
{
    private readonly EmailValidator $validator;

    private string $address = '';

    public function __construct()
    {
        $disposable = [];

        for ($i = 0; $i < 5_000; ++$i) {
            $disposable[] = 'throwaway'.$i.'.example';
        }

        // About as many top-level domains as the root zone delegates.
        $topLevel = ['com', 'example'];

        for ($i = 0; $i < 1_500; ++$i) {
            $topLevel[] = 'x'.\chr(97 + intdiv($i, 26 * 26) % 26).\chr(97 + intdiv($i, 26) % 26).\chr(97 + $i % 26);
        }

        $this->validator = new EmailValidator(
            disposable: DisposableDomains::of($disposable),
            resolver: new StaticResolver(otherwise: MailDomain::Mx),
            topLevelDomains: TopLevelDomains::of($topLevel),
        );
    }

    /**
     * @param array{address: string} $params
     */
    public function setUp(array $params): void
    {
        $this->address = $params['address'];
    }

    /**
     * @ParamProviders("addresses")
     *
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchValidate(): void
    {
        $this->validator->validate($this->address);
    }

    /**
     * @return Generator<string, array{address: string}>
     */
    public function addresses(): Generator
    {
        yield 'known domain' => ['address' => 'ada@gmail.com'];
        yield 'slip' => ['address' => 'ada@gmial.com'];
        yield 'disposable subdomain' => ['address' => 'ada@a.b.throwaway4999.example'];
        yield 'international domain' => ['address' => 'ada@bücher.example'];
        yield 'longest domain' => ['address' => 'a@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.'.str_repeat('e', 57).'.com'];
        yield 'syntax error' => ['address' => str_repeat('a', 300)];
        yield 'unknown top-level domain' => ['address' => 'ada@example.invalid'];
    }
}
