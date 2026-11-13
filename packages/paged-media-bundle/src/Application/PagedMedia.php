<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use Phil\PagedMediaBundle\Application\Exception\NotConfigured;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The native writer and Gotenberg behind one service. A document drawn with the native writer is rendered here, then,
 * when stages are given, sent through Gotenberg — to make it PDF/A, sign it, encrypt it —; PDFs and office documents
 * go to Gotenberg directly; HTML goes to the print engine of the application. Signatures and timestamps take the
 * certificate source and the authority of the configuration, so no key travels in a message.
 *
 * A print announces itself first, by `PrintStarting` when it names its work and `UnnamedPrintStarting` otherwise: a
 * listener that throws stops it.
 */
final readonly class PagedMedia
{
    public function __construct(
        private DocumentTemplates $templates = new DocumentTemplates(),
        private ?Gotenberg $gotenberg = null,
        private ?CertificateSource $certificates = null,
        private ?TimestampAuthority $timestampAuthority = null,
        private SignatureDefaults $signature = new SignatureDefaults(),
        private ?PrintEngine $engine = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Renders a document of the native writer, then runs the stages on it when there are any.
     */
    public function render(Document $document, string $filename = 'document.pdf', ?Pipeline $pipeline = null, ?PrintSubject $subject = null): Output
    {
        $this->announce($subject);

        return $this->renderDocument($document, $filename, $pipeline);
    }

    /**
     * Lays HTML and CSS out with the print engine of the application, then runs the stages on the PDF when there are any.
     */
    public function printHtml(PrintableHtml $page, string $filename = 'document.pdf', ?Pipeline $pipeline = null, ?PrintSubject $subject = null): Output
    {
        $this->announce($subject);
        $pdf = ($this->engine ?? throw NotConfigured::printEngine())->pdf($page);

        if (!$pipeline instanceof Pipeline) {
            return new Output($pdf, $filename);
        }

        return $this->pipe(Files::of(InputFile::fromContents($filename, $pdf)), $pipeline);
    }

    /**
     * Builds the document a template draws from the parameters, then renders it.
     *
     * @param array<string, mixed> $parameters
     */
    public function renderTemplate(string $template, array $parameters = [], string $filename = 'document.pdf', ?Pipeline $pipeline = null, ?PrintSubject $subject = null): Output
    {
        $this->announce($subject);

        return $this->renderDocument($this->templates->get($template)->build($parameters), $filename, $pipeline);
    }

    /**
     * Runs a list of stages on PDFs, through Gotenberg.
     */
    public function process(Files $files, Pipeline $pipeline, ?PrintSubject $subject = null): Output
    {
        $this->announce($subject);

        return $this->pipe($files, $pipeline);
    }

    /**
     * Converts office documents to PDF with LibreOffice, then runs the stages when there are any.
     */
    public function convert(Files $documents, LibreOfficeOptions $options = new LibreOfficeOptions(), ?Pipeline $pipeline = null, ?PrintSubject $subject = null): Output
    {
        $this->announce($subject);

        return Output::fromResult($this->gotenberg()->convert($documents, $options, $pipeline), ($documents->files[0]->name ?? 'document').'.pdf');
    }

    /**
     * A signature with the configured certificate, saying what the configuration says unless an argument says otherwise.
     */
    public function signature(?string $reason = null, ?string $location = null, ?string $contactInfo = null, ?string $name = null, ?bool $pades = null): Sign
    {
        if (!$this->certificates instanceof CertificateSource) {
            throw NotConfigured::signing();
        }

        return Sign::with(
            $this->certificates,
            $reason ?? $this->signature->reason,
            $location ?? $this->signature->location,
            $contactInfo ?? $this->signature->contactInfo,
            $name ?? $this->signature->name,
            $pades ?? $this->signature->pades,
            $this->timestampAuthority,
        );
    }

    /**
     * A document timestamp from the configured authority.
     */
    public function timestamp(): Timestamp
    {
        return new Timestamp($this->timestampAuthority ?? throw NotConfigured::timestamping());
    }

    /**
     * The stages, followed by the configured signature and a document timestamp when asked; null when there are none.
     *
     * @return ($pipeline is null ? Pipeline|null : Pipeline)
     */
    public function finish(?Pipeline $pipeline, bool $sign = false, bool $timestamp = false): ?Pipeline
    {
        $stages = array_filter([$sign ? $this->signature() : null, $timestamp ? $this->timestamp() : null], static fn (?Stage $stage): bool => $stage instanceof Stage);

        if ([] === $stages) {
            return $pipeline;
        }

        return $pipeline instanceof Pipeline ? $pipeline->then(...$stages) : new Pipeline(...$stages);
    }

    /**
     * The Gotenberg client of the configuration.
     */
    public function gotenberg(): Gotenberg
    {
        return $this->gotenberg ?? throw NotConfigured::gotenberg();
    }

    /**
     * The same service, which announces nothing: for a print announced when it was queued, made in a worker.
     */
    public function alreadyAnnounced(): self
    {
        return new self($this->templates, $this->gotenberg, $this->certificates, $this->timestampAuthority, $this->signature, $this->engine);
    }

    private function renderDocument(Document $document, string $filename, ?Pipeline $pipeline): Output
    {
        if (!$pipeline instanceof Pipeline) {
            return new Output($document->render(), $filename);
        }

        return $this->pipe(Files::of(InputFile::fromDocument($filename, $document)), $pipeline);
    }

    private function pipe(Files $files, Pipeline $pipeline): Output
    {
        return Output::fromResult($this->gotenberg()->pipe($files, $pipeline), $files->files[0]->name ?? 'document.pdf');
    }

    private function announce(?PrintSubject $subject): void
    {
        $this->events?->dispatch($subject instanceof PrintSubject ? new PrintStarting($subject) : new UnnamedPrintStarting());
    }
}
