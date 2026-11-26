<?php

declare(strict_types=1);

/*
 * The query string of a catalogue page on bytes nobody wrote: it is read as a query of the catalogue whatever it
 * holds; nothing is thrown, the words stay within their bound and valid UTF-8, the tag is one an entry keeps, the page
 * within its bound, and the query string it writes back reads as the same query.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    parse_str($input, $values);
    $query = CatalogueQuery::fromQuery($values, $input);

    if (null !== $query->text && (mb_strlen($query->text) > CatalogueQuery::TEXT_LENGTH || !mb_check_encoding($query->text, 'UTF-8'))) {
        throw new LogicException('The words of a query outgrow their bound.');
    }

    if (null !== $query->tag && CatalogueEntry::tag($query->tag) !== $query->tag) {
        throw new LogicException('The tag of a query is not one an entry keeps.');
    }

    if ($query->page < 1 || $query->page > CatalogueQuery::MAX_PAGE) {
        throw new LogicException('The page of a query is out of its bound.');
    }

    $again = CatalogueQuery::fromQuery($query->parameters(), $query->language);

    if ($again->parameters() !== $query->parameters()) {
        throw new LogicException('The query string written back reads as another query.');
    }
});
$config->setMaxLen(1024);
