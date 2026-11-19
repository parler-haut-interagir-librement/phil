<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // Maps that only ever answer isset(): the value kept under a key is never read, so `false` for `true`
            // changes nothing a caller can see.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(kept|closed|done|dead|seated|members|forbidden)(\\[[^=]*\\])* = true;',
                    '\\$onPath(\\[\\$next\\])? = (true|\\[\\$source => true\\]);',
                ],
            ],
            // The network is built around its own numbering and costs: a spare node nobody links, an edge that no
            // other edge lets carry more than one unit, a cost added to every path alike, the rank counter of an
            // empty problem, a capacity-zero edge or a node key below the first all leave every flow and cost as is;
            // and the starting potentials of the nodes the warm start leaves out shift every source-sink path alike
            // (checked on 1500 random problems against Bellman–Ford).
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$sink = \\$count \\+ \\\\count\\(\\$slots\\) \\+ 1;',
                    '\\$flow = new MinCostFlow\\(\\$sink \\+ 1\\);',
                    '\\$sourceEdges\\[\\$index\\] = \\$flow->addEdge\\(0, \\$index \\+ 1, 1, 0\\);',
                    '\\$choiceEdges\\[\\$flow->addEdge\\(\\$index \\+ 1, .*',
                    '\\$sinkEdges\\[\\$slot\\] = \\$flow->addEdge\\(.*',
                    '\\$potential \\+= array_fill\\(0, \\$this->nodes, 0\\);',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$ranks = 0;',
                    '\\$this->edges = array_fill\\(0, \\$nodes, \\[\\]\\);',
                ],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$draft->seats\\[\\$slot\\] > 0\\) \\{'],
            ],
            // A node reached again at the same distance is queued twice and visited once.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$candidate < \\$distance\\[\\$next\\]\\) \\{'],
            ],
            // `array_intersect` keeps the keys of the waiting list, which the next round only walks in order.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['\\$waiting = array_values\\(array_intersect\\(\\$waiting, \\$next\\)\\);'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of problems and the four assigners on what it reads, from the seeds of tests/Fuzz/corpus/problem.
        'targets' => [
            'problem' => 'tests/Fuzz/problem.php',
        ],
    ],
];
