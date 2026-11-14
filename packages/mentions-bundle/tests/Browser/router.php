<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the browser suite: a page with a text area that takes the mention
 * controller, the controller's modules and stylesheet from the bundle, Stimulus from where it is installed, and a
 * member search that answers as the block editor's does, from three members, refusing a query of "boom".
 */

$package = dirname(__DIR__, 2);
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? (string) strtok($uri, '?') : '/';

$assets = [
    '/assets/stimulus.js' => __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
    '/assets/mention_controller.js' => $package.'/assets/src/mention_controller.js',
    '/assets/lib/api.js' => $package.'/assets/src/lib/api.js',
    '/assets/lib/query.js' => $package.'/assets/src/lib/query.js',
    '/assets/mentions.css' => $package.'/assets/styles/mentions.css',
];

if (isset($assets[$path])) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript'));
    readfile($assets[$path]);

    return true;
}

if ('/members' === $path) {
    $query = mb_strtolower(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');

    if ('1' !== ($_SERVER['HTTP_X_PHIL_BLOCK_EDITOR'] ?? null) || 'boom' === $query) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo '{"error":"refused"}';

        return true;
    }

    $members = [
        ['reference' => 'user:1', 'label' => 'Ada Lovelace', 'url' => '/members/ada', 'hint' => '@ada'],
        ['reference' => 'user:4', 'label' => 'Adèle Exarchopoulos', 'url' => null, 'hint' => '@adele'],
        ['reference' => 'user:2', 'label' => 'Bob Marley', 'url' => null, 'hint' => '@bob'],
    ];
    $items = array_values(array_filter($members, static fn (array $member): bool => str_starts_with(substr($member['hint'], 1), $query) || str_starts_with(mb_strtolower($member['label']), $query)));
    header('Content-Type: application/json');
    echo json_encode(['items' => $items], \JSON_THROW_ON_ERROR);

    return true;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mentions</title>
    <link rel="stylesheet" href="/assets/mentions.css">
    <script type="importmap">{"imports": {"@hotwired/stimulus": "/assets/stimulus.js"}}</script>
    <script type="module">
        import { Application } from '@hotwired/stimulus';
        import Mention from '/assets/mention_controller.js';

        window.chosen = [];
        document.addEventListener('phil--mentions-bundle--mention:chosen', (event) => window.chosen.push(event.detail));
        window.application = Application.start();
        window.application.register('phil--mentions-bundle--mention', Mention);
        document.documentElement.dataset.started = 'true';
    </script>
</head>
<body>
    <form style="position: relative">
        <label for="body">Comment</label>
        <textarea id="body" name="body" rows="4" cols="60"
                  data-controller="phil--mentions-bundle--mention"
                  data-phil--mentions-bundle--mention-url-value="/members"
                  data-phil--mentions-bundle--mention-delay-value="20"></textarea>
        <button type="button" id="elsewhere">Elsewhere</button>
    </form>
</body>
</html>
<?php

return true;
