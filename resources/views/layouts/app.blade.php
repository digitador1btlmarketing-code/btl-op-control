<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'Control Center') | BTL Producción</title>
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="/css/style.css?v={{ time() }}">
    
    <!-- Immediately apply theme before rendering to avoid flash -->
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    
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

                @if(session('user_role') === 'admin' && request()->is('op/admin'))
                    <button onclick="openMaintenanceModal()" class="btn-view-brief" style="margin-right: 10px; cursor: pointer; background: rgba(255, 255, 255, 0.05); border-color: var(--border-glass);">
                        ⚙ Mantenimiento
                    </button>
                @endif

                <button class="btn-theme-toggle" style="margin-right: 10px;" onclick="toggleTheme()">
                    <span class="theme-toggle-icon">🌙</span> <span class="theme-toggle-text">Modo Oscuro</span>
                </button>
                
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
    <!-- Floating theme switch for unauthenticated login screen -->
    @if(!session()->has('user_role'))
        <div style="position: absolute; top: 20px; right: 20px; z-index: 1000;">
            <button class="btn-theme-toggle" onclick="toggleTheme()">
                <span class="theme-toggle-icon">🌙</span> <span class="theme-toggle-text">Modo Oscuro</span>
            </button>
        </div>
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
        // Theme Toggle Logic
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            
            // Sync all theme buttons on the page
            document.querySelectorAll('.btn-theme-toggle').forEach(btn => {
                const icon = btn.querySelector('.theme-toggle-icon');
                const text = btn.querySelector('.theme-toggle-text');
                if (theme === 'light') {
                    if (icon) icon.textContent = '☀️';
                    if (text) text.textContent = 'Modo Claro';
                } else {
                    if (icon) icon.textContent = '🌙';
                    if (text) text.textContent = 'Modo Oscuro';
                }
            });
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
        }

        // Apply on load
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);
        });

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
