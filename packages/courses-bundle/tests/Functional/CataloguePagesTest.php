<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Http\CatalogueController;
use Phil\CoursesBundle\Tests\Functional\App\CartCheckout;
use Phil\CoursesBundle\Tests\Functional\App\TestKernel;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The pages of the catalogues in an application: the catalogues shown to a visitor and to the members of a unit, one
 * catalogue filtered, sorted and paged, a learner enrolling in a free course and sent to the cart for a course sold.
 */
#[CoversClass(CatalogueController::class)]
final class CataloguePagesTest extends WebTestCase
{
    private const array ADA = ['HTTP_X_USER' => 'ada', 'HTTP_X_UNITS' => 'sales'];

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

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        Store::create($connection);
    }

    public function testTheCataloguesShownToAVisitorAndToAMember(): void
    {
        $catalogues = $this->catalogues();
        $public = $catalogues->create('Everyone', 'Open courses.');
        $catalogues->create('Sales', '', CatalogueAudience::Unit, 'sales');
        $catalogues->create('East', '', CatalogueAudience::Unit, 'east');

        $this->client->request('GET', '/courses/catalogues');
        self::assertResponseIsSuccessful();
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['Everyone'], $this->client->getCrawler()->filter('.phil-courses__catalogues a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSelectorTextContains('.phil-courses__catalogues .phil-courses__description', 'Open courses.');
        self::assertSelectorNotExists('.phil-courses__catalogues [data-slot="badge"]');

        $this->client->request('GET', '/courses/catalogues', server: self::ADA);
        self::assertSame(['Everyone', 'Sales'], $this->client->getCrawler()->filter('.phil-courses__catalogues a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSelectorTextContains('.phil-courses__catalogues [data-slot="badge"]', 'Your unit');
        self::assertSame('/courses/catalogues/'.$public->id->value, $this->client->getCrawler()->filter('.phil-courses__catalogues a')->first()->attr('href'));

        $catalogues->remove($public->id);
        $this->client->request('GET', '/courses/catalogues');
        self::assertSelectorTextContains('.phil-courses__none', 'No catalogue is open to you.');
    }

    public function testACatalogueIsFilteredSortedPagedAndHiddenFromOthers(): void
    {
        $catalogues = $this->catalogues();
        $catalogue = $catalogues->create('Workshop', 'Courses of the workshop.');
        $hidden = $catalogues->create('East', '', CatalogueAudience::Unit, 'east');

        for ($number = 1; $number <= Catalogues::PER_PAGE + 1; ++$number) {
            $course = $this->courses()->create(\sprintf('Course %02d', $number));
            $catalogues->offer($catalogue->id, $course->id, true, 1 === $number ? CoursePrice::of(4_250, 'CAD') : null, 2 === $number ? ['Safety'] : []);
        }

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Workshop');
        self::assertSelectorTextContains('.phil-courses__count', '21 courses');
        self::assertCount(Catalogues::PER_PAGE, $crawler->filter('.phil-courses__offer'));
        self::assertSame('CA$42.50', $crawler->filter('.phil-courses__offer [data-slot="badge"]')->first()->text());
        self::assertSame('Free', $crawler->filter('.phil-courses__offer')->eq(1)->filter('[data-slot="badge"]')->text());
        self::assertSelectorNotExists('.phil-courses__offer form');
        self::assertSame('Page 1 of 2', $crawler->filter('.phil-courses__pages span')->text());
        self::assertSelectorNotExists('.phil-courses__pages a[rel="prev"]');
        self::assertSame('/courses/catalogues/'.$catalogue->id->value.'?page=2', $crawler->filter('.phil-courses__pages a[rel="next"]')->attr('href'));
        self::assertSame(['', 'safety'], $crawler->filter('#phil-courses-tag option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value.'?sort=title&page=2');
        self::assertSame(['Course 21'], $crawler->filter('.phil-courses__offer h2')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame('/courses/catalogues/'.$catalogue->id->value.'?sort=title', $crawler->filter('.phil-courses__pages a[rel="prev"]')->attr('href'));
        self::assertSelectorNotExists('.phil-courses__pages a[rel="next"]');
        self::assertSame('title', $crawler->filter('#phil-courses-sort option[selected]')->attr('value'));
        self::assertSame('any', $crawler->filter('#phil-courses-price option[selected]')->attr('value'));

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value.'?price=paid&page=2');
        self::assertSame(['Course 01'], $crawler->filter('.phil-courses__offer h2')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame('paid', $crawler->filter('#phil-courses-price option[selected]')->attr('value'));
        self::assertSame('placed', $crawler->filter('#phil-courses-sort option[selected]')->attr('value'));

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value.'?tag=safety&q=course');
        self::assertSame(['Course 02'], $crawler->filter('.phil-courses__offer h2')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame('course', $crawler->filter('#phil-courses-q')->attr('value'));
        self::assertSame('safety', $crawler->filter('#phil-courses-tag option[selected]')->attr('value'));
        self::assertSelectorTextContains('.phil-courses__count', '1 course');
        self::assertSelectorNotExists('.phil-courses__pages');

        $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value.'?q=nothing');
        self::assertSelectorTextContains('.phil-courses__count', 'No course');
        self::assertSelectorNotExists('.phil-courses__offered');

        $this->client->request('GET', '/courses/catalogues/'.$hidden->id->value, server: self::ADA);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/courses/catalogues/'.$hidden->id->value, server: ['HTTP_X_USER' => 'eve', 'HTTP_X_UNITS' => 'east']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-courses__count', 'No course');
    }

    public function testALearnerEnrollsInAFreeCourseAndIsSentToTheCartForACourseSold(): void
    {
        $catalogues = $this->catalogues();
        $catalogue = $catalogues->create('Sales', '', CatalogueAudience::Unit, 'sales');
        $free = $this->courses()->create('Free course');
        $sold = $this->courses()->create('Sold course');
        $closed = $this->courses()->create('Closed course');
        $catalogues->offer($catalogue->id, $free->id);
        $catalogues->offer($catalogue->id, $sold->id, true, CoursePrice::of(9_900, 'CAD'));
        $catalogues->offer($catalogue->id, $closed->id, false);

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value, server: self::ADA);
        self::assertSame(['Enroll', 'Buy'], $crawler->filter('.phil-courses__offer form button')->each(static fn (Crawler $button): string => $button->text()));

        $this->client->submit($crawler->filter('.phil-courses__offer form')->first()->form(), [], self::ADA);
        self::assertResponseRedirects('/courses/'.$free->id->value, 303);
        self::assertNotNull($this->enrollments()->of($free->id, LearnerId::of('ada')));

        $crawler = $this->client->request('GET', '/courses/catalogues/'.$catalogue->id->value, server: self::ADA);
        self::assertSame(['Buy'], $crawler->filter('.phil-courses__offer form button')->each(static fn (Crawler $button): string => $button->text()));
        self::assertSame('Enrolled', $crawler->filter('.phil-courses__offer')->first()->filter('[data-slot="badge"]')->eq(1)->text());
        $form = $crawler->filter('.phil-courses__offer form')->first()->form();
        $this->client->submit($form, [], self::ADA);
        self::assertResponseRedirects('/shop/cart?'.http_build_query(['add' => 'course:'.$catalogue->id->value.'/'.$sold->id->value, 'for' => 'ada']), 303);
        self::assertNull($this->enrollments()->of($sold->id, LearnerId::of('ada')));

        $cart = self::getContainer()->get(CartCheckout::class);
        self::assertInstanceOf(CartCheckout::class, $cart);

        foreach (['http://localhost/shop/cart' => 303, '//evil.example.net/cart' => 500, '/\evil.example.net' => 500, 'https://evil.example.net/cart' => 500] as $address => $status) {
            $cart->address = $address;
            $this->client->submit($form, [], self::ADA);
            self::assertResponseStatusCodeSame($status, $address);
        }

        $token = (string) $crawler->filter('.phil-courses__offer form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/courses/catalogues/'.$catalogue->id->value.'/'.$closed->id->value.'/enroll', ['_token' => $token], server: self::ADA);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('POST', '/courses/catalogues/'.$catalogue->id->value.'/'.$free->id->value.'/enroll', ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/courses/catalogues/'.$catalogue->id->value.'/'.$free->id->value.'/enroll', ['_token' => 'forged'], server: self::ADA);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/courses/catalogues/'.$catalogue->id->value.'/'.$free->id->value.'/enroll', ['_token' => $token], server: ['HTTP_X_USER' => 'eve']);
        self::assertResponseStatusCodeSame(404);
    }

    private function catalogues(): Catalogues
    {
        $catalogues = self::getContainer()->get(Catalogues::class);
        self::assertInstanceOf(Catalogues::class, $catalogues);

        return $catalogues;
    }

    private function courses(): Courses
    {
        $courses = self::getContainer()->get(Courses::class);
        self::assertInstanceOf(Courses::class, $courses);

        return $courses;
    }

    private function enrollments(): Enrollments
    {
        $enrollments = self::getContainer()->get(Enrollments::class);
        self::assertInstanceOf(Enrollments::class, $enrollments);

        return $enrollments;
    }
}
