<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Doctrine\Columns;
use Phil\ReviewsBundle\Tests\Functional\App\TestKernel;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * The bundle in an application: a visitor reads the reviews, a member writes one through the form without
 * JavaScript, another votes on it with or without JavaScript, the merchant responds, a withdrawn review is contested;
 * the forms refuse a request without the token, and an unknown subject or review is not found.
 */
#[CoversNothing]
final class ReviewsBundleTest extends WebTestCase
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
        Store::create($this->connection());
    }

    public function testAVisitorReadsTheRules(): void
    {
        $this->client->request('GET', '/reviews/game/catan');

        self::assertResponseIsSuccessful();
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Reviews of game catan');
        self::assertSelectorTextContains('.phil-reviews__average', 'No review yet.');
        self::assertSelectorTextContains('.phil-reviews__yours', 'Sign in to write a review.');
        self::assertSelectorTextContains('.phil-reviews__rules', 'Conflict of interest');
        self::assertSelectorTextContains('.phil-reviews__method', '2 fictitious reviews of 3.0 are added');
        self::assertSelectorNotExists('form.phil-reviews__form');
    }

    public function testAMemberWritesAReviewAndAnotherVotesOnIt(): void
    {
        $crawler = $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorExists('fieldset.isRating legend');
        self::assertSelectorExists('input[type="radio"][name="overall"][value="5"][required]');
        self::assertSelectorExists('input[type="radio"][name="criteria[rules]"][value="0"]');

        $form = $crawler->filter('form.phil-reviews__form')->form();
        $this->choice($form, 'overall')->select('4');
        $this->choice($form, 'criteria[rules]')->select('5');
        $form['body'] = "Short rules.\nGreat with four.";
        $this->choice($form, 'superlatives[0]')->tick();
        $this->choice($form, 'compensated')->tick();
        $form['compensation_note'] = 'A free copy';
        $this->client->submit($form, [], ['HTTP_X_USER' => 'ada']);

        self::assertResponseStatusCodeSame(303);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/reviews/game/catan\#review-[0-9a-f-]{36}$#', $location);
        $id = substr($location, \strlen('/reviews/game/catan#review-'));

        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('#review-'.$id.' .phil-reviews__body', 'Short rules.');
        self::assertStringContainsString('Short rules.<br />', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('#review-'.$id.' .phil-reviews__compensation', 'The author received something in exchange for this review. What they received: A free copy.');
        self::assertSelectorExists('#review-'.$id.' [role="img"][aria-label="Overall score 4 out of 5 stars"]');
        self::assertSelectorTextContains('#review-'.$id.' .phil-reviews__superlatives', 'superlatives.gorgeous');
        self::assertSelectorTextContains('.phil-reviews__average', 'One review so far: an average is shown from 2 reviews.');
        self::assertSelectorTextContains('#phil-reviews-list-title', 'One review');

        $crawler = $this->client->getCrawler();
        $vote = $crawler->filter('#review-'.$id.' form.phil-reviews__helpful')->selectButton('Helpful')->form();
        $this->client->submit($vote, [], ['HTTP_X_USER' => 'bob']);
        self::assertResponseStatusCodeSame(303);

        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('#review-'.$id.' .phil-reviews__helpful p', '1 found it helpful, 0 did not.');
        self::assertSelectorExists('#review-'.$id.' button[value="retract"][aria-pressed="true"]');

        $token = $this->client->getCrawler()->filter('#review-'.$id.' form.phil-reviews__helpful input[name="_token"]')->attr('value');
        $this->client->request('POST', '/reviews/review/'.$id.'/vote', ['_token' => $token, 'vote' => 'unhelpful'], server: ['HTTP_X_USER' => 'bob', 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(['helpful' => 0, 'unhelpful' => 1, 'vote' => false], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', '/reviews/review/'.$id.'/vote', ['_token' => $token, 'vote' => 'retract'], server: ['HTTP_X_USER' => 'bob', 'HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['helpful' => 0, 'unhelpful' => 0, 'vote' => null], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', '/reviews/review/'.$id.'/vote', ['_token' => $token, 'vote' => 'helpful'], server: ['HTTP_X_USER' => 'ada', 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Your own review takes no vote of yours.'], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', '/reviews/review/'.$id.'/vote', ['_token' => 'forged', 'vote' => 'helpful'], server: ['HTTP_X_USER' => 'bob', 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAuthorChangesTheirReview(): void
    {
        $this->write('ada', ['overall' => '2', 'body' => 'Meh']);
        $crawler = $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextContains('form.phil-reviews__form button[type="submit"]', 'Update my review');
        self::assertSelectorTextContains('#phil-reviews-body', 'Meh');
        self::assertSelectorExists('input[name="overall"][value="2"][checked]');

        $form = $crawler->filter('form.phil-reviews__form')->form();
        $this->choice($form, 'overall')->select('5');
        $form['body'] = 'Grew on me.';
        $this->client->submit($form, [], ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(303);

        $this->client->request('GET', '/reviews/game/catan');
        self::assertSelectorTextContains('.phil-reviews__body', 'Grew on me.');
        self::assertSelectorTextContains('.phil-reviews__byline', 'edited');
        self::assertSame(1, Columns::int($this->connection()->fetchOne('SELECT COUNT(*) FROM phil_review')));
    }

    public function testAnInvalidReviewIsShownAgainWithItsError(): void
    {
        $this->write('ada', ['overall' => '9', 'body' => 'Too many stars', 'title' => 'Wow', 'criteria' => ['rules' => '0', 'replayability' => '3'], 'superlatives' => ['quick'], 'compensated' => '1', 'compensation_note' => 'Nothing']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#phil-reviews-error', 'The scores of a review of "game" are from 1 to 5.');
        self::assertSelectorTextContains('#phil-reviews-body', 'Too many stars');
        self::assertSelectorExists('#phil-reviews-title[value="Wow"]');
        self::assertSelectorExists('input[name="criteria[replayability]"][value="3"][checked]');
        self::assertSelectorExists('input[name="superlatives[]"][value="quick"][checked]');
        self::assertSelectorExists('input[name="compensated"][checked]');
        self::assertSelectorExists('form.phil-reviews__form[aria-describedby="phil-reviews-error"]');
        self::assertSame(0, Columns::int($this->connection()->fetchOne('SELECT COUNT(*) FROM phil_review')));
    }

    public function testANonsenseScoreIsRefused(): void
    {
        $this->write('ada', ['overall' => '4', 'criteria' => ['rules' => 'lots']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#phil-reviews-error', 'The scores of the criteria are integers by code.');
    }

    public function testAFormWithoutTheTokenOrAMemberIsRefused(): void
    {
        $this->client->request('POST', '/reviews/game/catan', ['_token' => 'forged', 'overall' => '4'], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('#phil-reviews-error', 'This form expired or did not come from this site');

        $this->client->request('POST', '/reviews/game/catan', ['_token' => $this->token(), 'overall' => '4']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, Columns::int($this->connection()->fetchOne('SELECT COUNT(*) FROM phil_review')));
    }

    public function testAnUnknownSubjectOrReviewIsNotFound(): void
    {
        $this->client->request('GET', '/reviews/book/dune');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/reviews/game/'.str_repeat('x', 191));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/reviews/review/'.ReviewId::generate()->value.'/vote', ['vote' => 'helpful']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheMerchantResponds(): void
    {
        $id = $this->write('ada', ['overall' => '2', 'body' => 'Box arrived broken']);

        $crawler = $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'shop']);
        $form = $crawler->filter('#review-'.$id.' form.phil-reviews__respond')->form(['response' => 'Sorry, a new box is on its way.']);
        $this->client->submit($form, [], ['HTTP_X_USER' => 'shop']);
        self::assertResponseStatusCodeSame(303);

        $this->client->request('GET', '/reviews/game/catan');
        self::assertSelectorTextContains('#review-'.$id.' .phil-reviews__response', 'Sorry, a new box is on its way.');
        self::assertSelectorNotExists('form.phil-reviews__respond');

        $this->client->request('POST', '/reviews/review/'.$id.'/response', ['_token' => $this->token(), 'response' => ''], server: ['HTTP_X_USER' => 'shop']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/reviews/game/catan');
        self::assertSelectorNotExists('.phil-reviews__response');

        $this->client->request('POST', '/reviews/review/'.$id.'/response', ['_token' => $this->token(), 'response' => 'Me too'], server: ['HTTP_X_USER' => 'bob']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#phil-reviews-error', 'You do not answer the reviews of this subject.');

        $this->client->request('POST', '/reviews/review/'.$id.'/response', ['_token' => 'forged', 'response' => 'Me too'], server: ['HTTP_X_USER' => 'shop']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAuthorOfAWithdrawnReviewContestsIt(): void
    {
        $id = $this->write('ada', ['overall' => '1', 'body' => 'Bad']);
        $reviews = self::getContainer()->get(Reviews::class);
        self::assertInstanceOf(Reviews::class, $reviews);
        $reviews->hold(ReviewId::fromString($id));

        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextContains('.phil-reviews__yours', 'Your review is hidden while the moderation looks at it.');

        $reviews->withdraw(ReviewId::fromString($id), 'off-topic');
        $crawler = $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextContains('.phil-reviews__yours', 'Your review was withdrawn under the criterion « Off topic ».');
        self::assertSelectorNotExists('#review-'.$id);

        $this->client->request('POST', '/reviews/review/'.$id.'/contest', ['_token' => $this->token(), 'evidence' => ' '], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(422);

        $form = $crawler->filter('form.phil-reviews__contest')->form(['evidence' => 'My review is about the game itself.']);
        $this->client->submit($form, [], ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextContains('.phil-reviews__yours', 'Your contest is being looked at.');

        $reviews->settleContest(ReviewId::fromString($id), false);
        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextContains('.phil-reviews__yours', 'Your contest was rejected: the withdrawal stands.');

        $this->client->request('POST', '/reviews/review/'.$id.'/contest', ['_token' => 'forged', 'evidence' => 'Again'], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testASuspendedSubjectShowsItsReviewsAndNoForm(): void
    {
        $this->write('ada', ['overall' => '4', 'body' => 'Kept']);
        $ratings = self::getContainer()->get(Ratings::class);
        self::assertInstanceOf(Ratings::class, $ratings);
        $ratings->suspend(SubjectRef::of('game', 'catan'), true);

        foreach (['ada', 'bob'] as $member) {
            $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => $member]);
            self::assertSelectorTextContains('.phil-reviews__suspended', 'New reviews are suspended for now.');
            self::assertSelectorTextContains('.phil-reviews__yours', 'The reviews of this subject are suspended for now; the reviews given stay.');
            self::assertSelectorNotExists('form.phil-reviews__form');
            self::assertSelectorTextContains('.phil-reviews__body', 'Kept');
        }
    }

    public function testTheSummaryAndThePagesOfManyReviews(): void
    {
        $this->write('ada', ['overall' => '5', 'criteria' => ['rules' => '4'], 'superlatives' => ['quick']]);
        $this->write('bob', ['overall' => '4', 'criteria' => ['rules' => '5'], 'superlatives' => ['quick', 'gorgeous']]);
        $this->write('cy', ['overall' => '3']);

        $this->client->request('GET', '/reviews/game/catan?order=lowest');
        self::assertSelectorTextContains('.phil-reviews__average', '4.0 out of 5, from 3 reviews');
        self::assertSelectorExists('.phil-reviews__summary [role="img"][aria-label="Average 4 out of 5 stars"]');
        self::assertSelectorTextContains('.phil-reviews__summary .phil-reviews__criteria', '4.5 out of 5, 2 ratings');
        self::assertSelectorTextContains('.phil-reviews__summary .phil-reviews__superlatives li:first-child', 'superlatives.quick · 2 times');
        self::assertSelectorTextContains('.phil-reviews__pages', 'Page 1 of 2');
        self::assertSelectorExists('a[aria-current="true"][href="/reviews/game/catan?order=lowest"]');
        self::assertCount(2, $this->client->getCrawler()->filter('.phil-reviews__review'));
        self::assertStringStartsWith('cy', trim($this->client->getCrawler()->filter('.phil-reviews__review')->first()->filter('.phil-reviews__byline')->text()));

        $this->client->request('GET', '/reviews/game/catan?order=lowest&page=2');
        self::assertCount(1, $this->client->getCrawler()->filter('.phil-reviews__review'));
        self::assertSelectorTextContains('.phil-reviews__pages', 'Page 2 of 2');
        self::assertSelectorExists('a[rel="prev"]');

        $this->client->request('GET', '/reviews/game/catan?page=9&order=nonsense');
        self::assertSelectorTextContains('.phil-reviews__pages', 'Page 2 of 2');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function write(string $member, array $fields): string
    {
        $this->client->request('POST', '/reviews/game/catan', ['_token' => $this->token()] + $fields, server: ['HTTP_X_USER' => $member]);
        $location = (string) $this->client->getResponse()->headers->get('Location');

        return substr($location, \strlen('/reviews/game/catan#review-'));
    }

    private function choice(Form $form, string $name): ChoiceFormField
    {
        $field = $form->get($name);
        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }

    private function token(): string
    {
        $this->client->request('GET', '/reviews/game/catan', server: ['HTTP_X_USER' => 'zed']);

        return (string) $this->client->getCrawler()->filter('form.phil-reviews__form input[name="_token"]')->attr('value');
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
