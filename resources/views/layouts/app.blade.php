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

                <button id="btn-sound-toggle" class="btn-theme-toggle" style="margin-right: 10px;" onclick="toggleSound()">
                    <span id="sound-icon">🔈</span> <span>Activar Sonido</span>
                </button>
                
                @if(in_array(session('user_role'), ['admin', 'ventas', 'jefe_ventas']))
                    <a href="{{ route('op.create') }}" class="btn-view-brief" style="margin-right: 10px;">+ Nueva OP</a>
                @endif
                
                @if(session('user_role') === 'admin')
                    <a href="{{ route('op.admin') }}" class="btn-view-brief" style="margin-right: 10px;">Panel Admin</a>
                @elseif(session('user_role') === 'jefe_ventas')
                    <a href="{{ route('op.jefe_ventas') }}" class="btn-view-brief" style="margin-right: 10px;">Panel Jefe de Ventas</a>
                @elseif(session('user_role') === 'ventas')
                    <a href="{{ route('op.mis_ordenes') }}" class="btn-view-brief" style="margin-right: 10px;">Panel Mis Órdenes</a>
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

    <!-- Global Toast Container for Custom Notifications -->
    <div id="toast-container"></div>

    <!-- Global Audio Element for Alerts -->
    <audio id="global-alert-audio" src="/sounds/alert.mp3" preload="auto"></audio>

    <script>
        // Theme Toggle Logic
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            
            // Sync all theme buttons on the page
            document.querySelectorAll('.btn-theme-toggle:not(#btn-sound-toggle)').forEach(btn => {
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

        // Global Sound Controls
        let isSoundEnabled = localStorage.getItem('sound_enabled') !== 'false';

        function playAlertSound() {
            if (isSoundEnabled) {
                const audio = document.getElementById('global-alert-audio');
                if (audio) {
                    audio.play().catch(err => console.log("Audio play blocked by browser policies:", err));
                }
            }
        }

        function updateSoundButtonUI() {
            const btns = document.querySelectorAll('#btn-sound-toggle');
            btns.forEach(btn => {
                const icon = btn.querySelector('#sound-icon') || btn.querySelector('.sound-icon') || btn.querySelector('span:first-child');
                const text = btn.querySelector('span:not(#sound-icon):not(.sound-icon)') || btn.querySelector('span:last-child');
                if (isSoundEnabled) {
                    if (icon) icon.textContent = '🔊';
                    if (text) text.textContent = 'Sonido Activo';
                    btn.style.background = 'rgba(0, 242, 195, 0.15)';
                    btn.style.borderColor = 'rgba(0, 242, 195, 0.3)';
                    btn.style.color = 'var(--green-lime)';
                } else {
                    if (icon) icon.textContent = '🔇';
                    if (text) text.textContent = 'Activar Sonido';
                    btn.style.background = 'rgba(255, 255, 255, 0.05)';
                    btn.style.borderColor = 'var(--border-glass)';
                    btn.style.color = '';
                }
            });
        }

        function toggleSound() {
            isSoundEnabled = !isSoundEnabled;
            localStorage.setItem('sound_enabled', isSoundEnabled ? 'true' : 'false');
            updateSoundButtonUI();
            if (isSoundEnabled) {
                playAlertSound();
            }
        }

        // Global custom toast notifications helper
        function showCustomToast(message, type = 'info', icon = '🔔') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `custom-toast toast-${type}`;
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
            `;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => {
                    toast.remove();
                }, 600);
            }, 7500);
        }

        // Process recent events list for real-time notifications
        window.notifiedEvents = new Set();
        function processRecentEvents(events) {
            if (!events || events.length === 0) return;
            let playedSound = false;
            events.forEach(event => {
                if (!window.notifiedEvents.has(event.id)) {
                    window.notifiedEvents.add(event.id);
                    
                    let icon = '🔔';
                    let type = 'info';
                    
                    if (event.tipo_evento === 'creacion') {
                        icon = '🆕';
                        type = 'success';
                    } else if (event.tipo_evento === 'cambio_estado') {
                        icon = '🔄';
                        type = 'info';
                    } else if (event.tipo_evento === 'asignacion_lider') {
                        icon = '👤';
                        type = 'info';
                    } else if (event.tipo_evento === 'solicitud_cambio') {
                        icon = '📅';
                        type = 'warning';
                    } else if (event.tipo_evento === 'aprobacion_cambio') {
                        icon = '✅';
                        type = 'success';
                    } else if (event.tipo_evento === 'rechazo_cambio') {
                        icon = '❌';
                        type = 'danger';
                    }
                    
                    showCustomToast(event.descripcion, type, icon);
                    playedSound = true;
                }
            });
            if (playedSound) {
                playAlertSound();
            }
        }

        // Apply on load
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);
            updateSoundButtonUI();

            // Auto-dismiss alerts after 3.5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 3500);
            });
        });

        // Global Toast Notification Helper for simple AJAX toast
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
