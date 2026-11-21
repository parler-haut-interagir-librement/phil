<?php

declare(strict_types=1);

/*
 * One process of the concurrency tests: it waits until the barrier file exists, for ten seconds at most, then admits
 * one task on the quota of a Doctrine DBAL usage store, and prints "admitted" or "refused <meter>".
 *
 * Arguments: the database URL, the meter (ai_tokens, ai_budget or ai_ceiling), the subject, the task's identifier and
 * the barrier file.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Tests\Integration\ConcurrentAdmissionTestCase;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\UsageMeter;
use Symfony\Component\Clock\NativeClock;

foreach ([dirname(__DIR__, 3).'/vendor/autoload.php', dirname(__DIR__, 5).'/vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        break;
    }
}

$arguments = $_SERVER['argv'] ?? [];
[, $url, $meter, $subject, $taskId, $barrier] = array_pad(array_values(array_filter(is_array($arguments) ? $arguments : [], is_string(...))), 6, '');
$connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url));
$usage = new UsageMeter(ConcurrentAdmissionTestCase::meters(), new DbalUsageStore($connection), new NativeClock());
$quota = match ($meter) {
    'ai_tokens' => new Quota($usage, 'ai_tokens'),
    'ai_budget' => new Quota($usage, budgetMeter: 'ai_budget'),
    default => new Quota($usage, ceilingMeter: 'ai_ceiling', ceilingSubject: ConcurrentAdmissionTestCase::CEILING_SUBJECT),
};

for ($wait = 0; $wait < 10_000 && !is_file($barrier); ++$wait) {
    usleep(1_000);
}

try {
    $quota->admit(Subject::fromString($subject), KeySource::Application, ConcurrentAdmissionTestCase::ROOM, $taskId, new Cost(ConcurrentAdmissionTestCase::ROOM, 'USD'));
    echo 'admitted';
} catch (QuotaExceeded $quotaExceeded) {
    echo 'refused '.$quotaExceeded->usage->meter;
} finally {
    $connection->close();
}
