<?php

use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\ResolveTenant;
use App\Providers\AuditServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        AuditServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
        ]);
        // Resolve the farm before route model binding so scoped resources are
        // looked up in the tenant selected for this request.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $isApi($request),
        );

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many requests. Try again later.',
                    ],
                ], 429);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_failed',
                        'message' => $e->getMessage(),
                        'fields' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'unauthenticated',
                        'message' => 'Authentication required.',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'forbidden',
                        'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'forbidden',
                        'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'method_not_allowed',
                        'message' => 'This HTTP method is not supported for this endpoint.',
                    ],
                ], 405);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => $e->getStatusCode() === 404 ? 'not_found' : 'http_error',
                    'message' => $e->getMessage() ?: 'The request could not be completed.',
                ],
            ], $e->getStatusCode(), $e->getHeaders());
        });

        $exceptions->render(function (PlanLimitExceededException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'error' => [
                        'code' => 'plan_limit_exceeded',
                        'message' => $e->getMessage(),
                        'fields' => [
                            'limit' => [$e->key],
                            'max' => [(string) $e->limit],
                        ],
                    ],
                ], 402);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request) || $e instanceof HttpResponseException) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'internal_error',
                    'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                ],
            ], 500);
        });
    })->create();
