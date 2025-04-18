<?php

declare(strict_types=1);

namespace Codprox\OneSignal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MyOneSignal
{
    protected string $appId;
    protected string $restApiKey;
    protected string $defaultIcon;
    protected Client $client;
    protected int $cacheTtl;

    public function __construct(?string $appId = null, ?string $restApiKey = null, ?string $defaultIcon = null)
    {
        $this->appId = $appId ?? config('onesignal.app_id');
        $this->restApiKey = $restApiKey ?? config('onesignal.rest_api_key');
        $this->defaultIcon = $defaultIcon ?? config('onesignal.default_icon', '');
        $this->cacheTtl = (int) config('onesignal.cache_ttl', 3600);

        if (empty($this->appId) || empty($this->restApiKey)) {
            throw new InvalidArgumentException('OneSignal App ID and REST API Key must be provided.');
        }

        $this->client = new Client([
            'base_uri' => 'https://onesignal.com/api/v1/',
            'headers' => [
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => (float) config('onesignal.timeout', 10.0),
            'connect_timeout' => (float) config('onesignal.connect_timeout', 5.0),
        ]);
    }

    public function sendToAll(array $message, array $extraData = [], ?string $scheduledTime = null): array
    {
        $this->validateMessage($message);
        $payload = $this->buildPayload($message, ['included_segments' => ['All']], $extraData, $scheduledTime);
        return $this->sendNotification($payload);
    }

    public function sendToUsers(array $userIds, array $message, array $extraData = [], ?string $scheduledTime = null): array
    {
        if (empty($userIds)) {
            throw new InvalidArgumentException('User IDs cannot be empty.');
        }
        $this->validateMessage($message);
        $payload = $this->buildPayload($message, ['include_external_user_ids' => $userIds], $extraData, $scheduledTime);
        return $this->sendNotification($payload);
    }

    public function sendToSegment(string $segmentName, array $message, array $extraData = [], ?string $scheduledTime = null): array
    {
        if (empty($segmentName)) {
            throw new InvalidArgumentException('Segment name cannot be empty.');
        }
        $this->validateMessage($message);
        $payload = $this->buildPayload($message, ['included_segments' => [$segmentName]], $extraData, $scheduledTime);
        return $this->sendNotification($payload);
    }

    public function createSegment(string $name, $value = null): array
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Segment name cannot be empty.');
        }

        $segments = $this->listSegments();
        foreach ($segments as $segment) {
            if ($segment['name'] === $name) {
                return array_merge($segment, ['exists' => true, 'message' => "Segment '$name' already exists"]);
            }
        }

        $payload = [
            'name' => $name,
            'filters' => [
                ['field' => 'tag', 'key' => $this->normalizeSegmentName($name), 'relation' => '=', 'value' => $value ?? 'true'],
            ],
        ];

        return Cache::remember("onesignal_segment_create_{$this->appId}_{$name}", $this->cacheTtl, function () use ($payload) {
            try {
                $response = $this->client->post("apps/{$this->appId}/segments", ['json' => $payload]);
                $data = json_decode($response->getBody()->getContents(), true);
                return array_merge($data, ['exists' => false]);
            } catch (GuzzleException $e) {
                Log::error('Failed to create segment: ' . $e->getMessage(), ['payload' => $payload]);
                throw $e;
            }
        });
    }

    public function updateSegment(string $segmentId, string $name, array $filters): array
    {
        if (empty($segmentId) || empty($name) || empty($filters)) {
            throw new InvalidArgumentException('Segment ID, name, and filters are required.');
        }

        $payload = ['name' => $name, 'filters' => $filters];
        $response = $this->client->put("apps/{$this->appId}/segments/{$segmentId}", ['json' => $payload]);
        Cache::forget("onesignal_segments_{$this->appId}");
        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteSegment(string $segmentId): bool
    {
        if (empty($segmentId)) {
            throw new InvalidArgumentException('Segment ID cannot be empty.');
        }

        $response = $this->client->delete("apps/{$this->appId}/segments/{$segmentId}");
        Cache::forget("onesignal_segments_{$this->appId}");
        return $response->getStatusCode() === 200;
    }

    public function listSegments(): array
    {
        return Cache::remember("onesignal_segments_{$this->appId}", $this->cacheTtl, function () {
            try {
                $response = $this->client->get("apps/{$this->appId}/segments");
                $data = json_decode($response->getBody()->getContents(), true);
                return $data['segments'] ?? [];
            } catch (GuzzleException $e) {
                Log::error('Failed to list segments: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    public function subscribeToSegments(string $playerId, array $segments): bool
    {
        if (empty($playerId) || empty($segments)) {
            throw new InvalidArgumentException('Player ID and segments cannot be empty.');
        }

        $tags = array_map(fn($segment) => [$this->normalizeSegmentName($segment) => 'true'], $segments);
        $payload = ['tags' => array_merge(...$tags)];

        try {
            $response = $this->client->put("players/{$playerId}", ['json' => $payload]);
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log::error('Failed to subscribe to segments: ' . $e->getMessage(), ['playerId' => $playerId, 'segments' => $segments]);
            throw $e;
        }
    }

    public function unsubscribeFromSegments(string $playerId, array $segments): bool
    {
        if (empty($playerId) || empty($segments)) {
            throw new InvalidArgumentException('Player ID and segments cannot be empty.');
        }

        $tags = array_map(fn($segment) => [$this->normalizeSegmentName($segment) => ''], $segments);
        $payload = ['tags' => array_merge(...$tags)];

        try {
            $response = $this->client->put("players/{$playerId}", ['json' => $payload]);
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log::error('Failed to unsubscribe from segments: ' . $e->getMessage(), ['playerId' => $playerId, 'segments' => $segments]);
            throw $e;
        }
    }

    /**
     * Récupère la liste de tous les appareils inscrits à l'application.
     *
     * @param int $limit Nombre maximum de résultats (défaut: 300, max: 2000)
     * @param int $offset Décalage pour la pagination (défaut: 0)
     * @return array Liste des appareils
     * @throws GuzzleException
     */
    public function getDevices(int $limit = 300, int $offset = 0): array
    {
        if ($limit < 1 || $limit > 2000) {
            throw new InvalidArgumentException('Limit must be between 1 and 2000.');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $cacheKey = "onesignal_devices_{$this->appId}_limit{$limit}_offset{$offset}";
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($limit, $offset) {
            try {
                $response = $this->client->get("players", [
                    'query' => [
                        'app_id' => $this->appId,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                return $data['players'] ?? [];
            } catch (GuzzleException $e) {
                Log::error('Failed to get devices: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Récupère les utilisateurs inscrits à un segment spécifique via leurs tags.
     *
     * @param string $segmentName Nom du segment
     * @param int $limit Nombre maximum de résultats (défaut: 300, max: 2000)
     * @param int $offset Décalage pour la pagination (défaut: 0)
     * @return array Liste des joueurs inscrits au segment
     * @throws GuzzleException
     */
    public function usersSegments(string $segmentName, int $limit = 300, int $offset = 0): array
    {
        if (empty($segmentName)) {
            throw new InvalidArgumentException('Segment name cannot be empty.');
        }
        if ($limit < 1 || $limit > 2000) {
            throw new InvalidArgumentException('Limit must be between 1 and 2000.');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $tagKey = $this->normalizeSegmentName($segmentName);
        $cacheKey = "onesignal_users_segment_{$this->appId}_{$tagKey}_limit{$limit}_offset{$offset}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tagKey, $limit, $offset) {
            try {
                $response = $this->client->get("players", [
                    'query' => [
                        'app_id' => $this->appId,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ]);
                $players = json_decode($response->getBody()->getContents(), true)['players'] ?? [];
                
                // Filtrer les joueurs ayant le tag correspondant au segment
                return array_filter($players, function ($player) use ($tagKey) {
                    return isset($player['tags'][$tagKey]) && $player['tags'][$tagKey] === 'true';
                });
            } catch (GuzzleException $e) {
                Log::error('Failed to get users for segment: ' . $e->getMessage(), ['segment' => $segmentName]);
                throw $e;
            }
        });
    }

    protected function normalizeSegmentName(string $segmentName): string
    {
        return strtolower(str_replace(' ', '_', trim($segmentName)));
    }

    protected function buildPayload(array $message, array $target, array $extraData, ?string $scheduledTime): array
    {
        [$setSubject, $setBody, $setUrl, $setIcon, $setImageUrl] = array_pad($message, 5, null);

        $payload = array_merge([
            'app_id' => $this->appId,
            'headings' => ['en' => $setSubject],
            'contents' => ['en' => $setBody],
            'url' => $setUrl,
            'chrome_web_icon' => $setIcon ?? $this->defaultIcon,
            'chrome_web_image' => $setImageUrl,
        ], $target, $extraData);

        if ($scheduledTime) {
            $payload['send_after'] = $scheduledTime;
        }

        return array_filter($payload, fn($value) => !is_null($value));
    }

    protected function sendNotification(array $payload): array
    {
        try {
            $response = $this->client->post('notifications', ['json' => $payload]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('OneSignal notification error: ' . $e->getMessage(), ['payload' => $payload]);
            throw $e;
        }
    }

    protected function validateMessage(array $message): void
    {
        if (count($message) < 2 || empty($message[0]) || empty($message[1])) {
            throw new InvalidArgumentException('Message must contain at least a subject and body.');
        }
    }
}
