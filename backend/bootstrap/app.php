<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$databaseUnavailable = static function (QueryException|\PDOException $exception): bool {
    $message = $exception->getMessage();

    foreach ([
        'SQLSTATE[HY000] [2002]',
        'actively refused',
        'Connection refused',
        'could not find driver',
        'unable to open database file',
        'database is locked',
    ] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($databaseUnavailable): void {
        $exceptions->render(function (QueryException|\PDOException $exception, Request $request) use ($databaseUnavailable) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if (! $databaseUnavailable($exception)) {
                return null;
            }

            return response()->json([
                'message' => 'The backend database is unavailable. Start MySQL and confirm backend/.env points to a working local database.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
    })->create();
