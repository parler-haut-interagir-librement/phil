<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

use Closure;

/**
 * The run-time API of one session, as the content calls it: `initialize()`, `getValue()`, `setValue()`, `commit()`,
 * `terminate()`, and the last error with its message and diagnostic. Each call answers as SCORM 1.2's
 * `LMSInitialize()`… or SCORM 2004's `Initialize()`… would: `"true"` or `"false"`, the value read, and an error code
 * of its version — a call before `initialize()` or after `terminate()`, an argument that is not the empty string.
 *
 * The browser runs the same API in the content's window; this one checks the same rules on the server, in tests, or
 * for a platform that runs the content elsewhere. `commit()` and `terminate()` call the hooks given, which keep the data.
 */
final class ScormSession
{
    private SessionState $state = SessionState::NotInitialized;

    private RuntimeResult $last;

    /**
     * @param ?Closure(CmiData): bool $onCommit    keeps the data; false when it could not
     * @param ?Closure(CmiData): bool $onTerminate keeps the data and ends the session; false when it could not
     */
    public function __construct(
        public readonly CmiData $data,
        private readonly ?Closure $onCommit = null,
        private readonly ?Closure $onTerminate = null,
    ) {
        $this->last = new RuntimeResult();
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    public function initialize(string $argument = ''): string
    {
        $is12 = ScormVersion::Scorm12 === $this->data->version;

        return $this->answer(match (true) {
            '' !== $argument => new RuntimeResult('', $is12 ? ScormError::INVALID_ARGUMENT_12 : ScormError::GENERAL_ARGUMENT),
            SessionState::Running === $this->state => new RuntimeResult('', $is12 ? ScormError::GENERAL : ScormError::ALREADY_INITIALIZED),
            SessionState::Terminated === $this->state => new RuntimeResult('', $is12 ? ScormError::GENERAL : ScormError::TERMINATED),
            default => $this->start(),
        });
    }

    public function terminate(string $argument = ''): string
    {
        $is12 = ScormVersion::Scorm12 === $this->data->version;

        $result = match (true) {
            '' !== $argument => new RuntimeResult('', $is12 ? ScormError::INVALID_ARGUMENT_12 : ScormError::GENERAL_ARGUMENT),
            SessionState::NotInitialized === $this->state => new RuntimeResult('', $is12 ? ScormError::NOT_INITIALIZED_12 : ScormError::TERMINATION_BEFORE_INITIALIZATION),
            SessionState::Terminated === $this->state => new RuntimeResult('', $is12 ? ScormError::NOT_INITIALIZED_12 : ScormError::TERMINATION_AFTER_TERMINATION),
            default => null,
        };

        if ($result instanceof RuntimeResult) {
            return $this->answer($result);
        }

        if ($this->onTerminate instanceof Closure && !($this->onTerminate)($this->data)) {
            return $this->answer(new RuntimeResult('', $is12 ? ScormError::GENERAL : ScormError::TERMINATION_FAILED));
        }

        $this->state = SessionState::Terminated;

        return $this->answer(new RuntimeResult('true'));
    }

    public function getValue(string $element): string
    {
        $refused = $this->refused(ScormError::RETRIEVE_BEFORE_INITIALIZATION, ScormError::RETRIEVE_AFTER_TERMINATION);
        $this->last = $refused ?? $this->data->get($element);

        return $this->last->value;
    }

    public function setValue(string $element, mixed $value): string
    {
        $refused = $this->refused(ScormError::STORE_BEFORE_INITIALIZATION, ScormError::STORE_AFTER_TERMINATION);

        return $this->answer($refused ?? $this->written($this->data->set($element, $value)));
    }

    public function commit(string $argument = ''): string
    {
        $is12 = ScormVersion::Scorm12 === $this->data->version;

        if ('' !== $argument) {
            return $this->answer(new RuntimeResult('', $is12 ? ScormError::INVALID_ARGUMENT_12 : ScormError::GENERAL_ARGUMENT));
        }

        $refused = $this->refused(ScormError::COMMIT_BEFORE_INITIALIZATION, ScormError::COMMIT_AFTER_TERMINATION);

        if ($refused instanceof RuntimeResult) {
            return $this->answer($refused);
        }

        if ($this->onCommit instanceof Closure && !($this->onCommit)($this->data)) {
            return $this->answer(new RuntimeResult('', $is12 ? ScormError::GENERAL : ScormError::GENERAL_COMMIT));
        }

        return $this->answer(new RuntimeResult('true'));
    }

    public function getLastError(): string
    {
        return (string) $this->last->error;
    }

    public function getErrorString(string $code): string
    {
        return 1 === preg_match('/^\d{1,3}$/D', $code) ? ScormError::message($this->data->version, (int) $code) : '';
    }

    public function getDiagnostic(string $code = ''): string
    {
        if ('' === $code || (string) $this->last->error === $code) {
            return $this->last->diagnostic;
        }

        return $this->getErrorString($code);
    }

    private function start(): RuntimeResult
    {
        $this->state = SessionState::Running;

        return new RuntimeResult('true');
    }

    private function refused(int $before, int $after): ?RuntimeResult
    {
        $is12 = ScormVersion::Scorm12 === $this->data->version;

        return match ($this->state) {
            SessionState::NotInitialized => new RuntimeResult('', $is12 ? ScormError::NOT_INITIALIZED_12 : $before, 'The session is not initialized.'),
            SessionState::Terminated => new RuntimeResult('', $is12 ? ScormError::NOT_INITIALIZED_12 : $after, 'The session is terminated.'),
            SessionState::Running => null,
        };
    }

    private function written(RuntimeResult $result): RuntimeResult
    {
        return $result->failed() ? $result : new RuntimeResult('true');
    }

    /**
     * The boolean answer of a call: `"true"`, or `"false"` with the error kept for `getLastError()`.
     */
    private function answer(RuntimeResult $result): string
    {
        $this->last = $result;

        return $result->failed() ? 'false' : 'true';
    }
}
