<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use JsonException;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\Cmi5\LaunchData;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Application\Xapi\XapiStatements;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints an assignable unit talks to, outside the application's firewall: the fetch URL, which gives a
 * session's token once; and the part of the xAPI an assignable unit needs — statements, kept through the learning
 * record store once the rules of cmi5 accept them; the `LMS.LaunchData` state document, read only, and the unit's own
 * state documents; the learner preferences, which the platform keeps none of; and `about`. Every xAPI request carries
 * its session's token and the `X-Experience-API-Version` header; cross-origin requests are answered for the content
 * origin and the origins the configuration allows, never for any origin. The sessions of xAPI activities talk to the
 * same endpoints: their statements are checked by `XapiStatements` instead of the cmi5 rules, and their state documents
 * belong to the activity identifier of their package.
 */
final readonly class Cmi5Controller
{
    public const string ENDPOINT_ROUTE = 'phil_courses_xapi';

    public const string FETCH_ROUTE = 'phil_courses_cmi5_fetch';

    public const string STATEMENTS_ROUTE = 'phil_courses_xapi_statements';

    public const string STATE_ROUTE = 'phil_courses_xapi_state';

    public const string AGENT_PROFILE_ROUTE = 'phil_courses_xapi_agent_profile';

    public const string ABOUT_ROUTE = 'phil_courses_xapi_about';

    public const string VERSION = '1.0.3';

    public const int STATE_BYTES = 65_536;

    private const string HEADERS = 'Authorization, Content-Type, X-Experience-API-Version';

    /**
     * @param list<string> $origins the origins whose scripts may call the endpoints
     */
    public function __construct(
        private Cmi5Tokens $tokens,
        private Cmi5Statements $statements,
        private LearningRecordStore $store,
        private array $origins = [],
        private ?XapiStatements $xapi = null,
    ) {
    }

    public function fetch(Request $request, string $session): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->answer($request, new JsonResponse(['error-code' => Cmi5Tokens::GENERAL, 'error-text' => 'The fetch URL is posted to.']));
        }

        return $this->answer($request, new JsonResponse($this->tokens->fetch($session)));
    }

    public function about(Request $request): Response
    {
        return $this->answer($request, new JsonResponse(['version' => [self::VERSION]]));
    }

    public function preflight(Request $request): Response
    {
        return $this->answer($request, new Response('', Response::HTTP_NO_CONTENT, ['Access-Control-Max-Age' => '600']));
    }

    public function statements(Request $request): Response
    {
        $session = $this->session($request);

        if ($session instanceof Response) {
            return $session;
        }

        if ($request->isMethod('GET')) {
            return $this->error($request, Response::HTTP_FORBIDDEN, 'This store does not serve statements back.');
        }

        $statementId = $request->isMethod('PUT') ? $request->query->getString('statementId') : null;

        try {
            $ids = $this->xapi instanceof XapiStatements && $this->xapi->handles($session)
                ? $this->xapi->receive($session, $request->getContent(), $statementId)
                : $this->statements->receive($session, $request->getContent(), $statementId);
        } catch (StatementRefused $statementRefused) {
            return $this->error($request, Response::HTTP_FORBIDDEN, $statementRefused->getMessage());
        } catch (RecordStoreUnavailable) {
            return $this->error($request, Response::HTTP_SERVICE_UNAVAILABLE, 'The statements could not be kept: send them again later.');
        }

        return $this->answer($request, null === $statementId ? new JsonResponse($ids) : new Response('', Response::HTTP_NO_CONTENT));
    }

    public function state(Request $request): Response
    {
        $session = $this->session($request);

        if ($session instanceof Response) {
            return $session;
        }

        try {
            if ($this->xapi instanceof XapiStatements && $this->xapi->handles($session)) {
                $xapi = $this->xapi->context($session);

                return $this->matches($request, $xapi->activityIri, $xapi->registration, $xapi->homePage, $xapi->learner)
                    ? $this->document($request, $xapi->activityIri, $xapi->registration, $request->query->getString('stateId'))
                    : $this->error($request, Response::HTTP_FORBIDDEN, 'The state belongs to the activity, agent and registration of the session.');
            }

            $context = $this->statements->context($session);
        } catch (CoursesException $coursesException) {
            return $this->error($request, Response::HTTP_FORBIDDEN, $coursesException->getMessage());
        }

        if (!$this->matches($request, $context->activityIri, $context->registration, $context->homePage, $context->learner)) {
            return $this->error($request, Response::HTTP_FORBIDDEN, 'The state belongs to the activity, agent and registration of the session.');
        }

        $stateId = $request->query->getString('stateId');

        if (Cmi5Iri::LAUNCH_DATA === $stateId) {
            return $request->isMethod('GET')
                ? $this->answer($request, new JsonResponse(LaunchData::document($context)))
                : $this->error($request, Response::HTTP_FORBIDDEN, "The launch data is the platform's to write.");
        }

        return $this->document($request, $context->activityIri, $context->registration, $stateId);
    }

    public function agentProfile(Request $request): Response
    {
        $session = $this->session($request);

        if ($session instanceof Response) {
            return $session;
        }

        return $request->isMethod('GET')
            ? $this->error($request, Response::HTTP_NOT_FOUND, 'The platform keeps no learner preferences.')
            : $this->error($request, Response::HTTP_FORBIDDEN, "The learner preferences are the platform's to write.");
    }

    private function document(Request $request, string $activityIri, string $registration, string $stateId): Response
    {
        if ('' === $stateId || \strlen($stateId) > 190) {
            return $this->error($request, Response::HTTP_BAD_REQUEST, 'A state is named by a "stateId" of 1 to 190 bytes.');
        }

        if ($request->isMethod('GET')) {
            $document = $this->store->state($activityIri, $registration, $stateId);

            return null === $document
                ? $this->error($request, Response::HTTP_NOT_FOUND, 'No such state.')
                : $this->answer($request, new Response($document, Response::HTTP_OK, ['Content-Type' => 'application/json']));
        }

        if ($request->isMethod('DELETE')) {
            $this->store->deleteState($activityIri, $registration, $stateId);

            return $this->answer($request, new Response('', Response::HTTP_NO_CONTENT));
        }

        $document = $request->getContent();

        if (\strlen($document) > self::STATE_BYTES) {
            return $this->error($request, Response::HTTP_REQUEST_ENTITY_TOO_LARGE, \sprintf('A state document is %d bytes at most.', self::STATE_BYTES));
        }

        $this->store->saveState($activityIri, $registration, $stateId, $document);

        return $this->answer($request, new Response('', Response::HTTP_NO_CONTENT));
    }

    /**
     * The session the request's token opens, or the response that refuses it.
     */
    private function session(Request $request): LaunchSession|Response
    {
        if (!str_starts_with((string) $request->headers->get('X-Experience-API-Version'), '1.0')) {
            return $this->error($request, Response::HTTP_BAD_REQUEST, 'The X-Experience-API-Version header names version 1.0.');
        }

        return $this->tokens->authenticate($request->headers->get('Authorization'))
            ?? $this->error($request, Response::HTTP_UNAUTHORIZED, 'The token is missing, wrong, or its session ended.');
    }

    private function matches(Request $request, string $activityIri, string $registration, string $homePage, string $learner): bool
    {
        try {
            $agent = json_decode($request->query->getString('agent'), true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        $account = \is_array($agent) && \is_array($agent['account'] ?? null) ? $agent['account'] : [];

        return $request->query->getString('activityId') === $activityIri
            && $request->query->getString('registration') === $registration
            && ($account['homePage'] ?? null) === $homePage
            && ($account['name'] ?? null) === $learner;
    }

    private function error(Request $request, int $status, string $message): Response
    {
        return $this->answer($request, new JsonResponse(['error' => $message], $status));
    }

    private function answer(Request $request, Response $response): Response
    {
        $response->headers->set('X-Experience-API-Version', self::VERSION);
        $response->headers->set('Cache-Control', 'no-store');
        $response->setVary('Origin', false);

        $origin = $request->headers->get('Origin');

        if (null !== $origin && \in_array($origin, $this->origins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Headers', self::HEADERS);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Experience-API-Version');
        }

        return $response;
    }
}
