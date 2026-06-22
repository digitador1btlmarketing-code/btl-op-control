@extends('layouts.app')

@section('title', 'Bandeja Operativa TV - ' . $categoria)

@section('body_class', 'tv-body')

@section('content')
<div class="tv-container">
    
    <!-- TV Header -->
    <div class="tv-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="logo-glass-wrapper tv-logo-wrapper" style="margin-bottom: 0;">
                    <img class="tv-logo-img" src="/img/logo-btl.png" alt="BTL Logo">
                </div>
                <div>
                    <h2 class="tv-title" style="margin: 0; line-height: 1;">BTL PRODUCCIÓN | <span style="color: var(--blue-bright);">BANDEJA DE TRABAJO</span></h2>
                    <p class="tv-meta" style="margin-top: 5px;">Órdenes de producción priorizadas para pantalla operativa (Categoría: <span>{{ $categoria }}</span>)</p>
                </div>
            </div>
            
            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    @php
                        $userRole = session('user_role');
                        $roleLabel = 'ROL DESCONOCIDO';
                        if ($userRole === 'tv_branding') $roleLabel = 'ROL TV BRANDING';
                        elseif ($userRole === 'tv_promocional') $roleLabel = 'ROL TV PROMOCIONAL';
                        elseif ($userRole === 'admin') $roleLabel = 'ROL ADMINISTRADOR';
                        elseif ($userRole === 'ventas') $roleLabel = 'ROL VENTAS';
                    @endphp
                    <span class="badge badge-normal" style="font-size: 0.8rem; padding: 4px 10px; text-transform: uppercase; font-weight: 800;">{{ $roleLabel }}</span>
                    
                    <!-- Sound Control Switch -->
                    <button id="btn-sound-toggle" class="btn-view-brief" style="font-size: 0.8rem; padding: 4px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;" onclick="toggleSound()">
                        <span>Activar Sonido</span> <span id="sound-icon">🔇</span>
                    </button>

                    <!-- Theme Switcher -->
                    <button class="btn-theme-toggle" onclick="toggleTheme()">
                        <span class="theme-toggle-icon">🌙</span> <span class="theme-toggle-text">Modo Oscuro</span>
                    </button>
                    
                    <a href="{{ route('logout') }}" class="btn-logout" style="font-size: 0.8rem; padding: 4px 10px;">Cerrar Sesión</a>
                </div>
                <div style="margin-top: 3px;">
                    <span class="tv-meta" style="font-size: 0.9rem; font-weight: 600;">Fecha Actual: <span id="live-date" style="color: var(--text-white);">--/--/----</span></span>
                    <span class="tv-meta" style="font-size: 0.9rem; font-weight: 600; margin-left: 12px;">Hora Actual: <span id="live-time" style="color: var(--green-lime);">--:--:--</span></span>
                </div>
                <p class="tv-meta" style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">Última actualización: <span id="last-update-time">{{ date('d/m/Y H:i:s') }}</span></p>
            </div>
        </div>
    </div>

    <!-- TV KPIs Grid -->
    <div class="grid-4 tv-kpis">
        <!-- Terminadas -->
        <div class="kpi-card finished tv-kpi-card">
            <div id="kpi-terminadas" class="kpi-value" style="color: var(--state-terminado);">{{ $kpis['terminadas'] }}</div>
            <div class="kpi-label">Terminadas</div>
        </div>
        <!-- Pendientes -->
        <div class="kpi-card pending tv-kpi-card">
            <div id="kpi-pendientes" class="kpi-value" style="color: var(--state-pendiente);">{{ $kpis['pendientes'] }}</div>
            <div class="kpi-label">Pendientes</div>
        </div>
        <!-- Urgentes -->
        <div class="kpi-card urgent tv-kpi-card">
            <div id="kpi-urgentes" class="kpi-value" style="color: var(--priority-urgente);">{{ $kpis['urgentes'] }}</div>
            <div class="kpi-label">Urgentes 🔥</div>
        </div>
        <!-- Total Visibles -->
        <div class="kpi-card tv-kpi-card" style="background: rgba(0, 210, 255, 0.05); border-color: rgba(0, 210, 255, 0.25);">
            <div id="kpi-visibles" class="kpi-value" style="color: var(--blue-bright);">{{ $kpis['visibles'] }}</div>
            <div class="kpi-label">Órdenes Visibles</div>
        </div>
    </div>

    <!-- Status Filter Bar -->
    <div class="filter-bar">
        <button class="filter-btn active" data-filter="activas" onclick="applyStatusFilter('activas')">Todas activas</button>
        <button class="filter-btn" data-filter="Pendiente" onclick="applyStatusFilter('Pendiente')">Pendientes</button>
        <button class="filter-btn" data-filter="En proceso" onclick="applyStatusFilter('En proceso')">En proceso</button>
        <button class="filter-btn" data-filter="Terminado" onclick="applyStatusFilter('Terminado')">Terminadas</button>
        <button class="filter-btn" data-filter="Cancelado" onclick="applyStatusFilter('Cancelado')">Canceladas</button>
        <button class="filter-btn" data-filter="todos" onclick="applyStatusFilter('todos')">Todas</button>
    </div>

    <!-- Main Table Card -->
    <div class="card" style="padding: 15px; border-radius: 12px; margin-bottom: 15px;">
        <div class="table-responsive">
            <table class="tv-table">
                <thead>
                    <tr>
                        <th style="width: 3%; text-align: center;">🔥</th>
                        <th style="width: 7%;">Hora Sol.</th>
                        <th style="width: 8%;">Prioridad</th>
                        <th style="width: 8%;">OP</th>
                        <th style="width: 14%;">Marca</th>
                        <th style="width: 14%;">Presupuestista</th>
                        <th style="width: 12%;">Líder Producción</th>
                        <th style="width: 12%;">Fecha Entrega</th>
                        <th style="width: 9%;">Estado</th>
                        <th style="width: 10%;">Avance</th>
                        <th style="width: 5%;">Días</th>
                        <th style="width: 8%; text-align: center;">Brief</th>
                    </tr>
                </thead>
                <tbody id="tv-table-body">
                    @forelse($ordenes as $orden)
                        @php
                            $rowGlow = '';
                            if ($orden->estado !== 'Terminado' && $orden->estado !== 'Cancelado') {
                                if ($orden->prioridad === 'URGENTE') $rowGlow = 'row-glow-urgente';
                                elseif ($orden->prioridad === 'PRÓXIMA') $rowGlow = 'row-glow-proxima';
                                else $rowGlow = 'row-glow-normal';
                            }
                        @endphp
                        <tr class="tv-row {{ $rowGlow }}" data-id="{{ $orden->id }}" data-estado="{{ $orden->estado }}" onclick="selectOrder({{ $orden->id }})">
                            <td style="text-align: center;">
                                <span id="fire-container-{{ $orden->id }}" class="fire-container @if($orden->estado === 'Terminado') extinguished @elseif($orden->estado === 'Cancelado') hidden-fire @elseif(!$orden->mostrar_fuego) hidden-fire @endif" title="Alerta de prioridad temporal">
                                    <span class="fire-flame">🔥</span>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">{{ \Carbon\Carbon::parse($orden->created_at)->format('H:i') }}</span>
                                <br>
                                <small style="font-size: 0.75rem; color: var(--text-muted);">{{ \Carbon\Carbon::parse($orden->created_at)->format('d/m/y') }}</small>
                            </td>
                            <td>
                                <span class="badge 
                                    @if($orden->prioridad === 'URGENTE') badge-urgente
                                    @elseif($orden->prioridad === 'PRÓXIMA') badge-proxima
                                    @elseif($orden->prioridad === 'NORMAL') badge-normal
                                    @else badge-cancelado @endif">
                                    {{ $orden->prioridad ?? 'Sin fecha' }}
                                </span>
                            </td>
                            <td><strong style="color: var(--blue-bright);">{{ $orden->numero_op }}</strong></td>
                            <td>{{ $orden->marca }}</td>
                            <td>{{ $orden->presupuestista }}</td>
                            <td>
                                @if($orden->lider_produccion)
                                    <span style="color: var(--text-white);">{{ $orden->lider_produccion }}</span>
                                @else
                                    <em style="color: var(--text-muted); font-size: 0.9em;">Sin asignar</em>
                                @endif
                            </td>
                            <td>
                                <span style="color: var(--text-white);">{{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</span>
                                <br>
                                <small style="color: var(--text-muted);">{{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }}</small>
                            </td>
                            <td>
                                <span class="badge 
                                    @if($orden->estado === 'Pendiente') badge-pendiente
                                    @elseif($orden->estado === 'En proceso') badge-proceso
                                    @elseif($orden->estado === 'Cancelado') badge-cancelado
                                    @else badge-terminado @endif">
                                    {{ $orden->estado }}
                                </span>
                            </td>
                            <td>
                                <div class="progress-container">
                                    <div class="progress-track">
                                        <div class="progress-fill 
                                            @if($orden->estado === 'Pendiente') progress-fill-pendiente
                                            @elseif($orden->estado === 'En proceso') progress-fill-proceso
                                            @elseif($orden->estado === 'Cancelado') progress-fill-cancelado
                                            @else progress-fill-terminado @endif"
                                            style="width: {{ $orden->avance }}%;"></div>
                                    </div>
                                    <span class="progress-text">{{ $orden->avance }}%</span>
                                </div>
                            </td>
                            <td style="font-weight: 700; color: @if($orden->dias_restantes === null) var(--text-muted) @elseif($orden->dias_restantes <= 0) var(--priority-urgente) @elseif($orden->dias_restantes <= 2) var(--priority-proxima) @else var(--priority-normal) @endif">
                                @if($orden->dias_restantes === null)
                                    -
                                @elseif($orden->dias_restantes <= 0)
                                    Hoy
                                @else
                                    {{ $orden->dias_restantes }}d
                                @endif
                            </td>
                            <td style="text-align: center;" onclick="event.stopPropagation();">
                                @if($orden->brief)
                                    <a href="/storage/{{ $orden->brief }}" target="_blank" class="btn-view-brief">Ver Brief</a>
                                @else
                                    <span style="color: var(--text-muted); font-weight: bold;">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" style="text-align: center; color: var(--text-muted); padding: 50px; font-size: 1.2rem;">
                                No hay órdenes de producción {{ $categoria }} registradas para hoy.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TV Bottom Layout (Atención Inmediata + Detalles side-by-side) -->
    <div class="tv-bottom-layout">
        <!-- Columna 1: Atención Inmediata -->
        <div>
            <div class="section-title">
                <span>🔥</span> Atención Inmediata
            </div>
            
            <div id="atencion-grid-vertical" class="atencion-grid-vertical">
                @php
                    $urgentes = $ordenes->filter(fn($o) => $o->dias_restantes !== null && $o->dias_restantes < 3 && $o->estado !== 'Terminado' && $o->estado !== 'Cancelado');
                @endphp
                @forelse($urgentes as $urg)
                    <div class="atencion-card {{ $urg->prioridad === 'PRÓXIMA' ? 'proxima' : '' }}" onclick="selectOrder({{ $urg->id }})" style="cursor: pointer;">
                        <div class="atencion-header">
                            <span class="atencion-op">{{ $urg->numero_op }}</span>
                            <span class="atencion-dias">
                                @if($urg->dias_restantes < 0)
                                    VENCIDA ({{ abs($urg->dias_restantes) }}d)
                                  @elseif($urg->dias_restantes == 0)
                                    ENTREGA HOY
                                @else
                                    {{ $urg->dias_restantes }} días rest.
                                @endif
                            </span>
                        </div>
                        <div class="atencion-content">
                            <p>Marca: <strong>{{ $urg->marca }}</strong></p>
                            <p>Presup.: <strong>{{ $urg->presupuestista }}</strong></p>
                            <p>Líder: <strong>{{ $urg->lider_produccion ?? 'Sin asignar' }}</strong></p>
                            <p>Entrega: <strong>{{ \Carbon\Carbon::parse($urg->fecha_entrega)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($urg->hora_entrega)->format('H:i') }}</strong></p>
                        </div>
                    </div>
                @empty
                    <div style="background: rgba(16, 24, 48, 0.45); border: 1px dashed var(--border-glass); border-radius: 12px; padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                        No hay órdenes de atención inmediata pendientes (todas tienen más de 3 días para su entrega).
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Columna 2: Detalle de Orden Seleccionada -->
        <div>
            <div id="detail-panel" class="detail-panel hidden" style="margin-top: 0; position: relative;">
                <!-- Hide Button -->
                <button onclick="hideDetail()" class="btn-logout" style="position: absolute; top: 15px; right: 20px; font-size: 0.8rem; padding: 4px 10px; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">Ocultar detalle</button>
                
                <h3 class="section-title" style="margin-top: 0; margin-bottom: 15px; font-size: 1.35rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 8px;">
                    📋 Detalle OP: <span id="detail-op-title" style="color: var(--blue-bright);"></span>
                </h3>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Número OP</div>
                        <div id="detail-op" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Categoría</div>
                        <div id="detail-categoria" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Proyecto / Campaña</div>
                        <div id="detail-proyecto" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Cliente</div>
                        <div id="detail-cliente" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Marca</div>
                        <div id="detail-marca" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Presupuestista</div>
                        <div id="detail-presupuestista" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Líder Producción</div>
                        <div id="detail-lider" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Fecha y Hora Entrega</div>
                        <div id="detail-entrega" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Estado</div>
                        <div id="detail-estado" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Avance</div>
                        <div id="detail-avance" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Entregar A</div>
                        <div id="detail-entregar-a" class="detail-val">-</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Brief / Diseño</div>
                        <div id="detail-brief" class="detail-val brief-container">-</div>
                    </div>
                </div>

                <!-- Conditional Installation fields -->
                <div id="detail-installation-section" class="hidden" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
                    <h4 style="font-size: 1rem; color: var(--priority-proxima); margin-bottom: 10px;">Detalles de Montaje e Instalación</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Lugar de Instalación</div>
                            <div id="detail-lugar" class="detail-val">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Instalación (Fecha/Hora)</div>
                            <div id="detail-fecha-inst" class="detail-val">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Desinstalación (Fecha/Hora)</div>
                            <div id="detail-fecha-desinst" class="detail-val">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications Container -->
<div id="toast-container"></div>
@endsection

@section('scripts')
<script>
    // Initial order database & current filter configuration
    let currentOrders = @json($ordenes);
    const database = [...currentOrders];
    
    // Config variables
    const storageBaseUrl = "/storage";
    let selectedOrderId = null;
    let updatedOrderIds = [];
    let isSoundEnabled = localStorage.getItem('tv_sound_enabled') === 'true';

    // Sound alert audio context / player
    const alertAudio = new Audio("/sounds/alert.mp3");
    
    function playAlertSound() {
        alertAudio.currentTime = 0;
        alertAudio.play().catch(err => {
            console.log("Audio playback blocked by browser or missing asset:", err);
            // Synth beep fallback
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (e) {
                console.log("Synthesizer fallback failed:", e);
            }
        });
    }

    function updateSoundButtonUI() {
        const btn = document.getElementById('btn-sound-toggle');
        const icon = document.getElementById('sound-icon');
        if (btn && icon) {
            if (isSoundEnabled) {
                btn.style.background = 'rgba(0, 242, 195, 0.15)';
                btn.style.borderColor = 'rgba(0, 242, 195, 0.4)';
                btn.style.color = 'var(--green-lime)';
                btn.querySelector('span').textContent = 'Sonido Activo';
                icon.textContent = '🔊';
            } else {
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.style.color = '';
                btn.querySelector('span').textContent = 'Activar Sonido';
                icon.textContent = '🔇';
            }
        }
    }

    function toggleSound() {
        isSoundEnabled = !isSoundEnabled;
        localStorage.setItem('tv_sound_enabled', isSoundEnabled);
        updateSoundButtonUI();
        if (isSoundEnabled) {
            playAlertSound();
        }
    }

    // Dynamic custom toasts
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
        }, 7500); // 6 to 8 seconds duration
    }

    // JS-based row selection and toggle detail panel
    function selectOrder(id) {
        const order = database.find(o => o.id === id);
        if (!order) return;

        if (selectedOrderId === id) {
            hideDetail();
            return;
        }

        selectedOrderId = id;
        sessionStorage.setItem('selected_op_id', id);

        // Highlight selected row
        document.querySelectorAll('.tv-row').forEach(row => {
            row.classList.remove('active');
            if (parseInt(row.getAttribute('data-id')) === id) {
                row.classList.add('active');
                row.style.background = 'rgba(0, 210, 255, 0.08)';
            } else {
                row.style.background = '';
            }
        });

        populateDetail(order);

        // Show panel and scroll
        const detailPanel = document.getElementById('detail-panel');
        detailPanel.classList.remove('hidden');
        detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideDetail() {
        selectedOrderId = null;
        sessionStorage.removeItem('selected_op_id');
        
        document.querySelectorAll('.tv-row').forEach(row => {
            row.classList.remove('active');
            row.style.background = '';
        });

        const panel = document.getElementById('detail-panel');
        if (panel) {
            panel.classList.add('hidden');
        }
    }

    function populateDetail(order) {
        document.getElementById('detail-op-title').textContent = order.numero_op;
        document.getElementById('detail-op').textContent = order.numero_op;
        document.getElementById('detail-categoria').textContent = order.categoria;
        document.getElementById('detail-proyecto').textContent = order.proyecto;
        document.getElementById('detail-cliente').textContent = order.cliente;
        document.getElementById('detail-marca').textContent = order.marca;
        document.getElementById('detail-presupuestista').textContent = order.presupuestista;
        document.getElementById('detail-lider').innerHTML = order.lider_produccion ? 
            order.lider_produccion : '<em style="color: var(--text-muted);">Sin asignar</em>';
        
        const rawDate = order.fecha_entrega.split('-');
        const formattedDate = `${rawDate[2]}/${rawDate[1]}/${rawDate[0]}`;
        const formattedTime = order.hora_entrega.substring(0, 5);
        document.getElementById('detail-entrega').textContent = `${formattedDate} a las ${formattedTime} hrs`;
        
        let statusBadgeClass = 'badge-pendiente';
        if (order.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
        if (order.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
        if (order.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';
        document.getElementById('detail-estado').innerHTML = `<span class="badge ${statusBadgeClass}">${order.estado}</span>`;
        
        document.getElementById('detail-avance').textContent = `${order.avance}%`;
        document.getElementById('detail-entregar-a').textContent = order.entregar_a;
        
        const briefDiv = document.getElementById('detail-brief');
        if (order.brief) {
            briefDiv.innerHTML = `<a href="${storageBaseUrl}/${order.brief}" target="_blank" class="btn-view-brief">Ver Brief</a>`;
        } else {
            briefDiv.textContent = '-';
        }

        const instSection = document.getElementById('detail-installation-section');
        if (order.entregar_a === 'Instaladores') {
            instSection.classList.remove('hidden');
            document.getElementById('detail-lugar').textContent = order.lugar_instalacion || '-';
            
            const rawInstDate = order.fecha_instalacion.split('-');
            const fmtInstDate = `${rawInstDate[2]}/${rawInstDate[1]}/${rawInstDate[0]}`;
            const fmtInstTime = order.hora_instalacion.substring(0, 5);
            document.getElementById('detail-fecha-inst').textContent = `${fmtInstDate} - ${fmtInstTime} hrs`;
            
            const rawDesinstDate = order.fecha_desinstalacion.split('-');
            const fmtDesinstDate = `${rawDesinstDate[2]}/${rawDesinstDate[1]}/${rawDesinstDate[0]}`;
            const fmtDesinstTime = order.hora_desinstalacion.substring(0, 5);
            document.getElementById('detail-fecha-desinst').textContent = `${fmtDesinstDate} - ${fmtDesinstTime} hrs`;
        } else {
            instSection.classList.add('hidden');
        }
    }

    // Client-side status filtering
    function applyStatusFilter(filterValue) {
        sessionStorage.setItem('tv_status_filter', filterValue);

        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.getAttribute('data-filter') === filterValue) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        renderTable(currentOrders);
    }

    // Dynamic table HTML rendering
    function renderTable(orders) {
        const tbody = document.getElementById('tv-table-body');
        if (!tbody) return;

        const activeFilter = sessionStorage.getItem('tv_status_filter') || 'activas';

        let filtered = [];
        if (activeFilter === 'activas') {
            filtered = orders.filter(o => o.estado === 'Pendiente' || o.estado === 'En proceso');
        } else if (activeFilter === 'todos') {
            filtered = orders;
        } else {
            filtered = orders.filter(o => o.estado === activeFilter);
        }

        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 1.25rem;">
                        No hay órdenes de producción en este estado.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        filtered.forEach(orden => {
            let rowGlowClass = '';
            if (orden.estado !== 'Terminado' && orden.estado !== 'Cancelado') {
                if (orden.prioridad === 'URGENTE') rowGlowClass = 'row-glow-urgente';
                else if (orden.prioridad === 'PRÓXIMA') rowGlowClass = 'row-glow-proxima';
                else rowGlowClass = 'row-glow-normal';
            }

            const isHighlighted = updatedOrderIds.includes(orden.id) ? 'row-highlight-flash' : '';
            
            let fireClass = '';
            if (orden.estado === 'Terminado') fireClass = 'extinguished';
            else if (orden.estado === 'Cancelado') fireClass = 'hidden-fire';
            else if (!orden.mostrar_fuego) fireClass = 'hidden-fire';

            // Created At format
            const dateObj = new Date(orden.created_at);
            const hh = String(dateObj.getHours()).padStart(2, '0');
            const mm = String(dateObj.getMinutes()).padStart(2, '0');
            const d = String(dateObj.getDate()).padStart(2, '0');
            const m = String(dateObj.getMonth() + 1).padStart(2, '0');
            const y = String(dateObj.getFullYear()).substring(2);
            const creationTimeStr = `${hh}:${mm}<br><small style="font-size: 0.75rem; color: var(--text-muted);">${d}/${m}/${y}</small>`;

            let priorityBadgeClass = 'badge-cancelado';
            if (orden.prioridad === 'URGENTE') priorityBadgeClass = 'badge-urgente';
            else if (orden.prioridad === 'PRÓXIMA') priorityBadgeClass = 'badge-proxima';
            else if (orden.prioridad === 'NORMAL') priorityBadgeClass = 'badge-normal';
            
            const prioridadText = orden.prioridad || 'Sin fecha';

            let statusBadgeClass = 'badge-pendiente';
            if (orden.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
            else if (orden.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
            else if (orden.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';

            let progressFillClass = 'progress-fill-pendiente';
            if (orden.estado === 'En proceso') progressFillClass = 'progress-fill-proceso';
            else if (orden.estado === 'Terminado') progressFillClass = 'progress-fill-terminado';
            else if (orden.estado === 'Cancelado') progressFillClass = 'progress-fill-cancelado';

            let delDateStr = '-';
            let delTimeStr = '-';
            if (orden.fecha_entrega) {
                const rawDelDate = orden.fecha_entrega.split('-');
                delDateStr = `${rawDelDate[2]}/${rawDelDate[1]}/${rawDelDate[0]}`;
            }
            if (orden.hora_entrega) {
                delTimeStr = orden.hora_entrega.substring(0, 5);
            }

            let diasText = '-';
            let daysColor = 'var(--text-muted)';
            if (orden.dias_restantes !== null && orden.dias_restantes !== undefined) {
                if (orden.dias_restantes <= 0) {
                    diasText = 'Hoy';
                    daysColor = 'var(--priority-urgente)';
                } else {
                    diasText = orden.dias_restantes + 'd';
                    daysColor = orden.dias_restantes <= 2 ? 'var(--priority-proxima)' : 'var(--priority-normal)';
                }
            }

            let briefHtml = '<span style="color: var(--text-muted); font-weight: bold;">-</span>';
            if (orden.brief) {
                briefHtml = `<a href="${storageBaseUrl}/${orden.brief}" target="_blank" class="btn-view-brief">Ver Brief</a>`;
            }

            const isActive = selectedOrderId === orden.id ? 'active' : '';
            const rowStyle = selectedOrderId === orden.id ? 'style="background: rgba(0, 210, 255, 0.08);"' : '';

            html += `
                <tr class="tv-row ${rowGlowClass} ${isHighlighted} ${isActive}" ${rowStyle} data-id="${orden.id}" data-estado="${orden.estado}" onclick="selectOrder(${orden.id})">
                    <td style="text-align: center;">
                        <span id="fire-container-${orden.id}" class="fire-container ${fireClass}">
                            <span class="fire-flame">🔥</span>
                        </span>
                    </td>
                    <td><span style="font-weight: 600;">${creationTimeStr}</span></td>
                    <td><span class="badge ${priorityBadgeClass}">${prioridadText}</span></td>
                    <td><strong style="color: var(--blue-bright);">${orden.numero_op}</strong></td>
                    <td>${orden.marca}</td>
                    <td>${orden.presupuestista}</td>
                    <td>${orden.lider_produccion ? `<span style="color: var(--text-white);">${orden.lider_produccion}</span>` : `<em style="color: var(--text-muted); font-size: 0.9em;">Sin asignar</em>`}</td>
                    <td>
                        <span style="color: var(--text-white);">${delDateStr}</span><br>
                        <small style="color: var(--text-muted);">${delTimeStr}</small>
                    </td>
                    <td><span class="badge ${statusBadgeClass}">${orden.estado}</span></td>
                    <td>
                        <div class="progress-container">
                             <div class="progress-track">
                                 <div class="progress-fill ${progressFillClass}" style="width: ${orden.avance}%;"></div>
                             </div>
                             <span class="progress-text">${orden.avance}%</span>
                        </div>
                    </td>
                    <td style="font-weight: 700; color: ${daysColor};">${diasText}</td>
                    <td style="text-align: center;" onclick="event.stopPropagation();">${briefHtml}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // Update visible count in KPI card to match filtered rows
        const visibleKpi = document.getElementById('kpi-visibles');
        if (visibleKpi) {
            visibleKpi.textContent = filtered.length;
        }
    }

    // Dynamic Attention Immediate list
    function renderAtencionInmediata(orders) {
        const container = document.getElementById('atencion-grid-vertical');
        if (!container) return;

        const urgentes = orders.filter(o => o.dias_restantes !== null && o.dias_restantes !== undefined && o.dias_restantes < 3 && o.estado !== 'Terminado' && o.estado !== 'Cancelado');

        if (urgentes.length === 0) {
            container.innerHTML = `
                <div style="background: rgba(16, 24, 48, 0.45); border: 1px dashed var(--border-glass); border-radius: 12px; padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    No hay órdenes de atención inmediata pendientes (todas tienen más de 3 días para su entrega).
                </div>
            `;
            return;
        }

        let html = '';
        urgentes.forEach(urg => {
            const cardClass = urg.prioridad === 'PRÓXIMA' ? 'proxima' : '';
            
            let diasLabel = '-';
            if (urg.dias_restantes !== null && urg.dias_restantes !== undefined) {
                if (urg.dias_restantes < 0) {
                    diasLabel = `VENCIDA (${Math.abs(urg.dias_restantes)}d)`;
                } else if (urg.dias_restantes === 0) {
                    diasLabel = 'ENTREGA HOY';
                } else {
                    diasLabel = `${urg.dias_restantes} días rest.`;
                }
            }

            const rawDelDate = urg.fecha_entrega.split('-');
            const delDateStr = `${rawDelDate[2]}/${rawDelDate[1]}/${rawDelDate[0]}`;
            const delTimeStr = urg.hora_entrega.substring(0, 5);

            html += `
                <div class="atencion-card ${cardClass}" onclick="selectOrder(${urg.id})" style="cursor: pointer;">
                    <div class="atencion-header">
                        <span class="atencion-op">${urg.numero_op}</span>
                        <span class="atencion-dias">${diasLabel}</span>
                    </div>
                    <div class="atencion-content">
                         <p>Marca: <strong>${urg.marca}</strong></p>
                         <p>Presup.: <strong>${urg.presupuestista}</strong></p>
                         <p>Líder: <strong>${urg.lider_produccion ?? 'Sin asignar'}</strong></p>
                         <p>Entrega: <strong>${delDateStr} - ${delTimeStr}</strong></p>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Dynamic AJAX updates polling loop (runs every 5 seconds)
    function pollUpdates() {
        fetch(`/op/tv/updates?categoria={{ $categoria }}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error("HTTP error polling status");
            return res.json();
        })
        .then(data => {
            const newOrders = data.ordenes;
            const kpis = data.kpis;

            let notify = false;
            let tempUpdatedIds = [];

            newOrders.forEach(newOp => {
                const oldOp = currentOrders.find(o => o.id === newOp.id);
                if (!oldOp) {
                    // Trigger new order toast notification
                    notify = true;
                    tempUpdatedIds.push(newOp.id);
                    showCustomToast(`Nueva OP recibida: ${newOp.numero_op}`, 'success', '🔔');
                } else {
                    let changed = false;
                    let changeMsgs = [];
                    let type = 'info';
                    let icon = '⚠️';

                    if (oldOp.estado !== newOp.estado) {
                        changed = true;
                        if (newOp.estado === 'Terminado') {
                            changeMsgs.push(`${newOp.numero_op} fue completada`);
                            type = 'success';
                            icon = '✅';
                        } else if (newOp.estado === 'Cancelado') {
                            changeMsgs.push(`${newOp.numero_op} fue cancelada`);
                            type = 'danger';
                            icon = '⚠️';
                        } else {
                            changeMsgs.push(`${newOp.numero_op} cambió a ${newOp.estado}`);
                            if (newOp.prioridad === 'URGENTE') {
                                type = 'warning';
                                icon = '🔥';
                            }
                        }
                    }

                    if (oldOp.lider_produccion !== newOp.lider_produccion) {
                        changed = true;
                        if (newOp.lider_produccion) {
                            changeMsgs.push(`${newOp.numero_op} asignada a ${newOp.lider_produccion}`);
                        } else {
                            changeMsgs.push(`${newOp.numero_op} quedó sin líder asignado`);
                        }
                    }

                    if (!changed && (
                        oldOp.proyecto !== newOp.proyecto || 
                        oldOp.fecha_entrega !== newOp.fecha_entrega || 
                        oldOp.hora_entrega !== newOp.hora_entrega ||
                        oldOp.marca !== newOp.marca
                    )) {
                        changed = true;
                        changeMsgs.push(`${newOp.numero_op} fue modificada`);
                    }

                    if (changed) {
                        notify = true;
                        tempUpdatedIds.push(newOp.id);
                        changeMsgs.forEach(msg => showCustomToast(msg, type, icon));
                    }
                }
            });

            // Update timestamp
            const now = new Date();
            const timeStr = String(now.getDate()).padStart(2, '0') + '/' + 
                            String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                            now.getFullYear() + ' ' + 
                            String(now.getHours()).padStart(2, '0') + ':' + 
                            String(now.getMinutes()).padStart(2, '0') + ':' + 
                            String(now.getSeconds()).padStart(2, '0');
            document.getElementById('last-update-time').textContent = timeStr;

            if (notify) {
                if (isSoundEnabled) {
                    playAlertSound();
                }

                updatedOrderIds = tempUpdatedIds;

                // Reset highlighted classes after 7 seconds
                setTimeout(() => {
                    updatedOrderIds = [];
                    renderTable(currentOrders);
                }, 7000);
            }

            // Sync client arrays
            currentOrders = newOrders;
            database.length = 0;
            database.push(...currentOrders);

            // Re-render
            renderTable(currentOrders);
            renderAtencionInmediata(currentOrders);

            // Update KPIs in DOM
            document.getElementById('kpi-terminadas').textContent = kpis.terminadas;
            document.getElementById('kpi-pendientes').textContent = kpis.pendientes;
            document.getElementById('kpi-urgentes').textContent = kpis.urgentes;
            document.getElementById('kpi-visibles').textContent = kpis.visibles;

            // Retain selection of details
            if (selectedOrderId) {
                const refreshedOrder = currentOrders.find(o => o.id === selectedOrderId);
                if (refreshedOrder) {
                    populateDetail(refreshedOrder);
                } else {
                    hideDetail();
                }
            }
        })
        .catch(err => console.log("AJAX updates polling error:", err));
    }

    // Clock ticking
    function updateClock() {
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        document.getElementById('live-date').textContent = `${day}/${month}/${year}`;
        
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('live-time').textContent = `${hours}:${minutes}:${seconds}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);

        updateSoundButtonUI();

        // Apply saved filter or default
        const savedFilter = sessionStorage.getItem('tv_status_filter') || 'activas';
        applyStatusFilter(savedFilter);

        // Restore previously active details
        const savedId = sessionStorage.getItem('selected_op_id');
        if (savedId) {
            selectOrder(parseInt(savedId));
        }

        // Start polling updates every 5 seconds
        setInterval(pollUpdates, 5000);
    });
</script>
@endsection
