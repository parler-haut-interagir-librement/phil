<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * Where an event was caught: an HTTP request, a message a worker failed to handle, a console command, a log record,
 * a browser, or a call of the application's own.
 */
enum Source: string
{
    case Http = 'http';
    case Messenger = 'messenger';
    case Console = 'console';
    case Log = 'log';
    case Browser = 'browser';
    case Manual = 'manual';
}
