<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The operations Gotenberg runs, with the rules of their order: which come first or last, which run once, and which
 * may follow them.
 */
enum Operation: string
{
    case Merge = 'merge';
    case Split = 'split';
    case Select = 'select';
    case Delete = 'delete';
    case Insert = 'insert';
    case Rotate = 'rotate';
    case StampText = 'stamp-text';
    case StampImage = 'stamp-image';
    case StampPdf = 'stamp-pdf';
    case SetMetadata = 'set-metadata';
    case SetBookmarks = 'set-bookmarks';
    case SetForm = 'set-form';
    case FlattenForm = 'flatten-form';
    case FlattenTransparency = 'flatten-transparency';
    case ConvertColors = 'convert-colors';
    case Redact = 'redact';
    case Autotag = 'autotag';
    case Attach = 'attach';
    case Detach = 'detach';
    case Optimize = 'optimize';
    case Encrypt = 'encrypt';
    case Decrypt = 'decrypt';
    case Remediate = 'remediate';
    case Sign = 'sign';
    case Timestamp = 'timestamp';
    case Render = 'render';
    case ExtractText = 'extract-text';
    case ExtractAttachments = 'extract-attachments';

    /**
     * Whether the stage comes last: it makes images, text or the embedded files rather than a PDF.
     */
    public function isTerminal(): bool
    {
        return \in_array($this, [self::Render, self::ExtractText, self::ExtractAttachments], true);
    }

    /**
     * Whether a list holds this stage once at most.
     */
    public function runsOnce(): bool
    {
        return \in_array($this, [self::Remediate, self::Encrypt, self::Sign, self::Timestamp], true);
    }

    /**
     * The only stages that may come after this one anywhere later in the list, the terminal ones aside; null when
     * any may.
     *
     * @return list<self>|null
     */
    public function followers(): ?array
    {
        return match ($this) {
            self::Remediate => [self::Encrypt, self::Optimize, self::Sign, self::Timestamp],
            self::Encrypt => [self::Optimize],
            self::Sign, self::Timestamp => [self::Sign, self::Timestamp],
            default => null,
        };
    }

    /**
     * Whether the stage rewrites the file whole, which drops its signatures: Gotenberg refuses it on a signed PDF.
     */
    public function rewrites(): bool
    {
        return \in_array($this, [
            self::Select, self::Delete, self::Insert, self::Merge, self::Split, self::Encrypt, self::Decrypt,
            self::FlattenForm, self::Redact, self::Remediate, self::ConvertColors, self::FlattenTransparency,
            self::Optimize,
        ], true);
    }

    /**
     * The route that runs the stage alone.
     */
    public function route(): string
    {
        return '/forms/pdf/'.$this->value;
    }
}
