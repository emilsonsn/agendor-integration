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
        Log::info('Webhook received from WooCommerce:', [
            'payload' => $request->all()
        ]);

        $webhookToken = env('WEBHOOK_API_TOKEN');

        $receivedToken = $request->header('Authorization');

        if ($receivedToken !== 'Bearer ' . $webhookToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
