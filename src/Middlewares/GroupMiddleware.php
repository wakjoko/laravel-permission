<?php

namespace Spatie\Permission\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;

class GroupMiddleware
{
    public function handle($request, Closure $next, $group)
    {
        if (Auth::guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $groups = is_array($group)
            ? $group
            : explode('|', $group);

        if (! Auth::user()->hasAnyGroup($groups)) {
            throw UnauthorizedException::forGroups($groups);
        }

        return $next($request);
    }
}
