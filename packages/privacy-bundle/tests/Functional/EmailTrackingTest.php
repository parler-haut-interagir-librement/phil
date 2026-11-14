<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Tests\Functional\App\RightsKernel;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A member's consent to tracked emails, asked on their preferences page when the application turns it on, kept in the
 * table of the bundle, answered to the sender of an email, exported and withdrawn as simply as it is given.
 */
#[CoversClass(PreferencesController::class)]
#[CoversClass(EmailTrackingConsents::class)]
#[CoversClass(DbalEmailTrackingConsents::class)]
final class EmailTrackingTest extends WebTestCase
{
    private const array FRESH = ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_X_TEST_SIGN_IN' => 'fresh'];

    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('database_connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return RightsKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new RightsKernel([...RightsKernel::CONFIGURATION, 'email_tracking' => ['enabled' => true, 'version' => '3']]);
    }

    public function testThePreferencesAskEachPurposeUntickedWithTheVersionOfTheText(): void
    {
        $crawler = $this->client->request('GET', '/privacy-choices/', [], [], self::FRESH);

        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/privacy-choices/email-tracking"]');
        self::assertCount(1, $form);
        self::assertCount(1, $form->filter('input[type=checkbox][name=opens]:not([checked])'));
        self::assertCount(1, $form->filter('input[type=checkbox][name=clicks]:not([checked])'));
        self::assertCount(1, $form->filter('label[for=phil-privacy-email-opens]'));
        self::assertStringContainsString('Version of this text: 3', $form->text());
        self::assertCount(0, $crawler->filter('p[role=status]'));
    }

    public function testAMemberAllowsOnePurposeThenWithdrawsItWithTheSameForm(): void
    {
        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token', 'opens' => '1'], [], self::FRESH);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertSame('/privacy-choices/', $this->client->getResponse()->headers->get('Location'));
        self::assertTrue($this->consents()->opens('member-1'));
        self::assertFalse($this->consents()->clicks('member-1'));

        $crawler = $this->client->request('GET', '/privacy-choices/', [], [], self::FRESH);
        self::assertCount(1, $crawler->filter('input[name=opens][checked]'));
        self::assertCount(1, $crawler->filter('input[name=clicks]:not([checked])'));
        self::assertStringContainsString('Your choice was saved on', $crawler->filter('p[role=status]')->text());

        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token'], [], self::FRESH);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertFalse($this->consents()->opens('member-1'));
        self::assertFalse($this->consents()->clicks('member-1'));
    }

    public function testTheChoiceIsPartOfTheMembersExport(): void
    {
        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token', 'clicks' => '1'], [], self::FRESH);
        $this->client->request('POST', '/privacy-choices/export', ['_token' => 'csrf-token'], [], self::FRESH);

        self::assertResponseIsSuccessful();
        $files = Unzip::files((string) $this->client->getResponse()->getContent());
        self::assertArrayHasKey('email_tracking.json', $files);
        $choice = json_decode($files['email_tracking.json'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($choice);
        self::assertSame(['version' => '3', 'opens' => false, 'clicks' => true], array_intersect_key($choice, ['version' => 0, 'opens' => 0, 'clicks' => 0]));
    }

    public function testAChoiceNeedsTheFormsTokenAndASignedInMember(): void
    {
        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token', 'opens' => '1'], [], ['HTTP_X_TEST_SIGN_IN' => 'fresh', 'HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->client->request('POST', '/privacy-choices/email-tracking', ['_token' => 'csrf-token', 'opens' => '1'], [], ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        self::assertFalse($this->consents()->opens('member-1'));
    }

    private function consents(): EmailTrackingConsents
    {
        $consents = self::getContainer()->get(EmailTrackingConsents::class);
        self::assertInstanceOf(EmailTrackingConsents::class, $consents);

        return $consents;
    }
}
