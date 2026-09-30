<?php

declare(strict_types=1);

namespace App\Support\Api;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;

/**
 * Builds every JSON response in the platform's envelope (spec §5.1).
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, string $code = 'OK', array $meta = []): JsonResponse
    {
        return self::make(true, $code, $message, $data, [], $status, $meta);
    }

    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     */
    public static function error(string $code, string $message, int $status, array $errors = [], mixed $data = null): JsonResponse
    {
        return self::make(false, $code, $message, $data, $errors, $status, []);
    }

    public static function formatTime(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function serverTime(): string
    {
        return self::formatTime(Carbon::now());
    }

    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     * @param  array<string, mixed>  $meta
     */
    private static function make(bool $success, string $code, string $message, mixed $data, array $errors, int $status, array $meta): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => array_merge($meta, [
                'server_time' => self::serverTime(),
                'request_id' => Context::get('request_id'),
            ]),
        ], $status);
    }
}
