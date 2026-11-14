<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingController;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Tests\Unit\Fixture\FixedUser;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(OnboardingController::class)]
#[CoversClass(OnboardingRequest::class)]
final class ControllerTest extends TestCase
{
    private Kit $kit;

    private FixedUser $user;

    private OnboardingController $controller;

    #[Override]
    protected function setUp(): void
    {
        $this->kit = new Kit();
        $this->user = new FixedUser('ada');
        $this->controller = new OnboardingController($this->kit->onboarding, $this->user, new OnboardingRequest(new Tokens()));
    }

    public function testAFormTicksAStepAndGoesBack(): void
    {
        $response = $this->controller->done($this->form(['HTTP_REFERER' => 'https://site.test/dashboard?tab=1']), 'author', 'profile');

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('https://site.test/dashboard?tab=1', $response->headers->get('Location'));
        self::assertSame(StepState::Completed, $this->kit->repository->find('ada', 'author')?->stateOf('profile'));
    }

    public function testAFormFromElsewhereGoesHome(): void
    {
        foreach ([['HTTP_REFERER' => 'https://evil.test/'], ['HTTP_REFERER' => 'https://site.test.evil.test/'], []] as $server) {
            self::assertSame('/', $this->controller->skip($this->form($server), 'author', 'profile')->headers->get('Location'));
        }
    }

    public function testTheTourGetsWhereTheUserStands(): void
    {
        $request = $this->json();
        $response = $this->controller->skip($request, 'author', 'profile');

        self::assertSame('{"flow":"author","done":1,"total":4,"current":"publish","finished":false,"dismissed":false}', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->kit->onboarding->complete('ada', 'author', 'publish');
        $this->kit->onboarding->complete('ada', 'author', 'page');

        $response = $this->controller->done($this->json(), 'author', 'rules');
        self::assertSame('{"flow":"author","done":4,"total":4,"current":null,"finished":true,"dismissed":false}', $response->getContent());
    }

    public function testAFlowIsDismissedAndReset(): void
    {
        self::assertSame('{"flow":"author","done":0,"total":4,"current":"profile","finished":false,"dismissed":true}', $this->controller->dismiss($this->json(), 'author')->getContent());
        self::assertTrue($this->kit->repository->find('ada', 'author')?->isDismissed());

        $this->controller->reset($this->json(), 'author');
        self::assertFalse($this->kit->repository->find('ada', 'author')->isDismissed());
    }

    public function testAVisitorAndARequestFromAnotherSiteAreForbidden(): void
    {
        $crossSite = $this->json(['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $badToken = Request::create('/', 'POST', server: ['HTTP_X_PHIL_ONBOARDING_TOKEN' => 'bad', 'HTTP_ACCEPT' => 'application/json']);
        $noToken = Request::create('/', 'POST');

        foreach ([$crossSite, $badToken, $noToken] as $request) {
            $response = $this->controller->done($request, 'author', 'profile');
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
            self::assertSame('{"error":"forbidden"}', $response->getContent());
        }

        $this->user->id = null;
        self::assertSame(Response::HTTP_FORBIDDEN, $this->controller->dismiss($this->json(), 'author')->getStatusCode());
        self::assertSame([], $this->kit->repository->ofUser('ada'));
    }

    public function testASameSiteRequestWithTheTokenInTheFormPasses(): void
    {
        self::assertSame(Response::HTTP_SEE_OTHER, $this->controller->done($this->form(['HTTP_SEC_FETCH_SITE' => 'same-origin']), 'author', 'profile')->getStatusCode());
    }

    public function testUnknownAndRefusedChanges(): void
    {
        $response = $this->controller->done($this->json(), 'author', 'missing');
        self::assertSame([Response::HTTP_NOT_FOUND, '{"error":"not_found"}'], [$response->getStatusCode(), $response->getContent()]);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->controller->reset($this->json(), 'nope')->getStatusCode());

        $response = $this->controller->done($this->json(), 'author', 'publish');
        self::assertSame([Response::HTTP_CONFLICT, '{"error":"conflict"}'], [$response->getStatusCode(), $response->getContent()]);
        self::assertSame(Response::HTTP_CONFLICT, $this->controller->skip($this->json(), 'author', 'rules')->getStatusCode());

        $this->kit->repository->racing = true;
        self::assertSame(Response::HTTP_CONFLICT, $this->controller->dismiss($this->json(), 'author')->getStatusCode());
        $this->kit->repository->racing = false;

        $this->user->id = str_repeat('a', 321);
        self::assertSame(Response::HTTP_CONFLICT, $this->controller->dismiss($this->json(), 'author')->getStatusCode());
        self::assertNotInstanceOf(Progress::class, $this->kit->repository->find('ada', 'author'));
    }

    public function testTheChoicesThatAreNotTextAreLeftOut(): void
    {
        $request = Request::create('https://site.test/onboarding/x', 'POST', ['_token' => 'good', 'choices' => ['events', 3, 'news']], server: ['HTTP_REFERER' => 'https://site.test/welcome']);
        $this->controller->answer($request, 'member', 'topics');

        self::assertSame(['news', 'events'], $this->kit->grants->answers[0]->choices);
    }

    public function testAFormAnswersAQuestion(): void
    {
        $request = Request::create('https://site.test/onboarding/x', 'POST', ['_token' => 'good', 'choices' => ['events', 'news', 3]], server: ['HTTP_REFERER' => 'https://site.test/welcome']);
        $response = $this->controller->answer($request, 'member', 'topics');

        self::assertSame([Response::HTTP_SEE_OTHER, 'https://site.test/welcome'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame(['news', 'events'], $this->kit->grants->answers[0]->choices);
        self::assertSame(StepState::Completed, $this->kit->repository->find('ada', 'member')?->stateOf('topics'));
    }

    public function testAnAnswerTheQuestionCannotTake(): void
    {
        foreach ([['_token' => 'good'], ['_token' => 'good', 'choices' => 'news'], ['_token' => 'good', 'choices' => ['sports']], ['_token' => 'good', 'choices' => ['new', 'old']]] as $body) {
            $response = $this->controller->answer(Request::create('/', 'POST', $body, server: ['HTTP_ACCEPT' => 'application/json']), 'member', 'level');
            self::assertSame([Response::HTTP_UNPROCESSABLE_ENTITY, '{"error":"invalid_answer"}'], [$response->getStatusCode(), $response->getContent()]);
        }

        self::assertSame(Response::HTTP_CONFLICT, $this->controller->answer(Request::create('/', 'POST', ['_token' => 'good', 'choices' => ['news']]), 'author', 'profile')->getStatusCode());
        self::assertSame([], $this->kit->grants->answers);
    }

    public function testTheTokenOfTheForms(): void
    {
        self::assertSame('good', new OnboardingRequest(new Tokens())->token());
    }

    /**
     * @param array<string, string> $server
     */
    private function form(array $server = []): Request
    {
        return Request::create('https://site.test/onboarding/x', 'POST', ['_token' => 'good'], server: $server);
    }

    /**
     * @param array<string, string> $server
     */
    private function json(array $server = []): Request
    {
        return Request::create('https://site.test/onboarding/x', 'POST', server: $server + ['HTTP_X_PHIL_ONBOARDING_TOKEN' => 'good', 'HTTP_ACCEPT' => 'application/json']);
    }
}
