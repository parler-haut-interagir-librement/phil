<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\BigBlueButton;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use JsonException;
use Override;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\Contract\VirtualClassroom;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Http\BoundedHttp;
use Phil\VirtualClassroom\Http\HttpBudget;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroom\Timestamps;
use Phil\VirtualClassroom\Token\JsonWebToken;
use Phil\VirtualClassroom\Webhook;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A BigBlueButton server, through its API: every call carries a checksum, the hash of the call's name, its query and
 * the secret shared with the server, so that the secret itself never travels. A room is opened by `create` — which a
 * running room answers again with its creation time —, joined by a `join` link signed for the role and tied to that
 * creation time, ended by `end`; `getMeetingInfo` says who is in it, `getRecordings` lists its recordings.
 *
 * The server calls back three ways. The end of a room, on `meetingEndedURL`, is a plain GET naming the room: the
 * application's address carries the proof. A recording ready posts `signed_parameters`, a token signed with HS256
 * under the shared secret. The attendance of a session, posted at its end by the learning analytics callback, is
 * read when `analytics` is on, and only with its bearer token, signed under the shared secret, unless
 * `signedAnalytics` is turned off for a server that sends none: the address of that callback is visible to the
 * participants, so an unsigned attendance could be written by any of them.
 */
final readonly class BigBlueButton implements VirtualClassroom
{
    public const int MAX_ATTENDEES = 1000;

    public const int MAX_STAYS = 200;

    public const int MAX_WEBHOOK_BYTES = 1_048_576;

    private string $apiUrl;

    private BoundedHttp $http;

    /**
     * @param Url $apiUrl the address of the API, as `https://bbb.example.com/bigbluebutton/api/`
     *
     * @throws InvalidValue when the address is not https or the secret is shorter than 16 bytes
     */
    public function __construct(
        HttpClientInterface $client,
        Url $apiUrl,
        #[SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
        private ChecksumAlgorithm $algorithm = ChecksumAlgorithm::Sha256,
        HttpBudget $budget = new HttpBudget(),
        private bool $analytics = true,
        private bool $signedAnalytics = true,
    ) {
        if ('https' !== $apiUrl->scheme) {
            throw InvalidValue::because('The API of a BigBlueButton server is reached over https.');
        }

        if (\strlen($secret) < 16) {
            throw InvalidValue::because('The secret shared with a BigBlueButton server is 16 bytes or more.');
        }

        $address = $apiUrl->toString();
        $this->apiUrl = str_ends_with($address, '/') ? $address : $address.'/';
        $this->http = new BoundedHttp($client, $budget);
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return new Capabilities(end: true, presences: true, recordings: true, webhooks: true);
    }

    #[Override]
    public function open(RoomSpec $room): OpenedRoom
    {
        $parameters = [
            'name' => $room->title,
            'meetingID' => $room->id->value,
            'record' => $room->record ? 'true' : 'false',
        ];

        if (null !== $room->maxMinutes) {
            $parameters['duration'] = (string) $room->maxMinutes;
        }

        if ($room->endedUrl instanceof Url) {
            $parameters['meetingEndedURL'] = $room->endedUrl->toString();
        }

        if ($room->eventsUrl instanceof Url && $room->record) {
            $parameters['meta_bbb-recording-ready-url'] = $room->eventsUrl->toString();
        }

        if ($room->eventsUrl instanceof Url && $this->analytics) {
            $parameters['meta_analytics-callback-url'] = $room->eventsUrl->toString();
        }

        $answer = $this->call('create', $parameters, ['idNotUnique']);
        $created = $answer->succeeded() ? $answer->text('/response/createTime') : $this->info($room->id)?->text('/response/createTime');

        if (null === $created || 1 !== preg_match('/^\d{1,20}$/D', $created)) {
            throw InvalidResponse::because('The answer to "create" gives no creation time.');
        }

        return new OpenedRoom($room->id, $room->record, $created);
    }

    #[Override]
    public function joinUrl(OpenedRoom $room, Participant $participant, Role $role): string
    {
        $parameters = [
            'fullName' => $participant->displayName,
            'meetingID' => $room->id->value,
            'role' => Role::Moderator === $role ? 'MODERATOR' : 'VIEWER',
            'userID' => $participant->id->value,
        ];

        if (null !== $room->sessionKey) {
            $parameters['createTime'] = $room->sessionKey;
        }

        return $this->signed('join', $parameters);
    }

    #[Override]
    public function end(RoomId $room): void
    {
        $this->call('end', ['meetingID' => $room->value], ['notFound']);
    }

    #[Override]
    public function presences(RoomId $room): Presences
    {
        $answer = $this->info($room);

        if (!$answer instanceof XmlAnswer) {
            return Presences::gone();
        }

        $participants = [];

        foreach ($answer->elements('/response/attendees/attendee') as $attendee) {
            $participant = ParticipantId::tryFrom($answer->text('userID', $attendee));

            if ($participant instanceof ParticipantId) {
                $participants[$participant->value] = $participant;
            }
        }

        return new Presences('true' === $answer->text('/response/running') ? RoomState::Running : RoomState::Waiting, array_values($participants));
    }

    #[Override]
    public function recordings(RoomId $room): array
    {
        $answer = $this->call('getRecordings', ['meetingID' => $room->value]);
        $recordings = [];

        foreach ($answer->elements('/response/recordings/recording') as $recording) {
            if ($answer->text('meetingID', $recording) !== $room->value) {
                continue;
            }

            foreach ($answer->elements('playback/format', $recording) as $format) {
                $read = $this->recording($answer, $room, $recording, $format);

                if ($read instanceof Recording) {
                    $recordings[] = $read;
                }
            }
        }

        return $recordings;
    }

    #[Override]
    public function receive(Webhook $webhook): array
    {
        if (\strlen($webhook->body) > self::MAX_WEBHOOK_BYTES) {
            throw InvalidWebhook::because('The notification is too large.');
        }

        if (Webhook::ENDED === $webhook->channel) {
            return [new RoomEnded($this->room($webhook->query['meetingID'] ?? null), $this->now())];
        }

        if (Webhook::EVENTS !== $webhook->channel || 'POST' !== $webhook->method) {
            throw InvalidWebhook::because('BigBlueButton notifies the end of a room by GET and its events by POST.');
        }

        parse_str($webhook->body, $form);

        if (isset($form['signed_parameters'])) {
            return [$this->recordingReady($form['signed_parameters'])];
        }

        return $this->analytics ? $this->attendance($webhook) : [];
    }

    /**
     * The address of a call: its query, then the checksum of the call's name, the query and the secret.
     *
     * @param array<string, string> $parameters
     */
    public function signed(string $call, array $parameters): string
    {
        $query = http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);

        return $this->apiUrl.$call.'?'.('' === $query ? '' : $query.'&').'checksum='.$this->algorithm->hash($call.$query.$this->secret);
    }

    /**
     * @param array<string, string> $parameters
     * @param list<string>          $tolerated
     */
    private function call(string $call, array $parameters, array $tolerated = []): XmlAnswer
    {
        return XmlAnswer::read($this->http->get($this->signed($call, $parameters), $call), $call, $tolerated);
    }

    /**
     * The information of a room, or null when the server does not know it.
     */
    private function info(RoomId $room): ?XmlAnswer
    {
        $answer = $this->call('getMeetingInfo', ['meetingID' => $room->value], ['notFound']);

        return $answer->succeeded() ? $answer : null;
    }

    private function recording(XmlAnswer $answer, RoomId $room, DOMElement $recording, DOMElement $format): ?Recording
    {
        $url = $answer->text('url', $format);

        try {
            return new Recording(
                (string) $answer->text('recordID', $recording),
                $room,
                (string) $answer->text('type', $format),
                null === $url || '' === $url ? null : Url::fromString($url)->toString(),
                'true' === $answer->text('published', $recording),
                Timestamps::read($answer->text('startTime', $recording)),
                Timestamps::read($answer->text('endTime', $recording)),
            );
        } catch (InvalidValue|InvalidUrl) {
            return null;
        }
    }

    private function recordingReady(mixed $token): RecordingReady
    {
        if (!\is_string($token)) {
            throw InvalidWebhook::because('The signed parameters of a recording are a token.');
        }

        $claims = JsonWebToken::verify($token, $this->secret, ['HS256'], $this->now());
        $recording = $claims['record_id'] ?? null;

        if (!\is_string($recording) || 1 !== preg_match('/^[A-Za-z0-9._:-]{1,190}$/D', $recording)) {
            throw InvalidWebhook::because('The notification of a recording names no recording.');
        }

        return new RecordingReady($this->room($claims['meeting_id'] ?? null), $recording, $this->now());
    }

    /**
     * @return list<RoomEvent>
     */
    private function attendance(Webhook $webhook): array
    {
        if ($this->signedAnalytics) {
            $authorization = $webhook->header('authorization') ?? '';

            if (!str_starts_with($authorization, 'Bearer ')) {
                throw InvalidWebhook::because('The attendance of a session comes with a bearer token.');
            }

            JsonWebToken::verify(substr($authorization, 7), $this->secret, ['HS256', 'HS384', 'HS512'], $this->now());
        }

        try {
            $payload = json_decode($webhook->body, true, 32, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidWebhook::because('The attendance of a session is not JSON.');
        }

        if (!\is_array($payload)) {
            throw InvalidWebhook::because('The attendance of a session is not a JSON object.');
        }

        $room = $this->room($payload['meeting_id'] ?? null);
        $data = $payload['data'] ?? null;
        $attendees = \is_array($data) && \is_array($data['attendees'] ?? null) ? $data['attendees'] : [];

        if (\count($attendees) > self::MAX_ATTENDEES) {
            throw InvalidWebhook::because(\sprintf('The attendance of a session lists more than %d attendees.', self::MAX_ATTENDEES));
        }

        $events = [];

        foreach ($attendees as $attendee) {
            if (!\is_array($attendee)) {
                continue;
            }

            $participant = ParticipantId::tryFrom($attendee['ext_user_id'] ?? null);

            if (!$participant instanceof ParticipantId) {
                continue;
            }

            foreach ($this->moments($attendee['joins'] ?? null) as $joined) {
                $events[] = new ParticipantJoined($room, $participant, $joined);
            }

            foreach ($this->moments($attendee['leaves'] ?? null) as $left) {
                $events[] = new ParticipantLeft($room, $participant, $left);
            }
        }

        usort($events, static fn (RoomEvent $a, RoomEvent $b): int => $a->at <=> $b->at);

        return $events;
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function moments(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        if (\count($values) > self::MAX_STAYS) {
            throw InvalidWebhook::because(\sprintf('An attendee of the session has more than %d stays.', self::MAX_STAYS));
        }

        $moments = [];

        foreach ($values as $value) {
            $moment = Timestamps::read(\is_array($value) ? ($value['timestamp'] ?? null) : $value);

            if ($moment instanceof DateTimeImmutable) {
                $moments[] = $moment;
            }
        }

        return $moments;
    }

    private function room(mixed $value): RoomId
    {
        try {
            return RoomId::fromString(\is_string($value) ? $value : '');
        } catch (InvalidValue) {
            throw InvalidWebhook::because('The notification names no room of the application.');
        }
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
