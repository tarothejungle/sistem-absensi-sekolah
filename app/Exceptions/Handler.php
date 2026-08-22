<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            $guard = Auth::guard();
            $recallerCookie = method_exists($guard, 'getRecallerName')
                ? $guard->getRecallerName()
                : null;

            $guard->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $loginUrl = route('login', ['expired' => 1]);
            $response = $request->expectsJson()
                ? response()->json([
                    'message' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                    'redirect' => $loginUrl,
                ], 419)
                : response()->view('errors.419', compact('loginUrl'), 419);

            foreach (array_filter([config('session.cookie'), $recallerCookie]) as $cookieName) {
                $response->headers->setCookie(Cookie::forget(
                    $cookieName,
                    config('session.path', '/'),
                    config('session.domain')
                ));
            }

            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
