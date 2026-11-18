<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Integration;

use Override;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A statement sent to a real xAPI learning record store, read from the environment: its endpoint at
 * `PHIL_TEST_LRS_URL`, an https address on a public host, with the key and secret of HTTP Basic authentication at
 * `PHIL_TEST_LRS_USERNAME` and `PHIL_TEST_LRS_PASSWORD`, and the xAPI version at `PHIL_TEST_LRS_VERSION` (1.0.3 by
 * default). The statement is kept, then sent once more, which the store keeps as well.
 */
#[CoversClass(HttpLearningRecordStore::class)]
#[Group('lrs')]
final class RemoteLrsTest extends TestCase
{
    private HttpLearningRecordStore $store;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv('PHIL_TEST_LRS_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_LRS_URL, PHIL_TEST_LRS_USERNAME and PHIL_TEST_LRS_PASSWORD to run this test against a real store.');
        }

        $username = getenv('PHIL_TEST_LRS_USERNAME');
        $password = getenv('PHIL_TEST_LRS_PASSWORD');
        $version = getenv('PHIL_TEST_LRS_VERSION');
        $this->store = new HttpLearningRecordStore(
            new SafeHttpClient(limits: new Limits(maxRedirects: 0)),
            $url,
            new Store()->lrs,
            \is_string($username) && '' !== $username ? $username : null,
            \is_string($password) && '' !== $password ? $password : null,
            \is_string($version) && '' !== $version ? $version : '1.0.3',
        );
    }

    #[DoesNotPerformAssertions]
    public function testAStatementIsKeptOnceAndSentAgainWithoutError(): void
    {
        $id = Uuid::v4()->toRfc4122();
        $registration = Uuid::v4()->toRfc4122();
        $statement = (string) json_encode([
            'id' => $id,
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => Store::HOME, 'name' => 'phil-courses-test']],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/experienced', 'display' => ['en-US' => 'experienced']],
            'object' => ['objectType' => 'Activity', 'id' => Store::HOME.'/courses/activities/test'],
            'context' => ['registration' => $registration],
            'timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'),
        ], \JSON_UNESCAPED_SLASHES);

        $this->store->store($id, $statement, $registration);
        $this->store->store($id, $statement, $registration);
    }
}
