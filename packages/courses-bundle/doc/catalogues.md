# Catalogues

A catalogue lists the courses a learner may take. A site has a public catalogue, shown to everyone — visitors signed
out included —, and private catalogues, each shown to the members of one unit of an organization and of the units below
it: the sales department, its eastern branch. A catalogue places its courses in an order, opens each to
self-enrollment or not, gives it or sells it, and tags it; a learner lists it filtered, sorted and a page at a time,
enrolls themselves in a free course, and buys a course with a price through phil/commerce-bundle.

## Defining a catalogue

`Catalogues` writes and reads the catalogues:

```php
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;

$public = $catalogues->create('Our courses', 'Open to everyone.');
$sales = $catalogues->create('Sales training', '', CatalogueAudience::Unit, $salesUnitId);

$catalogues->offer($public->id, $welding->id);                                      // free, open to self-enrollment
$catalogues->offer($public->id, $safety->id, tags: ['Safety', 'Level 1']);
$catalogues->offer($public->id, $masterclass->id, price: CoursePrice::of(14_900, 'CAD'));
$catalogues->offer($sales->id, $negotiation->id, selfEnrollment: false);           // listed, enrolled by a manager
$catalogues->offer($public->id, $welcome->id, before: $welding->id);              // placed before another course

$catalogues->move($public->id, $masterclass->id, before: $welcome->id);
$catalogues->move($public->id, $welcome->id);                                        // after every other course
$catalogues->withdraw($public->id, $safety->id);

$catalogues->rename($public->id, 'All our courses', 'Open to everyone.');
$catalogues->showTo($sales->id, CatalogueAudience::Public);
$catalogues->remove($sales->id);
```

`offer()` adds the course after the other courses of the catalogue, or before the course `before` names. Called again
for a course of the catalogue, it changes how the catalogue offers it — open to self-enrollment, its price, its tags —
and keeps its place unless `before` names another. The courses keep their order: `entries()` gives them as
`CatalogueEntry` — the course, its place from 0, whether a learner enrolls themselves, its price, its tags.

| Rule | Refusal |
|---|---|
| A title is 1 to 255 characters, a description 10 000 at most | `InvalidCourse` |
| A public catalogue names no unit; a catalogue of a unit names one, an identifier of 128 characters at most | `InvalidCourse` |
| A price is 1 to 10¹² minor units of a currency named by its ISO 4217 code; a free course has none | `InvalidCourse` |
| A course holds 10 tags at most in a catalogue, each 1 to 50 letters, digits, spaces or `-`, kept lowercase and once | `InvalidCourse` |
| A catalogue offers 1 000 courses at most | `InvalidCourse`: `A catalogue offers 1000 courses at most.` |
| `before` names a course of the catalogue | `InvalidCourse`: `The course "…" is not in the catalogue.` |
| The catalogue and the course exist | `UnknownRecord` |

A course is offered by any number of catalogues, free in one and sold in another. Removing a catalogue removes its
entries; removing a course removes its entries in every catalogue; the learners stay enrolled in either case.

## Public and unit catalogues

`shownTo()` gives the catalogues shown to a learner, by title: the public ones, and the ones of the units whose
catalogues reach the learner; a visitor signed out, null, sees the public ones. `shown()` gives one catalogue, and
throws `UnknownRecord` for a catalogue not shown to the learner, as for one that does not exist.

```php
$catalogues->shownTo($learner);                 // list<Catalogue>
$catalogues->shown($catalogueId, $learner);     // Catalogue, or UnknownRecord
```

The units come from the `LearnerUnits` port: the units the learner belongs to and the units above them, so that a
member of the eastern branch sees the catalogue of the sales department above it. With phil/organizations-bundle
registered, `OrganizationsLearnerUnits` answers it: for each organization the learner is a member of — the learner's
identifier naming the member —, `UnitTree::unitIdsOf()` with the units above. A unit catalogue names the unit's
identifier in that bundle, and follows its tree: a unit moved under another department reaches that department's
catalogue at once, an archived unit reaches none.

The catalogue of a unit reaching the units below it is configuration: with
`phil_courses.catalogues.units_below: false`, `OrganizationsLearnerUnits` names the units the learner belongs to only,
and a catalogue of the sales department is shown to the members of that department, not to those of its eastern
branch.

```yaml
phil_courses:
    catalogues:
        units_below: false      # the catalogue of a unit for its own members only
```
 An application without it names its own `LearnerUnits` service in
`phil_courses.catalogues.learner_units`; without any, the learners see the public catalogues only. The units of a
learner are read once per request, 500 at most.

## Listing a catalogue

`browse()` gives a page of a catalogue as a `CatalogueQuery` lists it:

```php
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;

$page = $catalogues->browse($catalogueId, $learner, CatalogueQuery::of(
    text: 'weld',
    tag: 'safety',
    price: PriceFilter::Free,
    sort: CatalogueSort::Title,
    language: $request->getLocale(),
    page: 2,
));

$page->items;   // list<CatalogueItem>, 20 at most
$page->total;   // how many courses the query kept
$page->pages;   // how many pages, 1 at least
$page->tags;    // every tag of the catalogue, sorted
```

| Part of the query | What it keeps |
|---|---|
| `text` | The courses whose title or description holds the words, whatever their case; 100 characters at most |
| `tag` | The courses with that tag |
| `price` | `any`, `free` — the courses without a price — or `paid` |
| `sort` | `placed`, the order of the catalogue; `title`, by title, numbers in their natural order; `newest`, the newest course first; `price`, the free courses first, then by currency and amount. Courses that compare equal keep the order of the catalogue |
| `language` | With a language, a group of [equivalent courses](equivalent-courses.md) is listed once: the course of that very tag, else the first of its primary language, else the first placed. Without one, every course |
| `page` | The page, from 1 to 1 000; a page beyond the last reads as the last |

`CatalogueQuery::fromQuery()` reads the query string of a page — `q`, `tag`, `price`, `sort` and `page` — and a value
that is not one of these reads as the default, so that an address typed by hand still lists the catalogue;
`parameters()` writes the query back, its defaults left out, for the links between pages.

Each `CatalogueItem` holds the course, its entry and where the learner stands: `enrollment`, their enrollment in it;
`completed`, whether they completed it or an equivalent course, as `Completions` answers — so that a recertification
that started the enrollment again shows it not completed; `assigned`, whether it is assigned to them as
[mandatory training](mandatory-training.md) not completed yet. A visitor signed out stands nowhere.

A listing reads the courses of the catalogue, their entries and their groups of equivalent courses in one query, then
a few queries for each course of the page.

## Enrolling from a catalogue

```php
$enrollment = $catalogues->enroll($catalogueId, $courseId, $learner);
```

`enroll()` enrolls the learner in a course of a catalogue shown to them, or gives back the enrollment they have:

| Case | Answer |
|---|---|
| The catalogue is not shown to the learner | `UnknownRecord` |
| The course is not in the catalogue | `EnrollmentRefused`: `This course is not in the catalogue.` |
| The learner is enrolled already | Their enrollment |
| The course is not open to self-enrollment | `EnrollmentRefused`: `This course is not open to self-enrollment.` |
| The course has a price in the catalogue | `EnrollmentRefused`, with `purchase` true: the learner buys it |
| Otherwise | A new enrollment, with `LearnerEnrolled` |

A course open to self-enrollment in a catalogue is enrolled in from that catalogue even when `phil_courses.self_enrollment`
is false: that option opens the page of every course, a catalogue opens the courses it lists. A course whose entry is
not open to self-enrollment is listed for information, and the application enrolls the learners.

## Courses sold through phil/commerce-bundle

A course with a price in a catalogue is sold, and phil/commerce-bundle stays optional: with it registered, the bundle
registers `CourseSellables`, the `SellableResolver` of the references of type `course`, and `CoursePurchases`, a
listener of `OrderPurchased`.

1. The learner asks for the course on the catalogue page. The page calls the application's `CourseCheckout`, named in
   `phil_courses.catalogues.checkout`, which adds `$entry->sellable()` — `course:<catalogue>/<course>` — to the
   learner's cart, an order whose customer is the learner's identifier, and answers the address of the cart; the page
   redirects there. Without a checkout, the courses with a price show their price and no form.
2. The store asks `CourseSellables` what the reference sells: the course, described by its title, at its price in that
   catalogue, under the tax category `phil_courses.catalogues.tax_category`, `standard` by default. A course free in
   that catalogue, or no longer in it, is nothing sold.
3. Once the order is paid in full, phil/commerce-bundle dispatches `OrderPurchased`. `CoursePurchases` reads the order,
   and enrolls its customer in the course of each line of type `course`, through `Catalogues::grantPurchase()`. An order
   without a customer, or whose customer is no learner identifier, enrolls nobody; a line whose course is no longer sold
   in its catalogue is left; a course paid twice keeps one enrollment.

An application that sells otherwise calls `grantPurchase()` itself once the payment is confirmed: it refuses a course
that is not in the catalogue or is free there.

## The pages

`config/routes.php` holds three routes:

| Route | Path | What it does |
|---|---|---|
| `phil_courses_catalogues` | `GET /catalogues` | The catalogues shown to the visitor, the ones of their units marked |
| `phil_courses_catalogue` | `GET /catalogues/{catalogue}` | One catalogue: the form of its filters and order, sent by GET, its courses with their price, tags and where the learner stands, and the links to the other pages; 404 for a catalogue not shown |
| `phil_courses_catalogue_enroll` | `POST /catalogues/{catalogue}/{course}/enroll` | Enrolls the learner in a free course and sends them to its outline, or sends them to the checkout for a course with a price; 403 signed out, 400 without the CSRF token, 409 for a course not open to it |

The pages answer a visitor signed out with the public catalogues; the form of each course is shown to a learner signed
in, not enrolled yet, for a course open to self-enrollment that is free or sold through a checkout. They draw the recipes
`Button`, `Badge`, `Input` and `NativeSelect` of the kit, and write a price with `phil_courses_price()`, as the locale of
the request writes it with `ext-intl`, with the decimals `symfony/intl` gives the currency. The texts are in English and
French, in the domain `phil_courses` under `catalogue.*`.

The catalogues hold no personal data: they need no privacy module. The enrollments they make are the ones of the
[course model](course-model.md), exported and erased with them.
