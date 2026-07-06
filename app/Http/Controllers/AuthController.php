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
                return redirect('/op/mis-ordenes');
            } elseif ($role === 'jefe_ventas') {
                return redirect('/op/jefe-ventas');
            } elseif (in_array($role, ['admin', 'admin_branding', 'admin_promo'])) {
                return redirect('/op/admin');
            } elseif ($role === 'tv_branding') {
                return redirect('/op/tv?categoria=Branding');
            } elseif ($role === 'tv_promocional') {
                return redirect('/op/tv?categoria=Promocional');
            } elseif ($role === 'vista') {
                return redirect('/op/vista');
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

        $codigo = strtoupper(trim($request->input('codigo_acceso')));

        // Check in database
        try {
            $usuario = \Illuminate\Support\Facades\DB::table('usuarios_acceso')
                ->where('codigo', $codigo)
                ->where('activo', true)
                ->first();
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error de conexión. Intente nuevamente.');
        }

        if ($usuario) {
            session([
                'user_role' => $usuario->rol,
                'user_code' => $usuario->codigo,
                'user_name' => $usuario->nombre . ($usuario->apellido ? ' ' . $usuario->apellido : ''),
            ]);

            if ($usuario->rol === 'ventas') {
                return redirect('/op/mis-ordenes')->with('success', 'Acceso Ventas autorizado.');
            } elseif ($usuario->rol === 'jefe_ventas') {
                return redirect('/op/jefe-ventas')->with('success', 'Acceso Jefe de Ventas autorizado.');
            } elseif (in_array($usuario->rol, ['admin', 'admin_branding', 'admin_promo'])) {
                return redirect('/op/admin')->with('success', 'Acceso Administrador de Producción autorizado.');
            } elseif ($usuario->rol === 'tv_branding') {
                return redirect('/op/tv?categoria=Branding')->with('success', 'Acceso Pantalla TV Branding autorizado.');
            } elseif ($usuario->rol === 'tv_promocional') {
                return redirect('/op/tv?categoria=Promocional')->with('success', 'Acceso Pantalla TV Promocional autorizado.');
            } elseif ($usuario->rol === 'vista') {
                return redirect('/op/vista')->with('success', 'Acceso Vista autorizado.');
            }
        }

        return redirect()->back()
            ->withInput()
            ->with('error', 'El código de acceso ingresado no es válido o está inactivo.');
    }

    /**
     * Log out the session.
     */
    public function logout()
    {
        session()->forget(['user_role', 'user_code', 'user_name']);
        return redirect('/')->with('success', 'Sesión finalizada.');
    }
}
