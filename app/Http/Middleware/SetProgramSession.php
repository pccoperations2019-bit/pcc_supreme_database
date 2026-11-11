<?php

namespace App\Http\Middleware;

use App\Enums\ProgramEnum;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetProgramSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('_program')) {
            $program = $request->query('_program');

            if (in_array($program, array_column(ProgramEnum::cases(), 'value'))) {
                session(['_program' => $program]);
            }

            return Inertia::location(route('dashboard', ['program' => $request->query('_program')]));
        }

        return $next($request);
    }
}
