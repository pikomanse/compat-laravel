<?php

namespace Pikoman\Compat;

use Closure;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Laravel's broadcasting on Pikoman, with Pusher's channel conventions (`private-`, `presence-`),
 * so `routes/channels.php`, events and Laravel Echo with @pikoman/compat work as they do with
 * Pusher.
 */
class PikomanBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /** Pusher's limit per trigger, kept so code that works there works here. */
    private const CHANNELS_PER_TRIGGER = 100;

    /** The field @pikoman/compat in the browser reads: the connection that should not get the event. */
    private const SKIP = '__pikoman_skip';

    private ?Client $client = null;

    /**
     * `$makeClient` makes the client on first use: Laravel resolves the broadcaster on every
     * request and artisan command (for `routes/channels.php`), and a missing or wrong key should
     * break broadcasting, not the whole app.
     */
    public function __construct(private Closure $makeClient) {}

    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name) ||
            ($this->isGuardedChannel($request->channel_name) &&
            ! $this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    public function validAuthenticationResponse($request, $result)
    {
        if (str_starts_with($request->channel_name, 'private')) {
            return $this->client()->authorizeChannel($request->socket_id, $request->channel_name);
        }

        $user = $this->retrieveUser($request, $this->normalizeChannelName($request->channel_name));

        $userId = method_exists($user, 'getAuthIdentifierForBroadcasting')
            ? $user->getAuthIdentifierForBroadcasting()
            : $user->getAuthIdentifier();

        return $this->client()->authorizeChannel($request->socket_id, $request->channel_name, [
            'user_id' => (string) $userId,
            'user_info' => $result,
        ]);
    }

    /**
     * Each chunk of 100 channels is one request that reaches all of them or none. With
     * `toOthers()`, Laravel passes the sender's socket id (Echo's `X-Socket-ID`); it goes in the
     * data as `__pikoman_skip`, and @pikoman/compat on that page drops the event.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $socket = Arr::pull($payload, 'socket');
        if (is_string($socket) && preg_match('/\Ac_[A-Za-z0-9]{1,64}\z/', $socket)) {
            $payload[self::SKIP] = $socket;
        }

        try {
            foreach (array_chunk($this->formatChannels($channels), self::CHANNELS_PER_TRIGGER) as $chunk) {
                $this->client()->trigger($chunk, $event, $payload);
            }
        } catch (Throwable $e) {
            $message = str_starts_with($e->getMessage(), 'Pikoman') ? $e->getMessage() : 'Pikoman: '.$e->getMessage();
            throw new BroadcastException($message, $e->getCode(), $e);
        }
    }

    private function client(): Client
    {
        return $this->client ??= ($this->makeClient)();
    }
}
