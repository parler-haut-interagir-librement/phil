<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Benchmark;

use Generator;
use Phil\Passwords\Strength\StrengthEstimator;

/**
 * Every keystroke of a strength meter and every form that sets a password runs the estimator: ordinary passwords,
 * passphrases, and the hostile inputs that make the cover search work hardest — dense matches on every position,
 * digits read as dates everywhere — at the bound of 128 characters.
 */
final readonly class StrengthEstimatorBench
{
    private StrengthEstimator $estimator;

    public function __construct()
    {
        $this->estimator = new StrengthEstimator(referenceYear: 2026);
    }

    /**
     * @ParamProviders("passwords")
     *
     * @param array{password: string} $params
     */
    public function benchEstimate(array $params): void
    {
        $this->estimator->estimate($params['password'], ['jean.dupont@example.com', 'Jean Dupont']);
    }

    public function benchBuild(): void
    {
        new StrengthEstimator(referenceYear: 2026);
    }

    /**
     * @return Generator<string, array{password: string}>
     */
    public function passwords(): Generator
    {
        yield 'common, 8' => ['password' => 'P@ssw0rd'];
        yield 'random, 16' => ['password' => 'x7#Kq!2mZp9$Lw4e'];
        yield 'passphrase, 30' => ['password' => 'dragon-canard-chuchoter-ecrire'];
        yield 'repeated letter, 128' => ['password' => str_repeat('a', 128)];
        yield 'repeated common password, 128' => ['password' => str_repeat('password', 16)];
        yield 'digits read as dates, 128' => ['password' => str_repeat('19870314', 16)];
        yield 'hexadecimal, 128' => ['password' => str_repeat('a3df50c96365e1b7', 8)];
    }
}
