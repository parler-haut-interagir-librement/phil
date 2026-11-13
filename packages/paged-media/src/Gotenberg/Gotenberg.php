<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\GotenbergFailure;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\ServiceUnavailable;
use Phil\PagedMedia\Gotenberg\Internal\Multipart;
use Phil\PagedMedia\Gotenberg\Internal\Url;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Read\Attachment;
use Phil\PagedMedia\Gotenberg\Read\DocumentInformation;
use Phil\PagedMedia\Gotenberg\Read\FormField;
use Phil\PagedMedia\Gotenberg\Read\JsonValue;
use Phil\PagedMedia\Gotenberg\Read\PdfInfo;
use Phil\PagedMedia\Gotenberg\Read\ValidationReport;
use Phil\PagedMedia\Gotenberg\Stage\Bookmark;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A client of Gotenberg 9 on Symfony HttpClient: the PDF stages, in a list or alone, the LibreOffice conversion, and
 * the routes that read a PDF. Every request is checked before it is sent — a stage's options, the order of a list,
 * the files — and every answer that is not a success becomes a `GotenbergFailure`.
 *
 * The Chromium routes are not covered: HTML and CSS are laid out by a paged-media engine of the application's choice.
 */
final readonly class Gotenberg
{
    private string $url;

    /**
     * @param string     $url     Gotenberg's base URL, such as `http://gotenberg:3000`, with its root path if it has one
     * @param float|null $timeout the longest a request may take, in seconds; the client's default when null
     */
    public function __construct(
        private HttpClientInterface $client,
        string $url,
        private ?BasicAuth $auth = null,
        private ?float $timeout = null,
    ) {
        $this->url = rtrim(Url::http($url, "Gotenberg's URL"), '/');
    }

    /**
     * Runs a list of stages on the PDFs, on `/forms/pdf/pipe`.
     */
    public function pipe(Files $files, Pipeline $pipeline, RequestOptions $options = new RequestOptions()): Result
    {
        return $this->produce('/forms/pdf/pipe', $files, ['stages' => $pipeline->toJson()], $pipeline->files(), $options);
    }

    /**
     * Runs one stage on the PDFs, on its own route, which takes the options as form fields.
     */
    public function run(Files $files, Stage $stage, RequestOptions $options = new RequestOptions()): Result
    {
        return $this->produce($stage->operation()->route(), $files, array_map($this->field(...), $stage->options()), $stage->files(), $options);
    }

    /**
     * Converts office documents to PDF with LibreOffice, then runs the stages on the PDFs when a list is given.
     */
    public function convert(Files $documents, LibreOfficeOptions $options = new LibreOfficeOptions(), ?Pipeline $pipeline = null, RequestOptions $request = new RequestOptions()): Result
    {
        $fields = $options->fields();

        if ($pipeline instanceof Pipeline) {
            $fields['stages'] = $pipeline->toJson();
        }

        return $this->produce('/forms/libreoffice/convert', $documents, $fields, $pipeline?->files() ?? [], $request);
    }

    /**
     * The page count, the version, and whether each PDF is encrypted, tagged, linearized or holds form fields.
     *
     * @return array<string, PdfInfo> by file name
     */
    public function info(Files $files, #[SensitiveParameter] ?string $password = null): array
    {
        return array_map(PdfInfo::fromJson(...), $this->read('/forms/pdf/info', $files, $password)->members());
    }

    /**
     * @return array<string, DocumentInformation> by file name
     */
    public function metadata(Files $files, #[SensitiveParameter] ?string $password = null): array
    {
        return array_map(DocumentInformation::fromJson(...), $this->read('/forms/pdf/metadata', $files, $password)->members());
    }

    /**
     * @return array<string, list<Bookmark>> by file name
     */
    public function bookmarks(Files $files, #[SensitiveParameter] ?string $password = null): array
    {
        return array_map(static fn (JsonValue $list): array => array_map(Bookmark::fromJson(...), $list->items()), $this->read('/forms/pdf/bookmarks', $files, $password)->members());
    }

    /**
     * @return array<string, list<Attachment>> by file name
     */
    public function attachments(Files $files, #[SensitiveParameter] ?string $password = null): array
    {
        return array_map(static fn (JsonValue $list): array => array_map(Attachment::fromJson(...), $list->items()), $this->read('/forms/pdf/attachments', $files, $password)->members());
    }

    /**
     * The form fields of each PDF, of the pages given or of all.
     *
     * @return array<string, list<FormField>> by file name
     */
    public function form(Files $files, #[SensitiveParameter] ?string $password = null, Pages|string|null $pages = null): array
    {
        $pages = \is_string($pages) ? Pages::of($pages) : $pages;

        return array_map(static fn (JsonValue $list): array => array_map(FormField::fromJson(...), $list->items()), $this->read('/forms/pdf/form', $files, $password, $pages instanceof Pages ? ['pages' => $pages->value] : [])->members());
    }

    /**
     * Checks each PDF against a profile, or a PDF/A profile and a PDF/UA one together.
     *
     * @param Profile|list<Profile> $profile
     *
     * @return array<string, ValidationReport> by file name
     */
    public function validate(Files $files, Profile|array $profile, #[SensitiveParameter] ?string $password = null): array
    {
        $profiles = array_map(static fn (Profile $profile): string => $profile->value, Profile::checked($profile));
        $field = 1 === \count($profiles) ? $profiles[0] : json_encode($profiles, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        return array_map(ValidationReport::fromJson(...), $this->read('/forms/pdf/validate', $files, $password, ['profile' => $field])->members());
    }

    /**
     * The version of Gotenberg, such as `9.1.0`.
     */
    public function version(): string
    {
        return trim($this->content($this->answer($this->send('GET', '/version', null, []))));
    }

    /**
     * @param array<string, string> $fields
     * @param list<InputFile>       $stageFiles
     */
    private function produce(string $route, Files $files, array $fields, array $stageFiles, RequestOptions $options): Result
    {
        $response = $this->answer($this->send('POST', $route, $this->body($files, $fields, $stageFiles), $options->headers()));

        return Result::from($this->client, $response);
    }

    /**
     * @param array<string, string> $fields
     */
    private function read(string $route, Files $files, ?string $password, array $fields = []): JsonValue
    {
        if (null !== $password) {
            $fields['password'] = $password;
        }

        $response = $this->answer($this->send('POST', $route, $this->body($files, $fields, []), []));

        return JsonValue::decode($this->content($response));
    }

    /**
     * @param array<string, string> $fields
     * @param list<InputFile>       $stageFiles
     */
    private function body(Files $files, array $fields, array $stageFiles): Multipart
    {
        if ($files->isEmpty()) {
            throw InvalidRequest::because('A request works on one file at least: uploaded, in an archive, or downloaded.');
        }

        $multipart = new Multipart();

        foreach ($fields as $name => $value) {
            $multipart->field($name, $value);
        }

        if ([] !== $files->downloads) {
            $multipart->field('downloadFrom', json_encode(array_map(static fn (Download $download): array => $download->toArray(), $files->downloads), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        }

        $sent = [];

        foreach ([...$files->files, ...$stageFiles] as $file) {
            if (isset($sent[$file->name])) {
                if ($sent[$file->name]->sameAs($file)) {
                    continue;
                }

                throw InvalidRequest::because(\sprintf('Two different files are named "%s"; Gotenberg tells files apart by their names.', $file->name));
            }

            $sent[$file->name] = $file;
            $multipart->file('files', $file);
        }

        foreach ($files->archives as $archive) {
            $multipart->file('archives', $archive);
        }

        return $multipart;
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, string $route, ?Multipart $body, array $headers): ResponseInterface
    {
        $options = ['headers' => $headers];

        if ($this->auth instanceof BasicAuth) {
            $options['auth_basic'] = $this->auth->credentials();
        }

        if ($body instanceof Multipart) {
            $options['headers']['Content-Type'] = $body->contentType();
            $options['body'] = $body->body();
        }

        if (null !== $this->timeout) {
            $options['timeout'] = $this->timeout;
            $options['max_duration'] = $this->timeout;
        }

        return $this->client->request($method, $this->url.$route, $options);
    }

    private function answer(ResponseInterface $response): ResponseInterface
    {
        try {
            $status = $response->getStatusCode();

            if ($status < 300) {
                return $response;
            }

            $trace = $response->getHeaders(false)['gotenberg-trace'][0] ?? null;

            throw GotenbergFailure::fromStatus($status, $response->getContent(false), $trace);
        } catch (ExceptionInterface $exception) {
            throw ServiceUnavailable::unreachable($this->url, $exception);
        }
    }

    private function content(ResponseInterface $response): string
    {
        try {
            return $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw ServiceUnavailable::unreachable($this->url, $exception);
        }
    }

    /**
     * A stage option as a form field: a text as it is, anything else as JSON.
     *
     * @param scalar|array<array-key, mixed> $value
     */
    private function field(mixed $value): string
    {
        return \is_string($value) ? $value : json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
