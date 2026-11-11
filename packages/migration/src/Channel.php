<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * Where and how a release is announced: a chat transport, the locale the announcement is read in, what it tells —
 * the notes or the news — and the shape of its messages, cut to fit the longest one the transport takes.
 *
 * `{counter}` in the title or in the title of the messages that follow becomes " (2/3)" when the release takes more
 * than one message, and nothing otherwise. `{title}` in the heading of a piece of news is its title, `{url}` in its
 * link its address.
 */
final readonly class Channel
{
    public const int MIN_LENGTH = 100;

    public string $transport;

    public function __construct(
        string $transport,
        public string $locale,
        public Content $content = Content::Notes,
        public string $title = '',
        public string $continued = '',
        public string $heading = '{title}',
        public string $link = '{url}',
        public int $maxLength = 2000,
    ) {
        $this->transport = trim($transport);

        if ('' === $this->transport) {
            throw InvalidAnnouncement::because('A channel names the chat transport it announces on.');
        }

        Locale::check($locale);

        if ($maxLength < self::MIN_LENGTH) {
            throw InvalidAnnouncement::because(\sprintf('A message is %d characters or more; %d is too short.', self::MIN_LENGTH, $maxLength));
        }

        foreach ([$title, $continued] as $header) {
            if (mb_strlen($header) > intdiv($maxLength, 2)) {
                throw InvalidAnnouncement::because(\sprintf('A title takes half of a message at most, %d characters; "%s" is longer.', intdiv($maxLength, 2), $header));
            }
        }

        if (!str_contains($heading, '{title}')) {
            throw InvalidAnnouncement::because(\sprintf('The heading of a piece of news holds its {title}; "%s" does not.', $heading));
        }
    }

    /**
     * The messages that announce a release on this channel, none when it has nothing in this channel's locale.
     *
     * @return list<string>
     */
    public function messages(Release $release): array
    {
        return Messages::split($this->lines($release), $this->title, $this->continued, $this->maxLength);
    }

    /**
     * @return list<string>
     */
    private function lines(Release $release): array
    {
        if (Content::Notes === $this->content) {
            return $release->notesIn($this->locale);
        }

        $lines = [];

        foreach ($release->newsIn($this->locale) as $news) {
            $lines[] = str_replace('{title}', $news->title, $this->heading);
            array_push($lines, ...$news->lines);

            if (null !== $news->url && '' !== $this->link) {
                $lines[] = str_replace('{url}', $news->url, $this->link);
            }
        }

        return $lines;
    }
}
