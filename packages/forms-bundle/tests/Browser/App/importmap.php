<?php

declare(strict_types=1);

/*
 * The import map of the browser suite's application: its entry point, Stimulus from the suite's own npm install, and
 * the loader of StimulusBundle, which imports the controllers that assets/controllers.json enables.
 */
return [
    'app' => ['path' => 'app.js', 'entrypoint' => true],
    '@hotwired/stimulus' => ['path' => 'stimulus/stimulus.js'],
    '@symfony/stimulus-bundle' => ['path' => '@symfony/stimulus-bundle/loader.js'],
];
