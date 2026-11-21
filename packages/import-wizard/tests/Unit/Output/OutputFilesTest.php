<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Output;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Output\RejectedRowsFile;
use Phil\ImportWizard\Output\TemplateFile;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Tests\Support\MemoryTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RejectedRowsFile::class)]
#[CoversClass(TemplateFile::class)]
final class OutputFilesTest extends TestCase
{
    public function testTheTemplateHoldsTheHeadersOfTheProfile(): void
    {
        self::assertSame("\u{FEFF}code,name,parent_code\r\n", TemplateFile::csv(LmsProfiles::organisationalUnits()));
        self::assertSame("email;given_name;family_name;external_id;locale;unit_code\r\n", TemplateFile::csv(LmsProfiles::people(), new CsvDialect(';', byteOrderMark: false)));
        self::assertSame('enrolment-template.csv', TemplateFile::filename(LmsProfiles::enrolments()));
    }

    public function testATemplateReadsBackAsAFileWithoutRows(): void
    {
        foreach (LmsProfiles::all() as $profile) {
            $plan = new ImportPlanner()->plan(CsvRowSource::fromString(TemplateFile::csv($profile)), $profile, new MemoryTarget($profile->type));

            self::assertSame([], $plan->rows);
            self::assertCount(\count($profile->columns), $plan->mapping->toArray());
        }
    }

    public function testTheRejectedRowsFileGivesEachRowItsLineAndErrors(): void
    {
        $csv = "Email,First name,Last name\nana@example.org,Ana,Lee\nnope,,=cmd()\nbo@example.org,Bo\nana@example.org,Ana,Lee\n";
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString($csv), LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame(
            "\u{FEFF}import_line,import_errors,Email,First name,Last name\r\n"
            ."3,Email: is not an email address | First name: is required,nope,,'=cmd()\r\n"
            ."4,Last name: is required,bo@example.org,Bo,\r\n"
            ."5,Row: repeats the key of another row (line 2),ana@example.org,Ana,Lee\r\n",
            RejectedRowsFile::csv($plan),
        );
    }

    public function testTheRejectedRowsFileOfAnImportHoldsTheRefusalsOfTheTarget(): void
    {
        $target = new MemoryTarget('person', refuse: ['["ana@example.org"]' => [CellError::refusedByTarget('email', 'the account is closed')]]);
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString("Email,First name,Last name,Extra\nana@example.org,Ana,Lee\n"), LmsProfiles::people(), $target);
        $result = new Importer()->commit($plan, $target);

        self::assertSame(
            "import_line;import_errors;Email;First name;Last name;Extra\r\n2;Email: is refused — the account is closed;ana@example.org;Ana;Lee;\r\n",
            RejectedRowsFile::csv($plan, $result->rejections, new CsvDialect(';', byteOrderMark: false)),
        );
        self::assertSame('import_line', RejectedRowsFile::LINE_HEADER);
        self::assertSame('import_errors', RejectedRowsFile::ERRORS_HEADER);
    }

    public function testTheCorrectedFileImportsAgainWithTheSameProfile(): void
    {
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString("Email,First name,Last name\nana@example.org,,=Lee\n"), LmsProfiles::people(), new MemoryTarget('person'));
        $rejected = RejectedRowsFile::csv($plan);
        $corrected = str_replace(',,', ',Ana,', $rejected);
        $again = new ImportPlanner()->plan(CsvRowSource::fromString($corrected), LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([], $again->rejections);
        self::assertSame(2, $again->rows[0]->line);
        self::assertSame('=Lee', $again->rows[0]->value('family_name'));
    }
}
