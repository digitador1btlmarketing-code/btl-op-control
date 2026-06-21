@extends('layouts.app')

@section('title', 'Acceso')

@section('content')
<div class="login-wrapper">
    <div class="card login-card">
        <div class="logo-glass-wrapper" style="padding: 25px; width: 170px; height: 170px; margin-bottom: 25px;">
            <img class="login-logo" src="{{ asset('img/logo-btl.png') }}" alt="BTL Marketing Logo" style="height: 120px; object-fit: contain;">
        </div>
        <h2 style="font-size: 2rem; font-weight: 800; letter-spacing: 0.5px;">BTL PRODUCCIÓN</h2>
        <h3 style="font-size: 1.15rem; color: var(--blue-bright); margin-bottom: 10px; font-weight: 700; text-transform: uppercase;">Formulario de Órdenes de Producción</h3>
        <p style="margin-bottom: 8px; font-size: 0.95rem; color: var(--text-white);">Acceso interno para Ventas, Producción y pantallas operativas.</p>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 25px; font-weight: 500;">Seleccione el acceso correspondiente según su área.</p>

        <form action="{{ url('/') }}" method="POST">
            @csrf
            
            <div class="form-group" style="text-align: left;">
                <label for="codigo_acceso">Código de Acceso</label>
                <input 
                    type="text" 
                    id="codigo_acceso" 
                    name="codigo_acceso" 
                    class="form-control" 
                    placeholder="Ingrese su código de acceso" 
                    required 
                    autocomplete="off" 
                    style="text-align: center; font-size: 1.1rem;"
                >
                @error('codigo_acceso')
                    <span style="color: var(--priority-urgente); font-size: 0.85rem; margin-top: 5px; display: block;">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">
                Ingresar al Sistema
            </button>
        </form>

        <div style="margin-top: 25px; font-size: 0.8rem; color: var(--text-muted); line-height: 1.5;">
            Soporte TI BTL Marketing &copy; {{ date('Y') }}
        </div>
    </div>
</div>
@endsection
