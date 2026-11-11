<?php

declare(strict_types=1);

/*
 * The charts of the golden images under cases/: each name, to its size and configuration.
 */
return [
    'untitled-named-line' => [320, 240, ['type' => 'line', 'data' => ['labels' => ['20', '40'], 'datasets' => [['label' => 'Only', 'data' => [1.23456, 5]]]]]],
    'positive-bars' => [480, 300, ['type' => 'bar', 'data' => ['labels' => ['Ünï', 'b'], 'datasets' => [['label' => 'Tom\'s "x" ✓', 'data' => [5, 7], 'borderColor' => '#112233']]], 'options' => ['plugins' => ['title' => ['text' => 'Positive']]]]],
    'single-label' => [300, 200, ['type' => 'bar', 'data' => ['labels' => ['solo'], 'datasets' => [['data' => [3]]]]]],
    'pie-with-a-zero' => [300, 300, ['type' => 'pie', 'data' => ['labels' => ['a', 'b', 'c', 'd'], 'datasets' => [['data' => [3, 0, 2, 2], 'backgroundColor' => ['#aa0000', '#00aa00', '#0000aa', '#aaaa00']]]]]],
    'pie-halves' => [300, 300, ['type' => 'pie', 'data' => ['labels' => ['a', 'b'], 'datasets' => [['data' => [1, 1]]]]]],
    'pie-whole' => [300, 300, ['type' => 'pie', 'data' => ['labels' => ['all'], 'datasets' => [['data' => [5]]]]]],
    'doughnut-whole' => [300, 300, ['type' => 'doughnut', 'data' => ['labels' => ['all'], 'datasets' => [['data' => [5]]]]]],
    'narrow-pie' => [200, 400, ['type' => 'doughnut', 'data' => ['labels' => ['a', 'b', 'c'], 'datasets' => [['data' => [1, 2, 3]]]]]],
    'negative-line-with-a-gap' => [400, 300, ['type' => 'line', 'data' => ['labels' => ['a', 'b', 'c', 'd'], 'datasets' => [['label' => 'Low', 'data' => [-3, null, -1, -7]], ['label' => 'Lower', 'data' => [-4, -5, null, -6]]]]]],
];
