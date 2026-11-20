<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the measures of the archived ProseMirror trial: a page with the block
 * editor, its controller, modules and stylesheet from the bundle, Stimulus from where it is installed, and a document
 * of `?blocks=` paragraphs. `window.mounted` is set once the first rich field can be edited.
 */

$package = dirname(__DIR__, 2);
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? (string) strtok($uri, '?') : '/';
$modules = __DIR__.'/node_modules';

$vendor = [
    '/vendor/stimulus.js' => $modules.'/@hotwired/stimulus/dist/stimulus.js',
];

$file = $vendor[$path] ?? null;

if (null === $file && 1 === preg_match('~^/assets/((?:src/(?:lib/)?|styles/)[a-z0-9_-]++\.(?:js|css))$~', $path, $found)) {
    $file = $package.'/assets/'.$found[1];
}

if (null !== $file) {
    if (!is_file($file)) {
        http_response_code(404);

        return true;
    }

    header('Content-Type: '.(str_ends_with($file, '.css') ? 'text/css' : 'text/javascript'));
    readfile($file);

    return true;
}

$count = max(1, min(2000, (int) (is_string($_GET['blocks'] ?? null) ? $_GET['blocks'] : '1')));
$blocks = [];

for ($index = 0; $index < $count; ++$index) {
    $blocks[] = ['id' => sprintf('b%04d', $index), 'type' => 'paragraph', 'data' => ['text' => 1 === $count ? '' : 'Paragraph <b>'.$index.'</b> with <i>some</i> text.']];
}

$document = json_encode(['time' => 0, 'blocks' => $blocks, 'version' => '2.31.7'], \JSON_THROW_ON_ERROR);
$config = json_encode(['locale' => 'en'], \JSON_THROW_ON_ERROR);
$imports = json_encode(['imports' => [
    '@hotwired/stimulus' => '/vendor/stimulus.js',
]], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Block editor</title>
    <link rel="stylesheet" href="/assets/styles/block-editor.css">
    <script>
        window.__errors = [];
        addEventListener('error', (event) => window.__errors.push(String(event.message)));
        addEventListener('unhandledrejection', (event) => window.__errors.push(String(event.reason?.stack ?? event.reason)));
    </script>
    <script type="importmap"><?php echo $imports; ?></script>
    <script type="module">
        import { Application } from '@hotwired/stimulus';
        import Editor from '/assets/src/editor_controller.js';

        window.started = performance.now();
        new MutationObserver((records, observer) => {
            if (null !== document.querySelector('.pbe-rich[contenteditable]')) {
                window.mounted = performance.now();
                observer.disconnect();
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
        window.application = Application.start();
        window.application.register('phil--block-editor-bundle--editor', Editor);
        document.documentElement.dataset.started = 'true';
    </script>
</head>
<body>
    <form>
        <label id="body_label">Body</label>
        <div class="phil-block-editor" data-controller="phil--block-editor-bundle--editor"
             data-phil--block-editor-bundle--editor-config-value="<?php echo htmlspecialchars($config, \ENT_QUOTES); ?>">
            <input type="hidden" id="body" value="<?php echo htmlspecialchars($document, \ENT_QUOTES); ?>"
                   data-phil--block-editor-bundle--editor-target="input">
            <div class="phil-block-editor__canvas" data-phil--block-editor-bundle--editor-target="canvas"
                 aria-labelledby="body_label"></div>
            <div class="phil-block-editor__status" data-phil--block-editor-bundle--editor-target="status"
                 role="status" aria-live="polite"></div>
        </div>
    </form>
</body>
</html>
<?php

return true;
