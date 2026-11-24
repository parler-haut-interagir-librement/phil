<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        // The tests of the Doctrine repositories, on SQLite with a coverage driver, take longer than Infection's ten
        // seconds: their mutants would be left unjudged.
        'timeout' => 30,
        'mutators' => [
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    // A numeric SKU of the configuration is an integer key, and PHP turns the string back into it when
                    // the issue order of the SKU is read: the cast serves PHPStan's array<string, IssueOrder>.
                    '.*\$orders\[\(string\) \$sku\] = IssueOrder::from\(\$order\);.*',
                    // A memory stream always reads back what was written: the cast only drops the false of the signature.
                    '.*\$csv = \(string\) stream_get_contents\(\$stream\);.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // One entry or one layer past the bound tells a statement that is too long; two tell it as well.
                    '.*ConsignorStatement::MAX_(LINES|HELD) \+ 1\);.*',
                    // Terms without a commission have a share, and their margin returns before the rate is read.
                    '.*\$commission = \$terms->commissionPpm \?\? 0;.*',
                    // The Doctrine lists are cut to their limit after the rows not flushed yet are added, and the series
                    // takes the highest number of what it reads: the query's own limit only spares rows.
                    '.*->setMaxResults\(max\(1, \$limit\)\).*',
                    '.*->setMaxResults\(1\).*',
                ],
            ],
            // A lookup of the Doctrine repository loads rows into the identity map, then keeps those it asks for: a
            // query without one of its criteria loads more rows, and keeps the same ones.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*->findBy\(\[.origin. => \$origin\]\);.*',
                    '.*->findBy\(\[.reference. => \$reference, .sku. => \$sku\]\);.*',
                ],
            ],
            // The sums of SQLite are integers already; PostgreSQL and MySQL give numeric strings, which the casts make
            // the integers of the signatures.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$held\[\$row\[.layer.\]\] = \(int\) \$row\[.units.\];.*',
                    '.*\$(owed|paid) = \(int\) \$this->entityManager->createQueryBuilder\(\).*',
                ],
            ],
            // SQLite groups the rows in the order of the layers' identifiers; the sort is for the other platforms.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*ksort\(\$held, \\\\SORT_STRING\);.*'],
            ],
            // A statement number holds no line break: the D flag only guards a series an application fills itself.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\(./\^\\\\d\{8\}\$/D., .*'],
            ],
            // No unit missing at a location writes no sale: the units to write at each price are then all zero, and
            // none is written.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*if \(\$missing <= 0\) \{.*'],
            ],
            // The versions of a consignor's agreement are kept for the listing: read again, they are the same.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\$versions\[\$owner->key\(\)\] \?\?= \$this->consignors->agreementsOf\(\$owner->key\(\)\);.*'],
            ],
            // An entry holds the currency, the unit price, the revenue and the cost together, or none of them.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return null === \$this->unitPrice \|\| null === \$this->currency \? null.*',
                    '.*if \(null === \$this->revenue \|\| null === \$this->cost \|\| null === \$this->currency\) \{.*',
                ],
            ],
            // A receipt line is bought, with a unit cost and no terms, or consigned, with terms and no unit cost: one
            // side of the coalesce is always null.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$money = \$line->unitCost \?\? \$line->terms\?->share;.*',
                    // The logistics module keeps one reservation per order, SKU and location: two never share a key.
                    '.*\(\$shipped\[\$key\]->quantity \?\? 0\) \+ \$quantity\);.*',
                ],
            ],
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\$money = \$line->unitCost \?\? \$line->terms\?->share;.*'],
            ],
            // The key of a part to return names its receipt's layer, an identifier of fixed length, then its price:
            // any order or separator of the three keeps the parts apart, and two currencies never price one layer.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*\$key = \$layer->origin\.\' \'\.\$unitPrice->currency->code\.\' \'\.\$unitPrice->minorUnits;.*'],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\$key = \$layer->origin\.\' \'\.\$unitPrice->currency->code\.\' \'\.\$unitPrice->minorUnits;.*'],
            ],
            // The parts to return and the entities not flushed yet are read with foreach: the renumbering only serves
            // PHPStan's list.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return array_values\(array_reverse\(array_filter\(\$parts, .*',
                    '.*return array_values\(array_filter\(\$this->entityManager->getUnitOfWork\(\)->getScheduledEntityInsertions\(\), .*',
                ],
            ],
            // The numbers read from the statements' table are strings: the check of the type only serves PHPStan.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['.*if \(\\\\is_string\(\$number\) && 1 === preg_match\(.*'],
            ],
            // Terms without a commission have a share, and their margin returns before the rate is read.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\$commission = \$terms->commissionPpm \?\? 0;.*'],
            ],
        ],
    ],
];
