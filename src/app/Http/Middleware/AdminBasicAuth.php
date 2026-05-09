<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    /**
     * 管理画面へのBasic認証を検証する。
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('admin.username', '');
        $password = (string) config('admin.password', '');

        if ($username === '' || $password === '') {
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            abort(503, 'Admin credentials are not configured.');
        }

        $providedUser = (string) ($request->getUser() ?? '');
        $providedPassword = (string) ($request->getPassword() ?? '');

        if (! hash_equals($username, $providedUser) || ! hash_equals($password, $providedPassword)) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="Reservation Admin"',
            ]);
        }

        return $next($request);
    }
}
