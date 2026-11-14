<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Http;

use Phil\DataTableBundle\Application\Definition\Export;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Phil\DataTableBundle\Application\Export\ExportRows;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Infrastructure\Export\TableExports;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The export of a table, a GET that changes nothing: its signed context opens the table, which asks its voter, then
 * the export's own; its sort, filters and columns are checked against the declaration as the table's are. The file
 * is streamed as its rows are fetched, never cached; past the most rows the export allows, a page says so in a `422`
 * before anything is written.
 */
final readonly class TableExportController
{
    public function __construct(
        private Tables $tables,
        private TableGuard $guard,
        private ExportRows $rows,
        private TableExports $exports,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function export(Request $request, string $table, string $format): Response
    {
        $open = $this->tables->open($table, $this->guard->opened($table, $request->query->getString('context'), $request->query->getString('seal')));
        $export = $open->definition->export;
        $written = Format::tryFrom($format);

        if (!$export instanceof Export || !$written instanceof Format || !$this->exports->writers()->has($written)) {
            throw NotFound::export($table, $format);
        }

        if (!$this->exports->allows($open)) {
            throw AccessDenied::to(\sprintf('the export of the table "%s"', $table), (string) $export->attribute);
        }

        $rows = $this->rows->rows($open, new TableState($request->query->getString('sort'), filters: $request->query->all('filters'), columns: $request->query->all('columns')));

        if ($rows->total > $export->maxRows) {
            $message = $this->translator->trans('table.export.too_many', ['%count%' => $rows->total, '%max%' => $export->maxRows], TableOperations::DOMAIN);

            return new Response($this->twig->render('@PhilDataTable/table/stopped.html.twig', ['messages' => [$message], 'back' => '/']), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sheet = new Sheet($rows->title, $rows->headings, $rows->rows);
        $writers = $this->exports->writers();

        return new StreamedResponse(
            static function () use ($writers, $written, $sheet): void {
                $output = fopen('php://output', 'w');
                \assert(\is_resource($output));
                $writers->write($written, $sheet, $output);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => $written->mediaType(),
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $written->fileName($table)),
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
