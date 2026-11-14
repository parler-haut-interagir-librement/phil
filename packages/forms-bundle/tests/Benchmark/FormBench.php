<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Benchmark;

use Generator;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;

/**
 * `Form::fields()` rebuilds and revalidates every field from its JSON on each call, and one submission calls it about
 * five times: twice to build the Symfony form, once in `accept()`, twice in the email notification.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"forms"})
 */
final class FormBench
{
    private Form $form;

    /**
     * @var array<string, string|bool>
     */
    private array $submitted;

    public function __construct()
    {
        $this->form = Forms::registration();
        $this->submitted = Forms::values();
    }

    /**
     * The tests' registration form, or a longer one: the registration's seven fields repeated, the choice fields
     * offering forty choices as a country or event list does.
     *
     * @param array{fields: int} $params
     */
    public function setUp(array $params): void
    {
        if (7 === $params['fields']) {
            return;
        }

        $this->submitted = [];
        $choices = array_map(static fn (int $i): string => \sprintf('Choice %d', $i), range(1, 40));
        $fields = [];

        for ($i = 0; \count($fields) < $params['fields']; ++$i) {
            $fields[] = Field::of('name_'.$i, 'Name', FieldType::Text, true);
            $fields[] = Field::of('email_'.$i, 'Email', FieldType::Email, true);
            $fields[] = Field::of('site_'.$i, 'Site', FieldType::Url);
            $fields[] = Field::of('born_'.$i, 'Born on', FieldType::Date);
            $fields[] = Field::of('team_'.$i, 'Team', FieldType::Choice, choices: $choices);
            $fields[] = Field::of('message_'.$i, 'Message', FieldType::Textarea);
            $fields[] = Field::of('consent_'.$i, 'I agree', FieldType::Checkbox, true);

            $this->submitted += ['name_'.$i => 'Ada', 'email_'.$i => 'ada@example.com', 'site_'.$i => 'https://ada.example.com', 'born_'.$i => '1990-12-10', 'team_'.$i => 'Choice 33', 'message_'.$i => "Hello,\nsee you soon.", 'consent_'.$i => true];
        }

        $this->form = Form::create('long-form', 'Long form', \array_slice($fields, 0, $params['fields']));
    }

    /**
     * @ParamProviders("forms")
     */
    public function benchFields(): void
    {
        $this->form->fields();
    }

    /**
     * @ParamProviders("forms")
     */
    public function benchAccept(): void
    {
        $this->form->accept($this->submitted);
    }

    /**
     * The calls of one submission: the Symfony form built (two reads of the fields), the entry accepted, the email
     * notification rendered (two reads).
     *
     * @ParamProviders("forms")
     */
    public function benchSubmission(): void
    {
        $this->form->fields();
        $this->form->fields();
        $this->form->accept($this->submitted);
        $this->form->fields();
        $this->form->fields();
    }

    /**
     * @return Generator<string, array{fields: int}>
     */
    public function forms(): Generator
    {
        yield '7 fields' => ['fields' => 7];
        yield '28 fields' => ['fields' => 28];
        yield '105 fields' => ['fields' => 105];
    }
}
