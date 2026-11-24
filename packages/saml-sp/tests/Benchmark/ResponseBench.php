<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Benchmark;

use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\RecordingGuard;
use Phil\SamlSp\Tests\Support\Stage;
use Symfony\Component\Clock\MockClock;

/**
 * The hot paths of a sign-in: the request written and signed for the redirect, and the response of the identity
 * provider read, verified and, when it is encrypted, decrypted.
 */
final readonly class ResponseBench
{
    private ResponseProcessor $processor;

    private MessageFactory $factory;

    private ServiceProvider $sp;

    private IdentityProvider $entra;

    private IdentityProvider $okta;

    private string $signed;

    private string $encrypted;

    private string $both;

    public function __construct()
    {
        $clock = new MockClock('2026-11-19 12:01:00 UTC');
        $this->processor = new ResponseProcessor($clock, new RecordingGuard());
        $this->factory = new MessageFactory($clock);
        $this->sp = Stage::sp();
        $this->entra = Stage::entra();
        $this->okta = Stage::okta();
        $this->signed = Stage::fixture('entra-signed-assertion.xml');
        $this->encrypted = Stage::fixture('entra-encrypted.xml');
        $this->both = Stage::fixture('okta-signed.xml');
    }

    public function benchAuthnRequestOnTheRedirect(): void
    {
        MessageSender::send($this->factory->authnRequest($this->sp, $this->entra), Keys::pair('sp'));
    }

    public function benchSignedAssertion(): void
    {
        $this->processor->process($this->signed, $this->entra, $this->sp, Stage::REQUEST);
    }

    public function benchSignedResponseAndAssertion(): void
    {
        $this->processor->process($this->both, $this->okta, $this->sp, Stage::REQUEST);
    }

    public function benchEncryptedAssertion(): void
    {
        $this->processor->process($this->encrypted, $this->entra, $this->sp, Stage::REQUEST);
    }
}
