<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Functional;

use Override;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\DependencyInjection\FollowServices;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowSchemaListener;
use Phil\FollowBundle\Infrastructure\Http\FollowController;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Phil\FollowBundle\Infrastructure\Twig\FollowExtension;
use Phil\FollowBundle\Tests\Functional\App\TestKernel;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;

/**
 * The bundle in an application: its tables from the ORM's schema, its templates, and its forms posted as a browser
 * without JavaScript posts them.
 */
#[CoversClass(FollowController::class)]
#[CoversClass(FollowGuard::class)]
#[CoversClass(FollowExtension::class)]
#[CoversClass(FollowSchemaListener::class)]
#[CoversClass(FollowServices::class)]
final class FollowBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $kernel = $this->client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
    }

    public function testTheSchemaOfTheOrmHoldsTheTables(): void
    {
        $kernel = $this->client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        self::assertTrue($kernel->connection()->createSchemaManager()->tablesExist(['phil_follows', 'phil_follow_requests', 'phil_follow_mutuals', 'phil_follow_activities', 'phil_follow_deliveries', 'phil_follow_unseen', 'phil_follow_visibility']));
    }

    public function testAVisitorSeesNoForm(): void
    {
        $crawler = $this->client->request('GET', '/page');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form'));
    }

    public function testAMemberFollowsAndChangesTheLevelWithoutJavaScript(): void
    {
        $crawler = $this->page('ann');
        self::assertSame('Follow', trim($crawler->filter('#button form > button')->text()));
        self::assertCount(3, $crawler->filter('#button details li button'));
        self::assertSame('Watching', trim($crawler->filter('#button details li button')->first()->filterXPath('.//text()[normalize-space()][1]')->text()));

        $this->press($crawler->filter('#button'), 'level', 'watching');
        self::assertResponseRedirects('/page/thread:1/bob', 303);
        self::assertSame(FollowLevel::Watching, $this->level('ann', 'thread:1'));

        $crawler = $this->page('ann');
        self::assertSame('Following', trim($crawler->filter('#button form > button')->text()));
        self::assertSame('true', $crawler->filter('#button details li button[value="watching"]')->attr('aria-pressed'));
        $form = $crawler->filter('#button button[name="level"][value="custom"]')->form();
        $form['events[reply.created]'] = 'ignoring';
        $this->client->submit($form);
        self::assertSame(FollowLevel::Custom, $this->level('ann', 'thread:1'));
        self::assertSame('ignoring', $this->page('ann')->filter('#button select option[selected]')->attr('value'));

        $this->press($this->page('ann')->filter('#button'), 'level', 'none');
        self::assertNull($this->level('ann', 'thread:1'));
    }

    public function testALockedProfileTakesARequestThatItsMemberAccepts(): void
    {
        $crawler = $this->page('ann', 'member:bob');
        $this->press($crawler->filter('#button'), 'level', 'watching');
        self::assertStringContainsString('Your request waits for an answer.', $this->page('ann', 'member:bob')->filter('#button')->text());

        $requests = $this->page('bob')->filter('#requests');
        self::assertStringContainsString('Ann asks to follow you.', $requests->text());
        $this->press($requests, 'decision', 'accept');
        self::assertResponseRedirects();
        self::assertSame(FollowLevel::Watching, $this->level('ann', 'member:bob'));
        self::assertStringContainsString('No request waits for you.', $this->page('bob')->filter('#requests')->text());
    }

    public function testAHiddenListOfFollowersShowsNothingToOthers(): void
    {
        $this->follows()->follow('ann', new SubjectReference('member', 'carl'));
        self::assertStringContainsString('1 follower', $this->page('dan', 'thread:1', 'carl')->filter('#followers')->text());
        self::assertStringContainsString('Ann', $this->page('dan', 'thread:1', 'carl')->filter('#followers')->text());

        $this->press($this->page('carl')->filter('#visibility'), 'hidden', '1');
        self::assertStringContainsString('Only you see who follows you.', $this->page('carl')->filter('#visibility')->text());

        self::assertSame('', trim($this->page('dan', 'thread:1', 'carl')->filter('#followers')->text()));
        self::assertStringNotContainsString('Ann', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Ann', $this->page('carl', 'thread:1', 'carl')->filter('#followers')->text());
    }

    public function testAMutualRelationshipIsAskedAndEnded(): void
    {
        $this->press($this->page('ann', 'thread:1', 'carl')->filter('#mutual'), 'action', 'request');
        self::assertStringContainsString('Your request to connect waits for an answer.', $this->page('ann', 'thread:1', 'carl')->filter('#mutual')->text());

        $this->press($this->page('carl')->filter('#requests'), 'decision', 'accept');
        $mutuals = self::getContainer()->get(Mutuals::class);
        $this->assertInstanceOf(Mutuals::class, $mutuals);
        self::assertTrue($mutuals->areMutual('ann', 'carl'));

        $this->press($this->page('ann', 'thread:1', 'carl')->filter('#mutual'), 'action', 'end');
        self::assertFalse($mutuals->areMutual('ann', 'carl'));
        self::assertCount(0, $this->page('ann', 'thread:1', 'ann')->filter('#mutual form'), 'No relationship with oneself.');
    }

    public function testAFormThatDoesNotPassTheChecksIsStopped(): void
    {
        $form = $this->page('ann')->filter('#button form')->form();

        $this->client->request('POST', $form->getUri(), ['_token' => 'forged', 'level' => 'watching'], [], ['HTTP_X_MEMBER' => 'ann']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('[role=alert]', 'This change is not available to you.');
        self::assertSelectorExists('a[href="/page/thread:1/bob"]');

        $this->client->request('POST', '/follow/level?subject=thread:1&back=https://elsewhere.test/', ['_token' => 'forged'], [], ['HTTP_X_MEMBER' => 'ann']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorExists('a[href="/"]');

        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'level' => 'loud'], [], ['HTTP_X_MEMBER' => 'ann']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'The form holds a value the site does not know.');

        $this->client->setServerParameters([]);
        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'level' => 'watching']);
        self::assertResponseStatusCodeSame(403, 'A visitor follows nothing.');
        self::assertNull($this->level('ann', 'thread:1'));
    }

    public function testAnUnknownRequestOrDecisionIsStopped(): void
    {
        $this->follows()->follow('ann', new SubjectReference('member', 'bob'));
        $form = $this->page('bob')->filter('#requests form')->form();

        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'request' => 'other', 'decision' => 'accept'], [], ['HTTP_X_MEMBER' => 'bob']);
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('[role=alert]', 'This request no longer waits for you.');

        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'request' => $this->field($form, 'request'), 'decision' => 'maybe'], [], ['HTTP_X_MEMBER' => 'bob']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'request' => $this->field($form, 'request'), 'decision' => 'withdraw'], [], ['HTTP_X_MEMBER' => 'ann']);
        self::assertResponseRedirects();
        self::assertStringContainsString('No request waits for you.', $this->page('bob')->filter('#requests')->text());
    }

    public function testRejectingAndAnUnknownMutualActionAreHandled(): void
    {
        $this->follows()->follow('ann', new SubjectReference('member', 'bob'));
        $this->press($this->page('bob')->filter('#requests'), 'decision', 'reject');
        self::assertNull($this->level('ann', 'member:bob'));

        $form = $this->page('ann', 'thread:1', 'carl')->filter('#mutual form')->form();
        $this->client->request('POST', $form->getUri(), ['_token' => $this->field($form, '_token'), 'action' => 'shout'], [], ['HTTP_X_MEMBER' => 'ann']);
        self::assertResponseStatusCodeSame(422);
    }

    private function field(Form $form, string $name): string
    {
        $field = $form->get($name);
        self::assertInstanceOf(FormField::class, $field);

        $value = $field->getValue();

        return \is_string($value) ? $value : '';
    }

    private function press(Crawler $scope, string $name, string $value): void
    {
        $this->client->submit($scope->filter(\sprintf('button[name="%s"][value="%s"]', $name, $value))->first()->form());
    }

    private function page(string $member, string $subject = 'thread:1', string $other = 'bob'): Crawler
    {
        $this->client->setServerParameter('HTTP_X_MEMBER', $member);

        return $this->client->request('GET', '/page/'.$subject.'/'.$other);
    }

    private function follows(): Follows
    {
        $follows = self::getContainer()->get(Follows::class);
        $this->assertInstanceOf(Follows::class, $follows);

        return $follows;
    }

    private function level(string $member, string $subject): ?FollowLevel
    {
        [$type, $id] = explode(':', $subject, 2);

        return $this->follows()->state($member, new SubjectReference($type, $id))->own?->level();
    }
}
