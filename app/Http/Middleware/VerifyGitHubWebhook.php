<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGitHubWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('github.webhook_secret');
        if ($secret === '') {
            abort(500, 'GITHUB_WEBHOOK_SECRET is not configured.');
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($signature) || $signature === '') {
            abort(403, 'Missing signature');
        }

        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid signature');
        }

        return $next($request);
    }
}
