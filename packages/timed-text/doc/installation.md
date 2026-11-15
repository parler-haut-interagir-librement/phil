# Installation

## Requirements

PHP 8.5 or later, with `ext-dom` and `ext-libxml` — the HTML5 parser that reads HTML transcripts — and `ext-mbstring`,
which replaces the invalid UTF-8 of a file. The library requires `phil/url`, which checks the links and images of
chapters.

## Installing

```sh
composer require phil/timed-text
```

## As Symfony services

The library declares no service. Its readers and writers are immutable and keep no state between calls: one instance
serves every request of a long-running worker. An application declares those it uses, with its own limits:

```php
// config/services.php
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\WebVtt\WebVttReader;

$services->set(Limits::class)->args(['$maxBytes' => 4_194_304, '$maxCues' => 20_000]);
$services->set(WebVttReader::class)->args([service(Limits::class), StylePolicy::Drop]);
$services->set(JsonTranscriptReader::class)->args([service(Limits::class)]);
```

`TranscriptFormat::reader()` and `ChapterFormat::reader()` build a reader for a media type when the format is known only
at run time, such as the `type` of a `<podcast:transcript>`.
