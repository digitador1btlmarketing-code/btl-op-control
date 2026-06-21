<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'Control Center') | BTL Producción</title>
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="/css/style.css?v={{ time() }}">
    
    <!-- Meta tags for auto-refresh if defined in child views -->
    @yield('meta_extra')
</head>
<body class="@yield('body_class')">

    <!-- Header Navigation -->
    @if(session()->has('user_role') && !request()->is('op/tv'))
        <header>
            <div class="header-left">
                <img src="/img/logo-btl.png" alt="BTL Marketing Logo">
                <div class="header-title-container">
                    <h1>BTL PRODUCCIÓN | <span>CONTROL CENTER</span></h1>
                    <p>Módulo Operativo de Control de Órdenes</p>
                </div>
            </div>
            <div class="header-right">
                <span class="badge 
                    @if(session('user_role') === 'admin') badge-urgente 
                    @elseif(session('user_role') === 'ventas') badge-proceso 
                    @else badge-normal @endif">
                    Rol: {{ strtoupper(str_replace('_', ' ', session('user_role'))) }}
                </span>
                
                @if(session('user_role') === 'admin' || session('user_role') === 'ventas')
                    <a href="{{ route('op.create') }}" class="btn-view-brief" style="margin-right: 10px;">+ Nueva OP</a>
                @endif
                
                @if(session('user_role') === 'admin')
                    <a href="{{ route('op.admin') }}" class="btn-view-brief" style="margin-right: 10px;">Panel Admin</a>
                @endif
                
                <a href="{{ route('logout') }}" class="btn-logout">Cerrar Sesión</a>
            </div>
        </header>
    @endif

    <main class="container">
        <!-- Success Alert -->
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- Error Alert -->
        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Global Toast notification for AJAX -->
    <div id="ajax-toast" class="toast"></div>

    <script>
        // Global Toast Notification Helper
        function showToast(message, type = 'success') {
            const toast = document.getElementById('ajax-toast');
            if (toast) {
                toast.textContent = message;
                toast.className = 'toast show ' + (type === 'success' ? 'toast-success' : 'toast-error');
                
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
    </script>

    @yield('scripts')
</body>
</html>
