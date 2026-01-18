<?php

namespace App\Http\Middleware;

use App\Services\CurrentProfile;
use Closure;
use Illuminate\Http\Request;

class EnsureProfileSelected
{
    public function handle(Request $request, Closure $next)
    {
        if (! app(CurrentProfile::class)->get()) {
            return redirect()->route('profiles.index');
        }

        return $next($request);
    }
}
