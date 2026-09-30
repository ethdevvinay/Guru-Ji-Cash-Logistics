<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST')) {
            $idempotencyKey = $request->header('X-Idempotency-Key');

            if ($idempotencyKey) {
                $cacheKey = "idempotency:{$idempotencyKey}";

                if (Cache::has($cacheKey)) {
                    $cachedResponse = Cache::get($cacheKey);
                    return response()->json($cachedResponse['data'], $cachedResponse['status']);
                }

                $response = $next($request);

                if ($response->isSuccessful()) {
                    Cache::put($cacheKey, [
                        'data' => json_decode($response->getContent(), true),
                        'status' => $response->getStatusCode(),
                    ], 120); // 2 minutes lock
                }

                return $response;
            }
        }

        return $next($request);
    }
}
