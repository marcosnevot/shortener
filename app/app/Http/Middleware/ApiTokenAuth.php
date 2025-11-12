<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        if (!$plain) {
            return response()->json(['error' => 'missing_token'], 401);
        }
        $hash = hash('sha256', $plain);
        $token = ApiToken::where('token_hash', $hash)->first();
        if (!$token) {
            return response()->json(['error' => 'invalid_token'], 403);
        }
        // Guarda el token para usarlo en rate limits, etc.
        app()->instance('api.token', $token);

        // Marcar último uso (no crítico si falla)
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
