<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected string $atlasBaseUrl;

    public function __construct()
    {
        $this->atlasBaseUrl = rtrim(config('services.atlas_auth.url', 'https://auth.example.com'), '/');
    }

    /**
     * Create a Google Meet meeting on behalf of the authenticated user.
     *
     * @param  string  $bearerToken  The user's Sanctum token from Authorization header
     * @param  array{summary: string, description?: string, start: array{dateTime: string, timeZone?: string}, end: array{dateTime: string, timeZone?: string}, attendees?: array}  $meetingData
     * @return array{success: bool, data?: array, error?: string, status?: int}
     */
    public function createMeetWithRetry(string $bearerToken, array $meetingData): array
    {
        try {
            $response = $this->callAtlasMeetEndpoint($bearerToken, $meetingData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            // Handle specific error cases
            $status = $response->status();
            $errorMessage = $this->extractErrorMessage($response);

            Log::warning('Google Meet creation failed', [
                'status' => $status,
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'status' => $status,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Exception creating Google Meet', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create Google Meet: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Delete a Google Calendar event via the Atlas Auth proxy.
     *
     * @return array{success: bool, status?: int, error?: string}
     */
    public function deleteEvent(string $bearerToken, string $eventId, string $calendarId = 'primary'): array
    {
        $path = sprintf(
            '/calendars/%s/events/%s',
            rawurlencode($calendarId),
            rawurlencode($eventId)
        );

        try {
            $response = $this->calendarProxy($bearerToken, 'DELETE', $path);

            if ($response->noContent() || $response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                ];
            }

            if ($response->status() === 404) {
                Log::info('Google Calendar event already deleted', [
                    'event_id' => $eventId,
                    'calendar_id' => $calendarId,
                ]);

                return [
                    'success' => true,
                    'status' => 404,
                ];
            }

            $errorMessage = $this->extractErrorMessage($response);

            Log::warning('Google Calendar event deletion failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Exception deleting Google Calendar event', [
                'event_id' => $eventId,
                'calendar_id' => $calendarId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to delete Google Calendar event: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generic proxy to Google Calendar API via Atlas Auth service.
     *
     * @param  string  $bearerToken  The user's Sanctum token
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE)
     * @param  string  $path  Path relative to /calendar/v3 (e.g., "/calendars/primary/events")
     * @param  array  $query  Optional query string parameters
     * @param  array  $json  Optional request body for POST/PUT
     */
    public function calendarProxy(
        string $bearerToken,
        string $method,
        string $path,
        array $query = [],
        array $json = []
    ): Response {
        $payload = [
            'method' => strtoupper($method),
            'path' => $path,
        ];

        if (! empty($query)) {
            $payload['query'] = $query;
        }

        if (! empty($json)) {
            $payload['json'] = $json;
        }

        return $this->client($bearerToken)
            ->post("{$this->atlasBaseUrl}/api/auth/google/calendar/proxy", $payload);
    }

    /**
     * Call the Atlas Auth service to create a Google Meet.
     */
    protected function callAtlasMeetEndpoint(string $bearerToken, array $meetingData): Response
    {
        return $this->client($bearerToken)
            ->timeout(30) // Longer timeout for Google API calls
            ->post("{$this->atlasBaseUrl}/api/auth/google/meet/create", $meetingData);
    }

    /**
     * Extract error message from response.
     */
    protected function extractErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (isset($json['message'])) {
            return $json['message'];
        }

        if (isset($json['error'])) {
            return is_string($json['error']) ? $json['error'] : json_encode($json['error']);
        }

        return "HTTP {$response->status()}: Failed to create meeting";
    }

    /**
     * Create HTTP client with authorization header.
     */
    protected function client(string $bearerToken): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'Authorization' => $bearerToken,
            ]);
    }

    /**
     * Update an existing Google Calendar event via Atlas Auth proxy.
     *
     * @param  array{summary?: string, description?: string, start?: array{dateTime: string, timeZone?: string}, end?: array{dateTime: string, timeZone?: string}, attendees?: array}  $eventData
     * @return array{success: bool, status?: int, error?: string, data?: array}
     */
    public function updateEvent(string $bearerToken, string $eventId, array $eventData, string $calendarId = 'primary'): array
    {
        $path = sprintf(
            '/calendars/%s/events/%s',
            rawurlencode($calendarId),
            rawurlencode($eventId)
        );

        try {
            $response = $this->calendarProxy($bearerToken, 'PATCH', $path, json: $eventData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $response->json(),
                ];
            }

            $errorMessage = $this->extractErrorMessage($response);

            Log::warning('Google Calendar event update failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Exception updating Google Calendar event', [
                'event_id' => $eventId,
                'calendar_id' => $calendarId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update Google Calendar event: '.$e->getMessage(),
            ];
        }
    }
}
