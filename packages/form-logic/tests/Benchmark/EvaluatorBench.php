<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Benchmark;

use Generator;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;

/**
 * The logic runs on each submitted answer and on each form shown: a rule is read, then evaluated. Each shape is
 * measured at the default limit of a thousand nodes and below it: a time that grows with the nodes, never with their
 * square, is the bound the reader's limits promise. The judge runs a form of a hundred conditions and computations.
 *
 * @BeforeMethods("setUp")
 */
final class EvaluatorBench
{
    private string $json = '';

    private ?Expression $expression = null;

    private ?FormLogic $logic = null;

    /** @var array<string, mixed> */
    private array $answer = [];

    /**
     * @param array{shape: string, nodes: int} $params
     */
    public function setUp(array $params): void
    {
        $this->answer = ['age' => '34', 'team' => 'Green', 'days' => ['fri', 'sun'], 'email' => 'ada@example.org'];
        $unit = match ($params['shape']) {
            // Comparisons joined by "and", the common form of a condition.
            'flat' => '{"==":[{"var":"team"},"Green"]}',
            // Operations nested in each other.
            'deep' => '{"!!":[{"!!":[{"!!":[{"!!":[true]}]}]}]}',
            // Membership in lists and texts.
            'membership' => '{"in":["fri",{"var":"days"}]}',
            default => 'true',
        };
        // The nodes of one unit: the operation and its operands; the "and" that joins them is one more.
        $count = intdiv($params['nodes'] - 1, 'deep' === $params['shape'] ? 5 : 3);
        $this->json = '{"and":['.implode(',', array_fill(0, max(1, $count), $unit)).']}';
        $this->expression = new JsonLogicReader()->read($this->json);

        $steps = [];

        for ($index = 0; $index < 50; ++$index) {
            $steps[] = ['field' => 'f'.$index, 'when' => ['>=' => [['var' => 'age'], $index]]];
            $steps[] = ['compute' => 'c'.$index, 'type' => 'number', 'value' => ['+' => [['var' => 'age'], $index]]];
        }

        $this->logic = FormLogic::fromArray($steps);
    }

    /**
     * @return Generator<string, array{shape: string, nodes: int}>
     */
    public function rules(): Generator
    {
        foreach (['flat', 'membership', 'deep'] as $shape) {
            foreach ([250, 500, 1000] as $nodes) {
                yield $shape.' '.$nodes => ['shape' => $shape, 'nodes' => $nodes];
            }
        }
    }

    /**
     * @return Generator<string, array{shape: string, nodes: int}>
     */
    public function form(): Generator
    {
        yield '100 steps' => ['shape' => 'flat', 'nodes' => 5];
    }

    /**
     * @ParamProviders("rules")
     *
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchRead(): void
    {
        new JsonLogicReader()->read($this->json);
    }

    /**
     * @ParamProviders("rules")
     *
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchEvaluate(): void
    {
        if ($this->expression instanceof Expression) {
            new Evaluator()->evaluate($this->expression, $this->answer);
        }
    }

    /**
     * @ParamProviders("form")
     *
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchJudge(): void
    {
        if ($this->logic instanceof FormLogic) {
            new Judge()->judge($this->logic, $this->answer);
        }
    }
}
