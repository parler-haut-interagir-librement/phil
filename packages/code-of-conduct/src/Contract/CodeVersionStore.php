<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Contract;

use Phil\CodeOfConduct\CodeVersion;

/**
 * The published versions of the codes, by scope and number.
 */
interface CodeVersionStore
{
    /**
     * The version of the highest number of the scope, `null` when it has none.
     */
    public function current(string $scope): ?CodeVersion;

    public function version(string $scope, int $number): ?CodeVersion;

    /**
     * The number of the latest major version of the scope, `null` when it has none.
     */
    public function latestMajor(string $scope): ?int;

    /**
     * Adds a version. An implementation refuses a second version of the same scope and number, by a unique key, so
     * that two concurrent publications never both succeed.
     */
    public function add(CodeVersion $version): void;
}
