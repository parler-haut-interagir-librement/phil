<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Http\CourseRequest;
use Phil\CoursesBundle\Infrastructure\Http\SecondChancesController;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The administration page refused to everyone when the application has no authorization checker, and to a teacher who
 * is not signed in, before any form is read.
 */
#[CoversClass(SecondChancesController::class)]
final class SecondChancesControllerTest extends TestCase
{
    public function testWithoutAnAuthorizationCheckerThePageIsRefusedToEveryone(): void
    {
        $controller = $this->controller(LearnerId::of('teacher-1'), null);

        foreach ([$controller->list(...), static fn (): mixed => $controller->decide(new Request(), '01234567-89ab-cdef-0123-456789abcdef')] as $call) {
            try {
                $call();
                self::fail('The page is open.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertSame('The second chances are decided by the teachers.', $accessDeniedException->getMessage());
            }
        }
    }

    public function testATeacherNotSignedInIsRefusedWhateverTheChecker(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $this->expectException(AccessDeniedException::class);

        $this->controller(null, $checker)->list();
    }

    public function testATeacherTheCheckerRefusesIsRefused(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->controller(LearnerId::of('teacher-1'), $checker)->list();
    }

    public function testATeacherTheCheckerGrantsThePageForTheRoleIsShown(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => 'ROLE_COURSES_TEACHER' === $attribute);

        $response = $this->controller(LearnerId::of('teacher-1'), $checker)->list();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testThePageIsRenderedWithTheReviewsTheDecisionsTheLayoutAndTheToken(): void
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_courses', 'the-token'));
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with('@PhilCourses/second_chances.html.twig', [
            'reviews' => [],
            'decisions' => ['grant', 'refuse', 'waive'],
            'layout' => '@PhilCourses/layout.html.twig',
            'token' => 'the-token',
        ])->willReturn('page');

        $response = $this->controller(LearnerId::of('teacher-1'), $checker, $twig, $tokens)->list();

        self::assertSame('page', $response->getContent());
    }

    private function controller(?LearnerId $teacher, ?AuthorizationCheckerInterface $checker, ?Environment $twig = null, ?CsrfTokenManagerInterface $tokens = null): SecondChancesController
    {
        $current = new readonly class($teacher) implements CurrentLearner {
            public function __construct(
                private ?LearnerId $teacher,
            ) {
            }

            #[Override]
            public function current(): ?LearnerId
            {
                return $this->teacher;
            }
        };

        return new SecondChancesController(
            new Store()->secondChances,
            $current,
            new CourseRequest($tokens ?? self::createStub(CsrfTokenManagerInterface::class)),
            $twig ?? self::createStub(Environment::class),
            self::createStub(UrlGeneratorInterface::class),
            $checker,
            'ROLE_COURSES_TEACHER',
            '@PhilCourses/layout.html.twig',
        );
    }
}
