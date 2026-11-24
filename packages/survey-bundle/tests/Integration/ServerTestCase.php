<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Integration;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Process\Process;

/**
 * The bundle on a real server, read from the variable a subclass names: the quotas taken by respondents at the same
 * instant, each in a process of its own on a connection of its own — `respondent.php` —, never take more places than
 * they hold, a member who sends twice at once is kept once, and the keys compare byte by byte.
 */
abstract class ServerTestCase extends TestCase
{
    private const int RESPONDENTS = 12;

    private const int PLACES = 5;

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = $this->connect();
        Stage::drop($this->connection);
        Stage::create($this->connection);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->connection instanceof Connection) {
            Stage::drop($this->connection);
            $this->connection->close();
        }
    }

    public function testConcurrentRespondentsNeverTakeMorePlacesThanAQuotaHolds(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour([
            'quotas' => [
                ['name' => 'everyone', 'limit' => 100, 'when' => ['!!' => [['var' => 'colour']]], 'ending' => 'thanks'],
                ['name' => 'red', 'limit' => self::PLACES, 'when' => ['==' => [['var' => 'colour'], 'red']], 'ending' => 'thanks'],
            ],
        ]));
        $codes = $this->respond($questionnaire, array_map(static fn (int $respondent): string => 'member:'.$respondent, range(1, self::RESPONDENTS)));
        $stage = new Stage($this->connection, create: false);

        self::assertCount(self::PLACES, array_keys($codes, 0, true), implode(',', $codes));
        self::assertCount(self::RESPONDENTS - self::PLACES, array_keys($codes, 3, true), implode(',', $codes));
        self::assertSame(self::PLACES, $stage->responses->count('colour'));
        self::assertSame(['everyone' => self::PLACES, 'red' => self::PLACES], $stage->responses->quotaCounts('colour'));
        self::assertSame(self::PLACES, $stage->rows('phil_survey_participations'));
    }

    public function testConcurrentSubmissionsOfOneMemberKeepOne(): void
    {
        $codes = $this->respond(Questionnaire::fromArray(Samples::colour()), array_fill(0, 6, 'member:ann'));

        self::assertCount(1, array_keys($codes, 0, true), implode(',', $codes));
        self::assertCount(5, array_keys($codes, 4, true), implode(',', $codes));
        self::assertSame(1, new Stage($this->connection, create: false)->responses->count('colour'));
    }

    /**
     * Starts one process per member, each on its own connection, all answering "red" at the same instant.
     *
     * @param list<string> $members
     *
     * @return list<int> the exit code of each
     */
    private function respond(Questionnaire $questionnaire, array $members): array
    {
        $autoload = \dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2).'/autoload.php';
        $url = (string) getenv($this->variable());
        $start = (string) ((int) (hrtime(true) / 1000) + 1_500_000);
        $processes = [];

        foreach ($members as $member) {
            $process = new Process([\PHP_BINARY, __DIR__.'/respondent.php', $autoload, $url, QuestionnaireJson::encode($questionnaire), $member, 'red', $start]);
            $process->start();
            $processes[] = $process;
        }

        $codes = [];

        foreach ($processes as $process) {
            $code = $process->wait();
            self::assertNotSame(1, $code, $process->getErrorOutput());
            $codes[] = $code;
        }

        return $codes;
    }

    public function testTheKeysCompareByteByByte(): void
    {
        $stage = new Stage($this->connection);
        $stage->questionnaires->save(Questionnaire::fromArray(Samples::colour(['anonymity' => 'identified'])));
        $stage->submissions->submit($stage->questionnaires->get('colour'), ['colour' => 'red'], 'member:Ann');

        self::assertSame([], $stage->responses->ofRespondent('member:ann'));
        self::assertCount(1, $stage->responses->ofRespondent('member:Ann'));
        self::assertSame(0, $stage->responses->count('COLOUR'));
        self::assertSame('2026-11-21 19:30:00', $stage->responses->ofRespondent('member:Ann')[0]->submittedAt->format('Y-m-d H:i:s'));
        self::assertSame(1, $stage->responses->purgeBefore('colour', $stage->clock->now()->modify('+1 second'), 10));
    }

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
