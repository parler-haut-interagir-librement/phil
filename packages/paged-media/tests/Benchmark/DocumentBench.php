<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Benchmark;

use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\CheckBox;
use Phil\PagedMedia\Pdf\Form\ChoiceField;
use Phil\PagedMedia\Pdf\Form\TextField;

/**
 * A receipt is written for every sale and a fillable sheet for every download: rendering three pages of text, with or
 * without a hundred and twenty fields, must stay well under a few milliseconds, and the fields must not slow down a
 * document that has none.
 */
final readonly class DocumentBench
{
    private Document $receipt;

    private Document $form;

    public function __construct()
    {
        $this->receipt = $this->pages();
        $this->form = $this->pages();

        for ($i = 0; $i < 40; ++$i) {
            $this->form->field(new TextField('name'.$i, 36, 750 - $i * 18, 200, 14, 'Value '.$i));
            $this->form->field(new CheckBox('check'.$i, 250, 750 - $i * 18, 12, 0 === $i % 2));
            $this->form->field(new ChoiceField('choice'.$i, 280, 750 - $i * 18, 120, 14, ['a' => 'First', 'b' => 'Second', 'c' => 'Third'], 'b'));
        }
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function benchReceipt(): void
    {
        $this->receipt->render();
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function benchInteractiveForm(): void
    {
        $this->form->render();
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function benchFlattenedForm(): void
    {
        $this->form->render(flattenForm: true);
    }

    private function pages(): Document
    {
        $document = new Document();

        for ($page = 0; $page < 3; ++$page) {
            $document->addPage();

            for ($line = 0; $line < 60; ++$line) {
                $document->text(36, 750 - $line * 12, 'Line '.$line.' of a receipt — 12,50 $', 10);
                $document->line(36, 745 - $line * 12, 500, 745 - $line * 12);
            }
        }

        return $document;
    }
}
