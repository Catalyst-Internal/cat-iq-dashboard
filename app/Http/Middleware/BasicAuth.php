<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) config('dashboard.auth_user');
        $password = (string) config('dashboard.auth_password');

        if ($user === '' && $password === '' && app()->environment('local')) {
            return $next($request);
        }

        if ($user === '' || $password === '') {
            abort(500, 'Dashboard basic auth is not configured (DASHBOARD_AUTH_USER / DASHBOARD_AUTH_PASSWORD).');
        }

        $given = $request->getUser();
        $givenPass = $request->getPassword();

        if ($given !== $user || $givenPass !== $password) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Cat IQ"',
            ]);
        }

        return $next($request);
    }
}
