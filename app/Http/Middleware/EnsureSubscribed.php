<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()->subscribed('default')) {
            return redirect()->route('assinatura.index')
                ->with('warning', 'Você precisa de uma assinatura ativa para acessar esta área.');
        }

        return $next($request);
    }
}
