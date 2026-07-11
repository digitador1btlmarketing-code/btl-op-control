@extends('layouts.app')

@section('title', 'Mis Órdenes de Venta')

@section('content')
<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 800;">Panel de Ventas | Mis Órdenes</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Seguimiento en tiempo real de sus requerimientos de producción</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('op.create') }}" class="btn-primary" style="width: auto; padding: 10px 20px;">+ Crear OP</a>
    </div>
</div>

<!-- KPIs Grid -->
<div class="grid-5" style="margin-bottom: 25px;">
    <div class="kpi-card">
        <div id="kpi-total" class="kpi-value">{{ $ordenes->count() }}</div>
        <div class="kpi-label">Mis Órdenes</div>
    </div>
    <div class="kpi-card pending">
        <div id="kpi-pendientes" class="kpi-value" style="color: var(--state-pendiente);">{{ $ordenes->where('estado', 'Pendiente')->count() }}</div>
        <div class="kpi-label">Pendientes</div>
    </div>
    <div class="kpi-card process">
        <div id="kpi-en-proceso" class="kpi-value" style="color: var(--state-en-proceso);">{{ $ordenes->where('estado', 'En proceso')->count() }}</div>
        <div class="kpi-label">En Proceso</div>
    </div>
    <div class="kpi-card waiting">
        <div id="kpi-en-espera" class="kpi-value" style="color: var(--state-en-espera);">{{ $ordenes->where('estado', 'En espera')->count() }}</div>
        <div class="kpi-label">En Espera</div>
    </div>
    <div class="kpi-card finished">
        <div id="kpi-terminadas" class="kpi-value" style="color: var(--state-terminado);">{{ $ordenes->where('estado', 'Terminado')->count() }}</div>
        <div class="kpi-label">Terminadas</div>
    </div>
</div>

<!-- SECTION: SOLICITUDES DE CAMBIO DE FECHA -->
<div class="card" id="solicitudes-card" style="margin-bottom: 25px; border-color: rgba(0, 210, 255, 0.25);">
    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        📅 Solicitudes de Cambio de Fecha Pendientes
    </h3>
    <div id="solicitudes-container">
        @if($solicitudes->count() === 0)
            <p id="no-solicitudes-msg" style="color: var(--text-muted); font-size: 0.95rem; font-style: italic;">No hay solicitudes de cambio de fecha pendientes de aprobación.</p>
        @else
            <div style="overflow-x: auto;">
                <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>OP</th>
                            <th>Cliente / Marca</th>
                            <th>Entrega Actual</th>
                            <th>Fecha Solicitada</th>
                            <th>Razón de Cambio</th>
                            <th>Solicitado Por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="solicitudes-table-body">
                        @foreach($solicitudes as $req)
                            @php
                                $dtAct = \Carbon\Carbon::parse($req->fecha_actual)->format('d/m/Y') . ' ' . \Carbon\Carbon::parse($req->hora_actual)->format('H:i');
                                $dtSol = \Carbon\Carbon::parse($req->fecha_solicitada)->format('d/m/Y') . ' ' . \Carbon\Carbon::parse($req->hora_solicitada)->format('H:i');
                            @endphp
                            <tr id="req-row-{{ $req->id }}">
                                <td><strong>{{ $req->ordenProduccion->numero_op ?? 'OP' }}</strong></td>
                                <td>{{ $req->ordenProduccion->cliente ?? '-' }}<br><small style="color: var(--text-muted);">{{ $req->ordenProduccion->marca ?? '-' }}</small></td>
                                <td>{{ $dtAct }}</td>
                                <td><strong style="color: var(--blue-bright);">{{ $dtSol }}</strong></td>
                                <td style="max-width: 250px; white-space: normal;">{{ $req->razon_solicitud }}</td>
                                <td>{{ $req->solicitado_por_nombre }}<br><small style="color: var(--text-muted);">{{ $req->solicitado_por_codigo }}</small></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="approveRequest({{ $req->id }})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime);">
                                            Aceptar cambio
                                        </button>
                                        <button onclick="openRejectRequestModal({{ $req->id }})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                            Rechazar cambio
                                        </button>
                                        <button onclick="selectOrder({{ $req->orden_produccion_id }})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                            Ver detalle
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Main Table -->
<div class="card">
    <style>
        .category-tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-glass);
            position: relative;
        }
        .category-tab {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 600;
            padding: 10px 16px;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            margin-bottom: -1px; /* overlap the border */
        }
        .category-tab:hover {
            color: var(--text-white);
        }
        .category-tab.active {
            color: var(--blue-bright);
            font-weight: 700;
        }
        .category-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--blue-bright);
            box-shadow: 0 0 8px var(--blue-bright);
        }
        .admin-row {
            cursor: pointer;
        }
        .admin-row:hover {
            background: rgba(0, 210, 255, 0.04) !important;
        }
        .admin-row.active {
            background: rgba(0, 210, 255, 0.08) !important;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0;">
            Mis Órdenes de Producción
        </h3>
        
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <!-- Search by OP number -->
            <div style="position: relative; min-width: 220px;">
                <input 
                    type="text" 
                    id="search-op" 
                    placeholder="Buscar por número OP (Presione Enter)..." 
                    class="form-control" 
                    style="padding: 8px 12px; font-size: 0.9rem; height: 38px; width: 100%;"
                    value="{{ request('search') }}"
                    onkeydown="if(event.key === 'Enter') submitFilters()"
                >
            </div>
            
            <!-- Filter by Status -->
            <div style="min-width: 180px;">
                <select 
                    id="filter-status" 
                    class="form-control" 
                    style="padding: 8px 12px; font-size: 0.9rem; height: 38px; cursor: pointer; width: 100%;"
                    onchange="submitFilters()"
                >
                    <option value="activas" {{ request('status', 'activas') === 'activas' ? 'selected' : '' }}>Todas activas</option>
                    <option value="Pendiente" {{ request('status') === 'Pendiente' ? 'selected' : '' }}>Pendientes</option>
                    <option value="En proceso" {{ request('status') === 'En proceso' ? 'selected' : '' }}>En proceso</option>
                    <option value="En espera" {{ request('status') === 'En espera' ? 'selected' : '' }}>En espera</option>
                    <option value="Terminado" {{ request('status') === 'Terminado' ? 'selected' : '' }}>Terminadas</option>
                    <option value="Cancelado" {{ request('status') === 'Cancelado' ? 'selected' : '' }}>Canceladas</option>
                    <option value="todos" {{ request('status') === 'todos' ? 'selected' : '' }}>Todas</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    @php
        $activeCat = request('category', 'todos');
    @endphp
    <div class="category-tabs-container">
        <button class="category-tab {{ $activeCat === 'todos' ? 'active' : '' }}" data-category="todos" onclick="selectCategoryTab('todos')">Todas</button>
        <button class="category-tab {{ $activeCat === 'Branding' ? 'active' : '' }}" data-category="Branding" onclick="selectCategoryTab('Branding')">Branding</button>
        <button class="category-tab {{ $activeCat === 'Promocional' ? 'active' : '' }}" data-category="Promocional" onclick="selectCategoryTab('Promocional')">Promocional</button>
        <button class="category-tab {{ $activeCat === 'Reprocesos' ? 'active' : '' }}" data-category="Reprocesos" onclick="selectCategoryTab('Reprocesos')">Reprocesos</button>
    </div>

    <div style="overflow-x: auto;">
        <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th>Ticket OP</th>
                    <th>Categoría</th>
                    <th>Cliente</th>
                    <th>Marca</th>
                    <th>Estado</th>
                    <th>Avance</th>
                    <th>Fecha Entrega</th>
                    <th>Cambio de Fecha</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody id="vendedor-table-body">
                @forelse($ordenes as $orden)
                    @php
                        $req = $orden->solicitudPendiente;
                        $badgeCategoryClass = $orden->categoria === 'Branding' ? 'badge-normal' : 'badge-proxima';
                        $progressFillClass = $orden->estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                              ($orden->estado === 'En proceso' ? 'progress-fill-proceso' :
                                              ($orden->estado === 'Cancelado' ? 'progress-fill-cancelado' :
                                              ($orden->estado === 'En espera' ? 'progress-fill-en-espera' : 'progress-fill-terminado')));
                    @endphp
                    <tr class="admin-row" id="row-{{ $orden->id }}" onclick="selectOrder({{ $orden->id }})"
                        data-id="{{ $orden->id }}"
                        data-estado="{{ $orden->estado }}"
                        data-prioridad="{{ $orden->prioridad }}"
                        data-numero-op="{{ $orden->numero_op }}"
                        data-categoria="{{ $orden->categoria }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="color: var(--text-white);">{{ $orden->numero_op }}</strong>
                                <span id="fire-container-{{ $orden->id }}" class="fire-container @if($orden->estado === 'Terminado' || $orden->estado === 'Cancelado') extinguished @elseif(!$orden->mostrar_fuego) hidden-fire @endif" title="Alerta de prioridad temporal">
                                    <span class="fire-flame">🔥</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            @php
                                $badgeCategoryClass = $orden->categoria === 'Branding' ? 'badge-normal' : 
                                                      ($orden->categoria === 'Promocional' ? 'badge-proxima' : '');
                            @endphp
                            <span class="badge {{ $badgeCategoryClass }}" style="{{ $orden->categoria === 'Reprocesos' ? 'background: rgba(255, 95, 56, 0.15); color: #ff5f38; border: 1px solid rgba(255, 95, 56, 0.3);' : '' }}">
                                {{ $orden->categoria === 'Reprocesos' ? 'REPROCESO' : $orden->categoria }}
                            </span>
                        </td>
                        <td>{{ $orden->cliente }}</td>
                        <td>{{ $orden->marca }}</td>
                        <td>
                            @php
                                $statusBadgeClass = 'badge-pendiente';
                                if ($orden->estado === 'En proceso') $statusBadgeClass = 'badge-proceso';
                                if ($orden->estado === 'Terminado') $statusBadgeClass = 'badge-terminado';
                                if ($orden->estado === 'Cancelado') $statusBadgeClass = 'badge-cancelado';
                                if ($orden->estado === 'En espera') $statusBadgeClass = 'badge-en-espera';
                            @endphp
                            <span class="badge {{ $statusBadgeClass }}">{{ $orden->estado }}</span>
                        </td>
                        <td>
                            <div class="progress-container" style="min-width: 100px;">
                                <div class="progress-track">
                                    <div id="progress-fill-{{ $orden->id }}" class="progress-fill {{ $progressFillClass }}" style="width: {{ $orden->avance }}%;"></div>
                                </div>
                                <span id="progress-text-{{ $orden->id }}" class="progress-text">{{ $orden->avance }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-dash">{{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</span>
                            <br>
                            <small style="color: var(--text-muted); font-weight: 600;">
                                {{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }}
                            </small>
                        </td>
                        <td id="change-status-cell-{{ $orden->id }}">
                            @if($req)
                                <span class="badge badge-pendiente">Pendiente de aprobación</span>
                            @else
                                @php
                                    $lastReq = $orden->solicitudesCambio()->orderBy('created_at', 'desc')->first();
                                @endphp
                                @if($lastReq && $lastReq->estado_solicitud === 'Aprobada')
                                    <span class="badge badge-terminado">Aprobada</span>
                                @elseif($lastReq && $lastReq->estado_solicitud === 'Rechazada')
                                    <span class="badge badge-cancelado" title="Razón: {{ $lastReq->razon_rechazo }}">Rechazada</span>
                                @else
                                    <span style="color: var(--text-muted); font-weight: 500;">-</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn-save-inline" style="background: var(--bg-btn-view); border-color: var(--border-btn-view); color: var(--blue-bright);">
                                Ver
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr id="empty-row">
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No tiene órdenes de producción registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if($ordenes->hasPages())
        <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding: 10px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-glass); border-radius: 8px;">
            @if($ordenes->onFirstPage())
                <span style="opacity: 0.5; pointer-events: none; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 6px; color: var(--text-muted); font-size: 0.85rem;">« Anterior</span>
            @else
                <a href="{{ $ordenes->appends(request()->query())->previousPageUrl() }}" class="btn-save-inline" style="padding: 6px 12px; background: rgba(0, 210, 255, 0.1); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 6px; color: var(--blue-bright); text-decoration: none; font-size: 0.85rem;">« Anterior</a>
            @endif

            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Página {{ $ordenes->currentPage() }} de {{ $ordenes->lastPage() }}</span>

            @if($ordenes->hasMorePages())
                <a href="{{ $ordenes->appends(request()->query())->nextPageUrl() }}" class="btn-save-inline" style="padding: 6px 12px; background: rgba(0, 210, 255, 0.1); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 6px; color: var(--blue-bright); text-decoration: none; font-size: 0.85rem;">Siguiente »</a>
            @else
                <span style="opacity: 0.5; pointer-events: none; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 6px; color: var(--text-muted); font-size: 0.85rem;">Siguiente »</span>
            @endif
        </div>
    @endif
</div>

<!-- Panel de Detalle (Sólo Lectura) -->
<div id="detail-panel" class="detail-panel hidden" style="margin-top: 25px; position: relative;">
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
            <div class="detail-label">Solicitante</div>
            <div id="detail-solicitante" class="detail-val">-</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Plano / Diseño</div>
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

    <!-- Detalles de la OP -->
    <div style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1rem; color: var(--blue-bright); margin-bottom: 10px;">Detalles de la OP</h4>
        <div id="detail-detalles" style="white-space: pre-wrap; color: var(--text-white); font-size: 0.95rem; line-height: 1.5; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-glass); padding: 12px; border-radius: 8px;">-</div>
    </div>

    <!-- Reproceso request block and history -->
    <div id="reproceso-action-container" style="margin-top: 15px;"></div>

    <div id="detail-reprocesos-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1.05rem; color: var(--priority-proxima); margin-bottom: 10px; font-weight: 700;">🔄 Historial de Reprocesos</h4>
        <div id="detail-reprocesos-content">-</div>
    </div>

    <!-- Date change status view in details -->
    <div id="detail-date-change-status-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1.05rem; color: var(--blue-bright); margin-bottom: 10px; font-weight: 700;">Estado de Cambio de Fecha</h4>
        <div id="detail-date-change-info" style="font-size: 0.9rem; line-height: 1.4; margin-bottom: 10px;">-</div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a id="btn-download-pdf-op" href="#" target="_blank" class="btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: rgba(0, 210, 255, 0.1); border-color: rgba(0, 210, 255, 0.2); color: var(--blue-bright);">
                📄 Descargar PDF OP
            </a>
            <button id="btn-request-date-change" onclick="openRequestDateChangeModal()" class="btn-primary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; display: none;">
                Solicitar cambio de fecha
            </button>
        </div>
    </div>

    <!-- Timeline of events (History) -->
    <div id="detail-history-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <button id="btn-show-history" onclick="openHistoryModal()" class="filter-btn" style="width: auto; padding: 8px 16px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; background: rgba(255, 255, 255, 0.05); border-color: var(--border-glass); color: var(--text-white);">
            📜 Ver historial
        </button>
    </div>
</div>

</div>

<!-- History Modal -->
<div id="history-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 650px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0; text-align: left; position: relative;">
        <button onclick="closeHistoryModal()" class="btn-logout" style="position: absolute; top: 15px; right: 20px; font-size: 0.8rem; padding: 4px 10px; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">Cerrar historial</button>
        
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--green-lime); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            📜 Historial de OP: <span id="history-modal-op-title" style="color: var(--blue-bright);"></span>
        </h3>
        
        <div id="modal-history-timeline" style="max-height: 400px; overflow-y: auto; font-size: 0.9rem; line-height: 1.5; color: var(--text-white); background: rgba(255, 255, 255, 0.02); padding: 15px; border-radius: 8px; border: 1px solid var(--border-glass); margin-bottom: 20px;">
            <em style="color: var(--text-muted);">Cargando historial...</em>
        </div>
        
        <div style="display: flex; justify-content: flex-end;">
            <button onclick="closeHistoryModal()" class="filter-btn" style="padding: 10px 20px; font-weight: 600;">Cerrar</button>
        </div>
    </div>
</div>

<!-- Request Date Change Modal -->
<div id="request-date-change-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 500px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0; text-align: left;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            📅 Solicitar Cambio de Fecha
        </h3>
        
        <form id="request-date-change-form" onsubmit="submitDateChangeRequest(event)">
            @csrf
            <input type="hidden" name="orden_produccion_id" id="change-op-id">
            
            <div class="grid-2" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Fecha Actual</label>
                    <input type="text" id="change-fecha-actual" class="form-control" disabled style="background: rgba(255,255,255,0.05); text-align: center;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Hora Actual</label>
                    <input type="text" id="change-hora-actual" class="form-control" disabled style="background: rgba(255,255,255,0.05); text-align: center;">
                </div>
            </div>
            
            <div class="grid-2" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="fecha_solicitada" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Nueva Fecha *</label>
                    <input type="date" name="fecha_solicitada" id="fecha_solicitada" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="hora_solicitada" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Nueva Hora *</label>
                    <input type="time" name="hora_solicitada" id="hora_solicitada" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="razon_solicitud" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Razón del Cambio *</label>
                <textarea name="razon_solicitud" id="razon_solicitud" class="form-control" rows="3" placeholder="Escriba la razón de forma detallada..." required style="resize: none;"></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRequestDateChangeModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 25px; font-weight: 700;">Enviar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Request Modal -->
<div id="reject-request-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 480px; width: 90%; border-color: rgba(255, 51, 102, 0.4); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--priority-urgente); margin-bottom: 20px;">
            ❌ Rechazar Solicitud de Cambio
        </h3>
        
        <form id="reject-request-form" onsubmit="submitRejectRequest(event)">
            @csrf
            <input type="hidden" name="solicitud_id" id="reject-solicitud-id">
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="razon_rechazo">Razón del Rechazo *</label>
                <textarea name="razon_rechazo" id="razon_rechazo" class="form-control" rows="3" required placeholder="Escriba el motivo por el cual se rechaza este cambio de entrega..." style="resize: none;"></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRejectRequestModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: var(--priority-urgente); border-color: var(--priority-urgente); width: auto; padding: 10px 25px; font-weight: 700;">Rechazar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- Solicitar Reproceso Modal -->
<div id="request-reproceso-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 520px; width: 90%; border-color: rgba(255, 95, 56, 0.4); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #ff5f38; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            🔄 Solicitar Reproceso
        </h3>
        
        <form id="request-reproceso-form" onsubmit="submitReprocesoRequest(event)" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="orden_id" id="reproceso-orden-id">
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="reproceso-motivo">Motivo *</label>
                <select name="motivo" id="reproceso-motivo" required style="width: 100%;">
                    <option value="" disabled selected>Seleccione un motivo</option>
                    <option value="Error de diseño">Error de diseño</option>
                    <option value="Error de producción">Error de producción</option>
                    <option value="Daño en transporte">Daño en transporte</option>
                    <option value="Solicitud del cliente">Solicitud del cliente</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="reproceso-descripcion">Descripción detallada *</label>
                <textarea name="descripcion" id="reproceso-descripcion" class="form-control" rows="3" required placeholder="Describa el problema detalladamente..." style="resize: none;"></textarea>
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="reproceso-fecha">Fecha requerida (Opcional)</label>
                <input type="date" name="fecha_requerida" id="reproceso-fecha" class="form-control">
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="reproceso-archivo">Archivo adjunto (Opcional)</label>
                <input type="file" name="archivo" id="reproceso-archivo" class="form-control" style="padding: 6px;">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRequestReprocesoModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: #ff5f38; border-color: #ff5f38; width: auto; padding: 10px 25px; font-weight: 700;">Enviar Solicitud</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const loggedInUserCode = "{{ session('user_code') }}";
    const loggedInUserRole = "{{ session('user_role') }}";
    let selectedOrderId = null;
    let activeCategory = '{{ request('category', 'todos') }}';
    let allOrders = @json($ordenes->items());
    const storageBaseUrl = "/storage";

    function selectOrder(id) {
        const order = allOrders.find(o => o.id === id);
        if (!order) return;

        if (selectedOrderId === id) {
            hideDetail();
            return;
        }

        selectedOrderId = id;

        // Highlight row
        document.querySelectorAll('.admin-row').forEach(row => {
            row.classList.remove('active');
            if (parseInt(row.getAttribute('id').replace('row-', '')) === id) {
                row.classList.add('active');
            }
        });

        populateDetail(order);

        const detailPanel = document.getElementById('detail-panel');
        detailPanel.classList.remove('hidden');
        detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideDetail() {
        selectedOrderId = null;
        document.querySelectorAll('.admin-row').forEach(row => {
            row.classList.remove('active');
        });
        const panel = document.getElementById('detail-panel');
        if (panel) panel.classList.add('hidden');
    }

    function selectCategoryTab(category) {
        activeCategory = category;
        
        // Update active class on tab buttons
        document.querySelectorAll('.category-tab').forEach(tab => {
            if (tab.getAttribute('data-category') === category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        submitFilters();
    }

    function submitFilters() {
        const searchVal = document.getElementById('search-op').value.trim();
        const filterVal = document.getElementById('filter-status').value;
        const categoryVal = activeCategory;
        
        window.location.href = `{{ route('op.mis_ordenes') }}?search=${encodeURIComponent(searchVal)}&status=${encodeURIComponent(filterVal)}&category=${encodeURIComponent(categoryVal)}`;
    }

    function applyVendedorFilters() {
        // Passive, no client-side filtering needed since server-side pagination & filtering is in place.
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
        
        let formattedDate = '-';
        let formattedTime = '-';
        if (order.fecha_entrega) {
            const rawDate = order.fecha_entrega.split('-');
            formattedDate = `${rawDate[2]}/${rawDate[1]}/${rawDate[0]}`;
        }
        if (order.hora_entrega) {
            formattedTime = order.hora_entrega.substring(0, 5);
        }
        document.getElementById('detail-entrega').textContent = `${formattedDate} a las ${formattedTime} hrs`;
        
        let statusBadgeClass = 'badge-pendiente';
        if (order.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
        if (order.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
        if (order.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';
        document.getElementById('detail-estado').innerHTML = `<span class="badge ${statusBadgeClass}">${order.estado}</span>`;
        
        document.getElementById('detail-avance').textContent = `${order.avance}%`;
        document.getElementById('detail-entregar-a').textContent = order.entregar_a;
        document.getElementById('detail-solicitante').textContent = order.solicitante || 'No disponible';
        document.getElementById('detail-detalles').textContent = order.detalles && order.detalles.trim() !== '' ? order.detalles : 'Sin detalles adicionales.';
        
        const briefDiv = document.getElementById('detail-brief');
        let filesHtml = '';
        if (order.archivos && order.archivos.length > 0) {
            order.archivos.forEach((file) => {
                const fileName = file.file_name || 'Archivo';
                filesHtml += `
                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <span style="color: var(--text-white); font-size: 0.9rem; word-break: break-all;">${fileName}</span>
                        <a href="/op/descargar-archivo/${file.id}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; text-decoration: none; white-space: nowrap;">Descargar</a>
                    </div>`;
            });
        } else if (order.brief) {
            const fileName = order.brief.split('/').pop() || 'Plano';
            filesHtml = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: var(--text-white); font-size: 0.9rem; word-break: break-all;">${fileName}</span>
                    <a href="/op/descargar-brief/${order.id}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; text-decoration: none; white-space: nowrap;">Descargar</a>
                </div>`;
        } else {
            filesHtml = '-';
        }
        briefDiv.innerHTML = filesHtml;

        const pdfBtn = document.getElementById('btn-download-pdf-op');
        if (pdfBtn) {
            pdfBtn.href = `/op/exportar/detalle/${order.id}`;
        }

        const instSection = document.getElementById('detail-installation-section');
        if (order.entregar_a === 'Instaladores') {
            instSection.classList.remove('hidden');
            document.getElementById('detail-lugar').textContent = order.lugar_instalacion || '-';
            
            let fmtInstDate = '-';
            let fmtInstTime = '-';
            if (order.fecha_instalacion) {
                const rawInstDate = order.fecha_instalacion.split('-');
                fmtInstDate = `${rawInstDate[2]}/${rawInstDate[1]}/${rawInstDate[0]}`;
            }
            if (order.hora_instalacion) {
                fmtInstTime = order.hora_instalacion.substring(0, 5);
            }
            document.getElementById('detail-fecha-inst').textContent = `${fmtInstDate} - ${fmtInstTime} hrs`;
            
            if (order.fecha_desinstalacion) {
                const rawDesinstDate = order.fecha_desinstalacion.split('-');
                const fmtDesinstDate = `${rawDesinstDate[2]}/${rawDesinstDate[1]}/${rawDesinstDate[0]}`;
                const fmtDesinstTime = order.hora_desinstalacion ? order.hora_desinstalacion.substring(0, 5) : '00:00';
                document.getElementById('detail-fecha-desinst').textContent = `${fmtDesinstDate} - ${fmtDesinstTime} hrs`;
            } else {
                document.getElementById('detail-fecha-desinst').textContent = 'No hay desinstalación';
            }
        } else {
            instSection.classList.add('hidden');
        }

        // Display change request info
        const infoDiv = document.getElementById('detail-date-change-info');
        const btnChange = document.getElementById('btn-request-date-change');
        if (order.solicitud_pendiente) {
            const req = order.solicitud_pendiente;
            const reqDate = req.fecha_solicitada.split('-').reverse().join('/');
            const reqTime = req.hora_solicitada.substring(0, 5);

            // Authorization logic: Vendor can approve if the request came from Admin/Promo/Branding and Vendor created the OP
            const isSolicitor = (req.solicitado_por_codigo === loggedInUserCode);
            let isAuthorized = false;
            if (!isSolicitor) {
                const solicitorRol = req.solicitado_por_rol;
                if (solicitorRol === 'jefe_ventas' || solicitorRol === 'ventas') {
                    if (loggedInUserRole === 'admin') {
                        isAuthorized = true;
                    } else if (loggedInUserRole === 'admin_branding' && order.categoria === 'Branding') {
                        isAuthorized = true;
                    } else if (loggedInUserRole === 'admin_promo' && order.categoria === 'Promocional') {
                        isAuthorized = true;
                    }
                } else if (['admin', 'admin_promo', 'admin_branding'].includes(solicitorRol)) {
                    if (loggedInUserRole === 'ventas' && order.creado_por_codigo === loggedInUserCode) {
                        isAuthorized = true;
                    } else if (loggedInUserRole === 'jefe_ventas') {
                        const isCreatorVendorOfJefe = (order.creado_por_jefe_codigo === loggedInUserCode);
                        if (order.creado_por_codigo === loggedInUserCode || order.creado_por_codigo === 'ADMIN-PROD-2026' || isCreatorVendorOfJefe) {
                            isAuthorized = true;
                        }
                    }
                }
            }

            infoDiv.innerHTML = `
                <div style="background: rgba(243, 166, 59, 0.08); border: 1px solid rgba(243, 166, 59, 0.2); padding: 10px; border-radius: 8px;">
                    <span style="color: var(--state-pendiente); font-weight: 600; display: block; margin-bottom: 5px;">⚠️ Solicitud Pendiente de Aprobación</span>
                    Nueva fecha sugerida: <strong>${reqDate} ${reqTime} hrs</strong><br>
                    Razón: <span style="font-style: italic;">"${req.razon_solicitud}"</span>
                    ${isAuthorized ? `
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                        <button onclick="approveRequest(${req.id})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime); padding: 5px 12px; font-size: 0.8rem; cursor: pointer; border-radius: 4px;">
                            Aceptar cambio
                        </button>
                        <button onclick="openRejectRequestModal(${req.id})" class="btn-logout" style="padding: 5px 12px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3); border-radius: 4px; margin-bottom: 0;">
                            Rechazar cambio
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
            if (btnChange) btnChange.style.display = 'none';
        } else {
            infoDiv.innerHTML = '<span style="color: var(--text-muted);">No hay cambios pendientes de aprobación.</span>';
            if (btnChange) btnChange.style.display = 'inline-block';
        }

        // Reprocesos request block and history
        const actionContainer = document.getElementById('reproceso-action-container');
        if (actionContainer) {
            actionContainer.innerHTML = '';
            if (order.estado === 'Terminado') {
                const pendingReproceso = order.solicitudes_reproceso ? order.solicitudes_reproceso.find(s => s.estado === 'Pendiente') : null;
                const hasActiveReproceso = order.reprocesos && order.reprocesos.some(r => r.estado !== 'Cancelado');
                
                if (pendingReproceso) {
                    let attachmentHtml = '';
                    if (pendingReproceso.archivo_adjunto) {
                        attachmentHtml = `<br><strong>Adjunto:</strong> <a href="/storage/${pendingReproceso.archivo_adjunto}" target="_blank" style="color: var(--blue-bright); text-decoration: underline;">Descargar archivo</a>`;
                    }
                    let reqDateStr = 'Sin especificar';
                    if (pendingReproceso.fecha_requerida) {
                        reqDateStr = pendingReproceso.fecha_requerida.split('-').reverse().join('/');
                    }
                    actionContainer.innerHTML = `
                        <div style="background: rgba(255, 95, 56, 0.08); border: 1px solid rgba(255, 95, 56, 0.2); padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; line-height: 1.4; text-align: left;">
                            <strong style="color: #ff5f38; display: block; margin-bottom: 5px;">⏳ Solicitud de Reproceso Pendiente</strong>
                            <strong>Solicitado por:</strong> ${pendingReproceso.solicitado_por_nombre || 'Desconocido'}<br>
                            <strong>Motivo:</strong> ${pendingReproceso.motivo || '-'}<br>
                            <strong>Descripción:</strong> <span style="font-style: italic;">"${pendingReproceso.descripcion || '-'}"</span><br>
                            <strong>Fecha requerida:</strong> ${reqDateStr}
                            ${attachmentHtml}
                        </div>
                    `;
                } else if (hasActiveReproceso) {
                    const rep = order.reprocesos.find(r => r.estado !== 'Cancelado');
                    actionContainer.innerHTML = `<span class="badge badge-proceso" style="padding: 6px 12px; font-size: 0.85rem; display: inline-block;">🔄 Ya existe un reproceso activo: <strong>${rep.numero_op}</strong></span>`;
                } else {
                    const userRole = '{{ session("user_role") }}';
                    if (userRole === 'ventas' || userRole === 'jefe_ventas') {
                        actionContainer.innerHTML = `<button onclick="openRequestReprocesoModal(${order.id})" class="btn-save-inline" style="background: rgba(255, 95, 56, 0.15); color: #ff5f38; border: 1px solid rgba(255, 95, 56, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.85rem;">🔄 Solicitar Reproceso</button>`;
                    }
                }
            }
        }

        const reprocesosDiv = document.getElementById('detail-reprocesos-content');
        if (reprocesosDiv) {
            if (order.reprocesos && order.reprocesos.length > 0) {
                let html = '<table style="width:100%; font-size:0.85rem; border-collapse:collapse; text-align:left;">';
                html += '<thead><tr style="border-bottom:1px solid var(--border-glass);"><th style="padding:4px;">Código OP</th><th style="padding:4px;">Estado</th><th style="padding:4px;">Líder</th></tr></thead><tbody>';
                order.reprocesos.forEach(rep => {
                    let badgeClass = 'badge-pendiente';
                    if (rep.estado === 'En proceso') badgeClass = 'badge-proceso';
                    if (rep.estado === 'Terminado') badgeClass = 'badge-terminado';
                    if (rep.estado === 'Cancelado') badgeClass = 'badge-cancelado';
                    if (rep.estado === 'En espera') badgeClass = 'badge-en-espera';
                    html += `<tr style="border-bottom:1px solid rgba(255,255,255,0.05);"><td style="padding:6px 4px;"><strong>${rep.numero_op}</strong></td><td style="padding:6px 4px;"><span class="badge ${badgeClass}">${rep.estado}</span></td><td style="padding:6px 4px;">${rep.lider_produccion || '<em>Sin asignar</em>'}</td></tr>`;
                });
                html += '</tbody></table>';
                reprocesosDiv.innerHTML = html;
            } else {
                reprocesosDiv.innerHTML = '<span style="color: var(--text-muted); font-style: italic;">Sin reprocesos</span>';
            }
        }
    }

    function openRequestReprocesoModal(id) {
        document.getElementById('reproceso-orden-id').value = id;
        document.getElementById('request-reproceso-form').reset();
        document.getElementById('request-reproceso-modal').classList.remove('hidden');
    }

    function closeRequestReprocesoModal() {
        document.getElementById('request-reproceso-modal').classList.add('hidden');
    }

    function submitReprocesoRequest(event) {
        event.preventDefault();
        const id = document.getElementById('reproceso-orden-id').value;
        const form = document.getElementById('request-reproceso-form');
        const formData = new FormData(form);

        fetch(`/op/solicitar-reproceso/${id}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            closeRequestReprocesoModal();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            closeRequestReprocesoModal();
            showToast('Error de red al enviar la solicitud.', 'error');
        });
    }

    // Polling updates for vendedor panel (with in-flight guard and backoff)
    let isPollingVendedor = false;
    let vendedorPollTimer = null;
    let vendedorErrorBackoff = 15000;

    function scheduleVendedorPoll(delay) {
        clearTimeout(vendedorPollTimer);
        vendedorPollTimer = setTimeout(pollVendedorUpdates, delay);
    }

    function pollVendedorUpdates() {
        if (isPollingVendedor) return;
        isPollingVendedor = true;

        const searchVal = document.getElementById('search-op').value.trim();
        const filterVal = document.getElementById('filter-status').value;
        const categoryVal = activeCategory;
        const pageVal = '{{ $ordenes->currentPage() }}';

        fetch(`/op/mis-ordenes/updates?search=${encodeURIComponent(searchVal)}&status=${encodeURIComponent(filterVal)}&category=${encodeURIComponent(categoryVal)}&page=${pageVal}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(data => {
            const newOrders = data.ordenes;
            
            // Remove deleted
            const tbody = document.getElementById('vendedor-table-body');
            const rows = tbody.querySelectorAll('.admin-row');
            rows.forEach(row => {
                const id = parseInt(row.getAttribute('id').replace('row-', ''));
                if (!newOrders.some(o => o.id === id)) {
                    row.remove();
                }
            });

            // Update KPIs
            let total = newOrders.length;
            let pendientes = 0;
            let proceso = 0;
            let espera = 0;
            let terminadas = 0;

            newOrders.forEach(orden => {
                if (orden.estado === 'Pendiente') pendientes++;
                if (orden.estado === 'En proceso') proceso++;
                if (orden.estado === 'En espera') espera++;
                if (orden.estado === 'Terminado') terminadas++;

                let row = document.getElementById('row-' + orden.id);
                if (!row) {
                    // Create simple row
                    row = document.createElement('tr');
                    row.className = 'admin-row';
                    row.id = 'row-' + orden.id;
                    row.onclick = () => selectOrder(orden.id);
                    tbody.appendChild(row);
                }

                row.setAttribute('data-id', orden.id);
                row.setAttribute('data-estado', orden.estado);
                row.setAttribute('data-prioridad', orden.prioridad || '');
                row.setAttribute('data-numero-op', orden.numero_op);
                row.setAttribute('data-categoria', orden.categoria);

                // Update columns
                let formattedDate = '-';
                let formattedTime = '-';
                if (orden.fecha_entrega) {
                    const parts = orden.fecha_entrega.split('-');
                    formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
                }
                if (orden.hora_entrega) {
                    formattedTime = orden.hora_entrega.substring(0, 5);
                }

                const fireClass = (orden.estado === 'Terminado' || orden.estado === 'Cancelado') ? 'extinguished' : (!orden.mostrar_fuego ? 'hidden-fire' : '');
                const progressFillClass = orden.estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                          (orden.estado === 'En proceso' ? 'progress-fill-proceso' :
                                          (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' :
                                          (orden.estado === 'En espera' ? 'progress-fill-en-espera' : 'progress-fill-terminado')));
                
                let statusBadgeClass = 'badge-pendiente';
                if (orden.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
                if (orden.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
                if (orden.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';
                if (orden.estado === 'En espera') statusBadgeClass = 'badge-en-espera';

                let requestBadge = '<span style="color: var(--text-muted); font-weight: 500;">-</span>';
                if (orden.solicitud_pendiente) {
                    requestBadge = '<span class="badge badge-pendiente">Pendiente de aprobación</span>';
                }

                let badgeCategoryClass = orden.categoria === 'Branding' ? 'badge-normal' : 
                                      (orden.categoria === 'Promocional' ? 'badge-proxima' : '');
                let catText = (orden.categoria === 'Reprocesos' || orden.categoria === 'Reproceso' || orden.categoria === 'REPROCESO') ? 'REPROCESO' : orden.categoria;
                let catStyle = (orden.categoria === 'Reprocesos' || orden.categoria === 'Reproceso' || orden.categoria === 'REPROCESO') ? 'background: rgba(255, 95, 56, 0.15); color: #ff5f38; border: 1px solid rgba(255, 95, 56, 0.3);' : '';
                let catBadge = `<span class="badge ${badgeCategoryClass}" style="${catStyle}">${catText}</span>`;

                row.innerHTML = `
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: var(--text-white);">${orden.numero_op}</strong>
                            <span id="fire-container-${orden.id}" class="fire-container ${fireClass}" title="Alerta de prioridad temporal">
                                <span class="fire-flame">🔥</span>
                            </span>
                        </div>
                    </td>
                    <td>${catBadge}</td>
                    <td>${orden.cliente}</td>
                    <td>${orden.marca}</td>
                    <td>
                        <span class="badge ${statusBadgeClass}">${orden.estado}</span>
                    </td>
                    <td>
                        <div class="progress-container" style="min-width: 100px;">
                            <div class="progress-track">
                                <div id="progress-fill-${orden.id}" class="progress-fill ${progressFillClass}" style="width: ${orden.avance}%;"></div>
                            </div>
                            <span id="progress-text-${orden.id}" class="progress-text">${orden.avance}%</span>
                        </div>
                    </td>
                    <td>
                        <span class="text-dash">${formattedDate}</span>
                        <br>
                        <small style="color: var(--text-muted); font-weight: 600;">
                            ${formattedTime}
                        </small>
                    </td>
                    <td id="change-status-cell-${orden.id}">${requestBadge}</td>
                    <td>
                        <button type="button" class="btn-save-inline" style="background: var(--bg-btn-view); border-color: var(--border-btn-view); color: var(--blue-bright);">
                            Ver
                        </button>
                    </td>
                `;

                if (selectedOrderId === orden.id) {
                    row.classList.add('active');
                } else {
                    row.classList.remove('active');
                }
            });

            document.getElementById('kpi-total').textContent = total;
            document.getElementById('kpi-pendientes').textContent = pendientes;
            document.getElementById('kpi-en-proceso').textContent = proceso;
            document.getElementById('kpi-en-espera').textContent = espera;
            document.getElementById('kpi-terminadas').textContent = terminadas;

            allOrders = newOrders;
            applyVendedorFilters();

            // Sync solicitudes container
            if (data.solicitudes) {
                const solContainer = document.getElementById('solicitudes-container');
                const newRequests = data.solicitudes;
                if (newRequests.length === 0) {
                    solContainer.innerHTML = `<p id="no-solicitudes-msg" style="color: var(--text-muted); font-size: 0.95rem; font-style: italic;">No hay solicitudes de cambio de fecha pendientes de aprobación.</p>`;
                } else {
                    let tableHtml = `
                        <div style="overflow-x: auto;">
                            <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th>OP</th>
                                        <th>Cliente / Marca</th>
                                        <th>Entrega Actual</th>
                                        <th>Fecha Solicitada</th>
                                        <th>Razón de Cambio</th>
                                        <th>Solicitado Por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="solicitudes-table-body">
                    `;

                    newRequests.forEach(req => {
                        const dtAct = new Date(req.fecha_actual).toLocaleDateString('es-NI') + ' ' + req.hora_actual.substring(0, 5);
                        const dtSol = new Date(req.fecha_solicitada).toLocaleDateString('es-NI') + ' ' + req.hora_solicitada.substring(0, 5);
                        tableHtml += `
                            <tr id="req-row-${req.id}">
                                <td><strong>${req.orden_produccion.numero_op}</strong></td>
                                <td>${req.orden_produccion.cliente}<br><small style="color: var(--text-muted);">${req.orden_produccion.marca}</small></td>
                                <td>${dtAct}</td>
                                <td><strong style="color: var(--blue-bright);">${dtSol}</strong></td>
                                <td style="max-width: 250px; white-space: normal;">${req.razon_solicitud}</td>
                                <td>${req.solicitado_por_nombre}<br><small style="color: var(--text-muted);">${req.solicitado_por_codigo}</small></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="approveRequest(${req.id})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime);">
                                            Aceptar cambio
                                        </button>
                                        <button onclick="openRejectRequestModal(${req.id})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                            Rechazar cambio
                                        </button>
                                        <button onclick="selectOrder(${req.orden_produccion_id})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                            Ver detalle
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    tableHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    solContainer.innerHTML = tableHtml;
                }
            }

            // Sync open detail
            if (selectedOrderId) {
                const refreshedOrder = allOrders.find(o => o.id === selectedOrderId);
                if (refreshedOrder) {
                    populateDetail(refreshedOrder);
                } else {
                    hideDetail();
                }
            }

            if (data.recent_events) {
                processRecentEvents(data.recent_events);
            }
        })
        .catch(err => {
            console.log("AJAX updates polling error:", err);
            vendedorErrorBackoff = Math.min(vendedorErrorBackoff * 2, 60000);
        })
        .finally(() => {
            isPollingVendedor = false;
            scheduleVendedorPoll(vendedorErrorBackoff === 15000 ? 15000 : vendedorErrorBackoff);
            if (vendedorErrorBackoff !== 15000) vendedorErrorBackoff = 15000;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Start polling updates every 15 seconds with in-flight guard
        scheduleVendedorPoll(15000);
    });
    // Modal: Collapsible History functions
    function openHistoryModal() {
        if (!selectedOrderId) return;
        const order = allOrders.find(o => o.id === selectedOrderId);
        if (!order) return;
        
        document.getElementById('history-modal-op-title').textContent = order.numero_op;
        const timeline = document.getElementById('modal-history-timeline');
        timeline.innerHTML = '<em style="color: var(--text-muted); text-align: center; display: block; padding: 20px;">Cargando historial...</em>';
        
        document.getElementById('history-modal').classList.remove('hidden');
        
        fetch('/op/historial/' + order.id)
            .then(res => {
                if (!res.ok) throw new Error('Error al obtener el historial');
                return res.json();
            })
            .then(history => {
                if (history.length === 0) {
                    timeline.innerHTML = '<em style="color: var(--text-muted); text-align: center; display: block; padding: 20px;">No hay eventos registrados para esta orden.</em>';
                    return;
                }
                
                let tableHtml = `
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-glass);">
                                    <th style="padding: 10px; color: var(--blue-bright); font-weight: 700; width: 18%;">Fecha</th>
                                    <th style="padding: 10px; color: var(--blue-bright); font-weight: 700; width: 12%;">Hora</th>
                                    <th style="padding: 10px; color: var(--blue-bright); font-weight: 700; width: 25%;">Usuario</th>
                                    <th style="padding: 10px; color: var(--blue-bright); font-weight: 700; width: 45%;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                history.forEach(item => {
                    const dateObj = new Date(item.created_at);
                    const dateStr = String(dateObj.getDate()).padStart(2, '0') + '/' +
                                  String(dateObj.getMonth() + 1).padStart(2, '0') + '/' +
                                  dateObj.getFullYear();
                    const timeStr = String(dateObj.getHours()).padStart(2, '0') + ':' +
                                  String(dateObj.getMinutes()).padStart(2, '0');
                                  
                    const userStr = `${item.realizado_por_nombre}<br><small style="color: var(--text-muted);">${item.realizado_por_codigo}</small>`;
                    
                    tableHtml += `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 10px; font-weight: 600;">${dateStr}</td>
                            <td style="padding: 10px; color: var(--text-muted);">${timeStr}</td>
                            <td style="padding: 10px; white-space: normal;">${userStr}</td>
                            <td style="padding: 10px; white-space: normal; color: var(--text-white);">${item.descripcion}</td>
                        </tr>
                    `;
                });
                
                tableHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                timeline.innerHTML = tableHtml;
            })
            .catch(err => {
                console.error("Error al cargar historial:", err);
                timeline.innerHTML = '<em style="color: var(--priority-urgente); text-align: center; display: block; padding: 20px;">Error al cargar el historial.</em>';
            });
    }

    function closeHistoryModal() {
        document.getElementById('history-modal').classList.add('hidden');
    }

    // Modal: Request change date
    function openRequestDateChangeModal() {
        const order = allOrders.find(o => o.id === selectedOrderId);
        if (!order) return;
        
        document.getElementById('change-op-id').value = order.id;
        
        // Parse actual dates
        let dateVal = '-';
        if (order.fecha_entrega) {
            const parts = order.fecha_entrega.split('-');
            dateVal = `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        document.getElementById('change-fecha-actual').value = dateVal;
        document.getElementById('change-hora-actual').value = order.hora_entrega ? order.hora_entrega.substring(0, 5) : '-';
        
        // Clear requested fields
        document.getElementById('fecha_solicitada').value = '';
        document.getElementById('hora_solicitada').value = '';
        document.getElementById('razon_solicitud').value = '';
        
        document.getElementById('request-date-change-modal').classList.remove('hidden');
    }

    function closeRequestDateChangeModal() {
        document.getElementById('request-date-change-modal').classList.add('hidden');
    }

    function submitDateChangeRequest(event) {
        event.preventDefault();
        
        const form = document.getElementById('request-date-change-form');
        const formData = new FormData(form);
        
        fetch('{{ route("op.solicitar_cambio_fecha") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeRequestDateChangeModal();
                pollVendedorUpdates();
                hideDetail();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de red al enviar la solicitud.', 'error');
        });
    }

    // Modal: Reject request
    function openRejectRequestModal(id) {
        const modalIdInput = document.getElementById('reject-solicitud-id');
        if (modalIdInput) modalIdInput.value = id;
        
        const modalReasonInput = document.getElementById('razon_rechazo');
        if (modalReasonInput) modalReasonInput.value = '';
        
        const modal = document.getElementById('reject-request-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeRejectRequestModal() {
        const modal = document.getElementById('reject-request-modal');
        if (modal) modal.classList.add('hidden');
    }

    function submitRejectRequest(event) {
        event.preventDefault();
        const id = document.getElementById('reject-solicitud-id').value;
        const razon = document.getElementById('razon_rechazo').value;

        fetch(`/jefe/cambio-fecha/rechazar/${id}`, {
            method: 'POST',
            body: JSON.stringify({ razon_rechazo: razon }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeRejectRequestModal();
                pollVendedorUpdates();
                hideDetail();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de red al rechazar.', 'error');
        });
    }

    function approveRequest(id) {
        if (!confirm('¿Está seguro de aprobar este cambio de fecha?')) return;
        
        fetch(`/jefe/cambio-fecha/aprobar/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                pollVendedorUpdates();
                hideDetail();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de conexión al aprobar.', 'error');
        });
    }
</script>
@endsection
