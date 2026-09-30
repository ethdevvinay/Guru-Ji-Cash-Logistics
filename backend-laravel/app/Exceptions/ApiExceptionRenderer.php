<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception raised while serving /api/* into the standard envelope.
 * Web (admin panel) requests fall through to Laravel's normal HTML rendering.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $response = match (true) {
            $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->errors, $e->data),
            $e instanceof ValidationException => ApiResponse::error('VALIDATION_FAILED', 'Some fields are invalid.', 422, self::validationErrors($e)),
            $e instanceof AuthenticationException => $this->unauthenticated($request),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error('FORBIDDEN', 'You are not allowed to do this.', 403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('NOT_FOUND', 'Not found.', 404),
            $e instanceof ThrottleRequestsException => ApiResponse::error('RATE_LIMITED', 'Too many requests. Please wait and try again.', 429),
            $e instanceof MethodNotAllowedHttpException => ApiResponse::error('METHOD_NOT_ALLOWED', 'Method not allowed.', 405),
            $e instanceof HttpExceptionInterface => ApiResponse::error('HTTP_ERROR', $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.', $e->getStatusCode()),
            default => ApiResponse::error('SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.', 500),
        };

        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }

    private function unauthenticated(Request $request): JsonResponse
    {
        return ApiResponse::error('UNAUTHENTICATED', 'Please log in.', 401);
    }

    /**
     * @return list<array{field: string|null, code: string, message: string}>
     */
    private static function validationErrors(ValidationException $e): array
    {
        $failed = $e->validator->failed();
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $rules = array_keys($failed[$field] ?? []);
            foreach (array_values($messages) as $index => $message) {
                $rule = $rules[$index] ?? 'Invalid';
                $errors[] = [
                    'field' => $field,
                    'code' => Str::upper(Str::snake(class_basename($rule))),
                    'message' => $message,
                ];
            }
        }

        return $errors;
    }
}
