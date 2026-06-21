<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Display the access form.
     */
    public function showLogin()
    {
        // If session already exists, auto-redirect to respective section
        if (session()->has('user_role')) {
            $role = session('user_role');
            if ($role === 'ventas') {
                return redirect('/op/nueva');
            } elseif ($role === 'admin') {
                return redirect('/op/admin');
            } elseif ($role === 'tv_branding') {
                return redirect('/op/tv?categoria=Branding');
            } elseif ($role === 'tv_promocional') {
                return redirect('/op/tv?categoria=Promocional');
            }
        }
        return view('auth.login');
    }

    /**
     * Authenticate user with access code.
     */
    public function login(Request $request)
    {
        $request->validate([
            'codigo_acceso' => 'required|string',
        ], [
            'codigo_acceso.required' => 'El código de acceso es obligatorio.',
        ]);

        $codigo = trim($request->input('codigo_acceso'));

        switch ($codigo) {
            case 'VENTAS-PROD-2026':
                session(['user_role' => 'ventas']);
                return redirect('/op/nueva')->with('success', 'Acceso Ventas autorizado.');
            case 'ADMIN-PROD-2026':
                session(['user_role' => 'admin']);
                return redirect('/op/admin')->with('success', 'Acceso Administrador de Producción autorizado.');
            case 'BRANDING-PROD-2026':
                session(['user_role' => 'tv_branding']);
                return redirect('/op/tv?categoria=Branding')->with('success', 'Acceso Pantalla TV Branding autorizado.');
            case 'PROMO-PROD-2026':
                session(['user_role' => 'tv_promocional']);
                return redirect('/op/tv?categoria=Promocional')->with('success', 'Acceso Pantalla TV Promocional autorizado.');
            default:
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'El código de acceso ingresado no es válido.');
        }
    }

    /**
     * Log out the session.
     */
    public function logout()
    {
        session()->forget('user_role');
        return redirect('/')->with('success', 'Sesión finalizada.');
    }
}
