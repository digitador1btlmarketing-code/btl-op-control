<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessControlMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$allowedRoles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles): Response
    {
        $userRole = session('user_role');

        if (!$userRole) {
            return redirect('/')->with('error', 'Por favor, ingrese un código de acceso.');
        }

        // Check if the current user role is allowed.
        // The admin role has superuser access to all pages.
        if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles)) {
            if ($userRole !== 'admin') {
                return redirect('/')->with('error', 'No tiene permisos para acceder a esta sección.');
            }
        }

        // Custom restrictions for TV view
        if ($request->is('op/tv')) {
            $categoria = $request->query('categoria');

            if ($userRole === 'tv_branding') {
                if ($categoria !== 'Branding') {
                    return redirect('/op/tv?categoria=Branding');
                }
            } elseif ($userRole === 'tv_promocional') {
                if ($categoria !== 'Promocional') {
                    return redirect('/op/tv?categoria=Promocional');
                }
            } elseif ($userRole === 'admin') {
                // Admin can access either category, default to Branding if not specified
                if (!$categoria || !in_array($categoria, ['Branding', 'Promocional'])) {
                    return redirect('/op/tv?categoria=Branding');
                }
            }
        }

        return $next($request);
    }
}
