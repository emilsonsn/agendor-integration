<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class WebhookMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $receivedToken = $request->header('Authorization');

        Log::info('Webhook received from WooCommerce:', [
            'payload' => $request->all(),
            'authorization' => $receivedToken
        ]);

        $webhookToken = env('WEBHOOK_API_TOKEN');

        if ($receivedToken !== 'Bearer ' . $webhookToken) {
            return $next($request);
            // return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
