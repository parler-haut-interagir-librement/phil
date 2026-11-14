<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Http;

use Phil\DataTableBundle\Application\EditStatus;
use Phil\DataTableBundle\Application\Exception\ActionRefused;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The changes of a browser without JavaScript: a plain form posts here, and comes back to the table it left, or stays
 * on a page that says what stopped the change. Every check of the component is made here too.
 */
final readonly class TableController
{
    public function __construct(
        private Tables $tables,
        private TableOperations $operations,
        private TableGuard $guard,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function action(Request $request, string $table, string $action): Response
    {
        $open = $this->open($request, $table);
        $ids = array_filter($request->request->all('ids'), \is_string(...));

        try {
            $this->operations->perform($open, $action, $ids);
        } catch (ActionRefused $actionRefused) {
            return $this->stopped($request, [$this->translator->trans($actionRefused->getMessage(), [], $open->definition->domain)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($request);
    }

    public function edit(Request $request, string $table, string $column): Response
    {
        $outcome = $this->operations->edit(
            $this->open($request, $table),
            $request->request->getString('row'),
            $column,
            $request->request->getString('value'),
            $request->request->getString('version'),
        );

        if ($outcome->isSaved()) {
            return $this->back($request);
        }

        return $this->stopped($request, $outcome->errors, EditStatus::Conflict === $outcome->status ? Response::HTTP_CONFLICT : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function open(Request $request, string $table): OpenTable
    {
        $this->guard->assertTrusted($request->request->getString('_token'));

        return $this->tables->open($table, $this->guard->opened($table, $request->request->getString('context'), $request->request->getString('seal')));
    }

    private function back(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->backTo($request), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param list<string> $messages
     */
    private function stopped(Request $request, array $messages, int $status): Response
    {
        return new Response($this->twig->render('@PhilDataTable/table/stopped.html.twig', ['messages' => $messages, 'back' => $this->backTo($request)]), $status);
    }

    /**
     * The page to come back to: a path of this site, whatever the form said.
     */
    private function backTo(Request $request): string
    {
        $back = $request->request->getString('back');

        return 1 === preg_match('~^/(?![/\\\\])[^\s\\\\]*$~D', $back) ? $back : '/';
    }
}
