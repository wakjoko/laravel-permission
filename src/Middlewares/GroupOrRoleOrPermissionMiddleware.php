<?php

namespace Spatie\Permission\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;

class GroupOrRoleOrPermissionMiddleware
{
    public function handle($request, Closure $next, $groupOrRoleOrPermission)
    {
        if (Auth::guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $groupOrRolesOrPermissions = is_array($groupOrRoleOrPermission)
            ? $groupOrRoleOrPermission
            : explode('|', $groupOrRoleOrPermission);

        if (! Auth::user()->hasAnyGroup($groupOrRolesOrPermissions) && ! Auth::user()->hasAnyRole($groupOrRolesOrPermissions) && ! Auth::user()->hasAnyPermission($groupOrRolesOrPermissions)) {
            throw UnauthorizedException::forRolesOrPermissions($groupOrRolesOrPermissions);
        }

        return $next($request);
    }
}
