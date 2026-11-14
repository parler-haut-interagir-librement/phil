<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the browser suite: `/{kit}/{kind}` draws the ballot and the results of one
 * poll of each kind, drawn by the PHIL UX kit (`phil`) or by the Shadcn kit (`shadcn`), in the browser environment of
 * the functional application, whose polls live in a SQLite file; `?as=` names the viewer, nobody when empty. A posted
 * ballot is read by `BallotForm` and cast, or changed, then the page is drawn again: with the message of a ballot
 * refused, or after a redirection.
 */

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\PollException;
use Phil\PollBundle\Application\BallotForm;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Exception\PollBundleException;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

$package = dirname(__DIR__, 2);
$vendor = array_find([$package.'/../../vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php'));

if (null === $vendor) {
    throw new RuntimeException('Run composer install first.');
}

require $vendor.'/autoload.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? (string) strtok($uri, '?') : '/';

if (1 !== preg_match('#^/(phil|shadcn)/(single|multiple|ranked|scale|nps|closed)$#D', $path, $route)) {
    http_response_code(404);

    return true;
}

[, $kit, $kind] = $route;
$kernel = new TestKernel('shadcn' === $kit ? TestKernel::BROWSER_SHADCN : TestKernel::BROWSER, true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
assert($container instanceof ContainerInterface);
$entityManager = $container->get('doctrine.orm.entity_manager');
assert($entityManager instanceof EntityManagerInterface);

if (!$entityManager->getConnection()->createSchemaManager()->tablesExist(['phil_poll'])) {
    new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
}

$polls = $container->get(Polls::class);
assert($polls instanceof Polls);
$ada = ResourceReference::of('user', 'ada');
$subject = ResourceReference::of('page', $kind);
$drinks = [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee'), PollOption::of('cocoa', 'Cocoa')];
$poll = $polls->ofSubject($subject)[0] ?? $polls->create(match ($kind) {
    'single' => new PollDraft('Which drink?', $drinks, subject: $subject),
    'multiple' => new PollDraft('Which drinks?', $drinks, BallotKind::Multiple, maxChoices: 2, abstention: true, subject: $subject),
    'ranked' => new PollDraft('Rank the drinks', $drinks, BallotKind::Ranked, subject: $subject),
    'scale' => new PollDraft('How was the evening?', PollDraft::marks(1, 5, [1 => 'Poor', 5 => 'Great']), BallotKind::Scale, abstention: true, subject: $subject),
    'nps' => new PollDraft('How likely are you to recommend us?', PollDraft::marks(0, 10), BallotKind::Scale, counting: 'nps', subject: $subject),
    default => new PollDraft('Which drink, before closing?', $drinks, subject: $subject),
}, $ada);

if ('closed' === $kind && !$poll->isClosedAt(new DateTimeImmutable('2026-11-08 10:00:00 UTC'))) {
    $polls->cast($poll->id, ResourceReference::of('user', 'cid'), ['coffee']);
    $polls->close($poll->id, $ada);
}

$as = $_GET['as'] ?? 'bob';
$as = is_string($as) ? $as : '';
$page = $path.'?as='.rawurlencode($as);
$viewer = '' !== $as ? ResourceReference::of('user', $as) : null;
$errors = [];

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') && $viewer instanceof ResourceReference) {
    try {
        $choices = BallotForm::choices($poll->rules(), $_POST);
        $polls->hasVoted($poll->id, $viewer) ? $polls->change($poll->id, $viewer, $choices) : $polls->cast($poll->id, $viewer, $choices);
        header('Location: '.$page, true, 303);

        return true;
    } catch (PollException|PollBundleException $refused) {
        http_response_code(422);
        $errors[] = $refused->getMessage();
    }
}

$twig = $container->get('twig');
assert($twig instanceof Environment);
$body = $twig->createTemplate(
    "{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, viewer), action: action, errors: errors}) }}"
    ."{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll, viewer)}) }}",
)->render(['poll' => $poll->id, 'viewer' => $viewer, 'action' => $page, 'errors' => $errors]);

header('Content-Type: text/html; charset=utf-8');
echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Poll</title>
    </head>
    <body>
        <main>{$body}</main>
    </body>
    </html>
    HTML;

return true;
