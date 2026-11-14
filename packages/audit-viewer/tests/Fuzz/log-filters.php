<?php

declare(strict_types=1);

/*
 * The filters of the log on query strings nobody wrote: they read into a query and no refusal, or into refusals and no
 * query, each refusal a field the form has; a value that is not text is refused by the request itself. Nothing else is
 * thrown, and nothing warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditViewer\Infrastructure\Http\AuditFilters;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\InputBag;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    parse_str($input, $parameters);

    try {
        $filters = AuditFilters::read(new InputBag($parameters), new DateTimeZone('America/Toronto'), 50);
    } catch (BadRequestException) {
        // A field given as a list, not as text: the request refuses it.
        return;
    }

    $known = [...AuditFilters::FIELDS, 'cursor'];

    if ($filters->query instanceof AuditQuery !== ([] === $filters->errors) || [] !== array_diff($filters->errors, $known)) {
        throw new LogicException('The filters read into both a query and refusals, or into neither.');
    }
});
$config->setMaxLen(1024);
