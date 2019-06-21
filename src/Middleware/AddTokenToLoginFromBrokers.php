<?php

namespace Zefy\LaravelSSO\Middleware;

use Closure;

class AddTokenToLoginFromBrokers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if($request->route()->hasParameter('token')) {
            $request->headers->set('Authorization', 'Bearer ' . base64_decode($request->route()->parameter('token')));
        }

        return $next($request);
    }
}
