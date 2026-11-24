<?php

declare(strict_types=1);

/*
 * The readers of what an identity provider and a browser send, on text nobody wrote: `SafeXml::load()` within the
 * budget of a message, the response processor on that text as a posted field, the metadata reader, and the query of the
 * redirect binding. Each returns or throws a SamlException; nothing else escapes, and no document beyond the budget is
 * ever returned.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Metadata\IdentityProviderMetadata;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Xml\SafeXml;
use Phil\SamlSp\Xml\XmlBudget;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$budget = new XmlBudget(8192, 16, 512);
$processor = Stage::processor();
$entra = Stage::entra(allowUnsolicited: true);
$sp = Stage::sp();
$reader = new MetadataReader(budget: $budget);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($budget, $processor, $entra, $sp, $reader): void {
    try {
        $document = SafeXml::load($input, $budget);

        if (strlen((string) $document->saveXML()) > 4 * $budget->maxBytes) {
            throw new LogicException('A document grew far beyond its budget.');
        }
    } catch (SamlException) {
    }

    foreach ([
        static fn (): SamlIdentity => $processor->process(base64_encode($input), $entra, $sp, null),
        static fn (): IdentityProviderMetadata => $reader->read($input),
        static fn (): ReceivedMessage => HttpRedirect::receive($input, [], budget: $budget),
    ] as $read) {
        try {
            $read();
        } catch (SamlException) {
        }
    }
});
$config->setMaxLen(8192);
