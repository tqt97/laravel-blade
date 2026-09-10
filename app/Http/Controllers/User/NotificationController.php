<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit((int) config('booking.listing.notification_preview_limit'))->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->getKey(),
                'title' => $notification->data['title'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'url' => $this->internalUrl($request, $notification->data['url'] ?? null),
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    private function internalUrl(Request $request, mixed $url): string
    {
        if (! is_string($url) || $url === '') {
            return '/user/dashboard';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $localHosts = app()->environment(['local', 'testing'])
            ? ['localhost', '127.0.0.1', '::1']
            : [];

        if (! is_string($host) || ! in_array($host, array_merge([$request->getHost(), $configuredHost], $localHosts), true)) {
            return '/user/dashboard';
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/user/dashboard';
        $query = is_string($parts['query'] ?? null) ? '?'.$parts['query'] : '';
        $fragment = is_string($parts['fragment'] ?? null) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->delete();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return response()->json(['unread_count' => 0]);
    }
}
