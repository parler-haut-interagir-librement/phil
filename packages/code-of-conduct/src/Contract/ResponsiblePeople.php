<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Contract;

/**
 * Who the application designates to receive and handle the reports of a scope.
 */
interface ResponsiblePeople
{
    /**
     * The identifiers of the responsible people of the scope, at most 100.
     *
     * @return list<string>
     */
    public function of(string $scope): array;
}
