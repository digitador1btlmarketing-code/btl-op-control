@extends('layouts.app')

@section('title', 'Bandeja de Producción')

@section('content')
<!-- Forms container for HTML5 form association -->
<div id="forms-container">
@foreach($ordenes as $orden)
    <form id="form-{{ $orden->id }}" action="{{ route('op.update', $orden->id) }}" method="POST">
        @csrf
    </form>
@endforeach
</div>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 800;">Panel de Administración de Producción</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Digitalización y Control del flujo operativo de BTL Marketing</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="exportData('excel')" class="btn-secondary" style="width: auto; padding: 10px 20px; background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); font-weight: 600; cursor: pointer; border-radius: 8px;">📊 Exportar Excel</button>
        <button onclick="exportData('pdf')" class="btn-secondary" style="width: auto; padding: 10px 20px; background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); font-weight: 600; cursor: pointer; border-radius: 8px;">📄 Exportar PDF</button>
        <a href="{{ route('op.create') }}" class="btn-primary" style="width: auto; padding: 10px 20px;">+ Crear OP</a>
    </div>
</div>

<!-- KPIs Grid -->
<div class="grid-6" style="margin-bottom: 25px;">
    <!-- Total -->
    <div class="kpi-card">
        <div id="kpi-total" class="kpi-value">{{ $kpis['total'] }}</div>
        <div class="kpi-label">Total Órdenes</div>
    </div>
    <!-- Pendientes -->
    <div class="kpi-card pending">
        <div id="kpi-pendientes" class="kpi-value" style="color: var(--state-pendiente);">{{ $kpis['pendientes'] }}</div>
        <div class="kpi-label">Pendientes</div>
    </div>
    <!-- En Proceso -->
    <div class="kpi-card process">
        <div id="kpi-en-proceso" class="kpi-value" style="color: var(--state-en-proceso);">{{ $kpis['en_proceso'] }}</div>
        <div class="kpi-label">En Proceso</div>
    </div>
    <!-- En Espera -->
    <div class="kpi-card waiting">
        <div id="kpi-en-espera" class="kpi-value" style="color: var(--state-en-espera);">{{ $kpis['en_espera'] }}</div>
        <div class="kpi-label">En Espera</div>
    </div>
    <!-- Terminadas -->
    <div class="kpi-card finished">
        <div id="kpi-terminadas" class="kpi-value" style="color: var(--state-terminado);">{{ $kpis['terminadas'] }}</div>
        <div class="kpi-label">Terminadas</div>
    </div>
    <!-- Urgentes -->
    <div class="kpi-card urgent">
        <div id="kpi-urgentes" class="kpi-value" style="color: var(--priority-urgente);">{{ $kpis['urgentes'] }}</div>
        <div class="kpi-label">Urgentes 🔥</div>
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

<!-- SECTION: SOLICITUDES DE REPROCESO -->
<div class="card" id="solicitudes-reproceso-card" style="margin-bottom: 25px; border-color: rgba(255, 51, 102, 0.25);">
    <h3 style="font-size: 1.25rem; font-weight: 800; color: #ff3366; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        🔄 Solicitudes de Reproceso Pendientes
    </h3>
    <div id="solicitudes-reproceso-container">
        @if($solicitudesReproceso->count() === 0)
            <p id="no-solicitudes-reproceso-msg" style="color: var(--text-muted); font-size: 0.95rem; font-style: italic;">No hay solicitudes de reproceso pendientes de aprobación.</p>
        @else
            <div style="overflow-x: auto;">
                <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>OP Original</th>
                            <th>Cliente / Proyecto</th>
                            <th>Motivo / Descripción</th>
                            <th>Requerido Para</th>
                            <th>Adjunto</th>
                            <th>Solicitado Por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="solicitudes-reproceso-table-body">
                        @foreach($solicitudesReproceso as $req)
                            <tr id="req-repro-row-{{ $req->id }}">
                                <td><strong>{{ $req->ordenProduccion->numero_op ?? 'OP' }}</strong></td>
                                <td>
                                    {{ $req->ordenProduccion->cliente ?? '-' }}<br>
                                    <small style="color: var(--text-muted);">{{ $req->ordenProduccion->proyecto ?? '-' }}</small>
                                </td>
                                <td style="max-width: 250px; white-space: normal;">
                                    <strong style="color: var(--blue-bright);">{{ $req->motivo }}</strong><br>
                                    <small style="color: var(--text-white);">{{ $req->descripcion }}</small>
                                </td>
                                <td>
                                    {{ $req->fecha_requerida ? \Carbon\Carbon::parse($req->fecha_requerida)->format('d/m/Y') : 'Sin especificar' }}
                                </td>
                                <td>
                                    @if($req->archivo_adjunto)
                                        <a href="/storage/{{ $req->archivo_adjunto }}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; text-decoration: none; white-space: nowrap;">Descargar</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $req->solicitado_por_nombre }}<br><small style="color: var(--text-muted);">{{ $req->solicitado_por_codigo }}</small></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="approveReproceso({{ $req->id }})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime);">
                                            Aprobar y Crear OP
                                        </button>
                                        <button onclick="rejectReproceso({{ $req->id }})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                            Rechazar
                                        </button>
                                        <button onclick="selectOrder({{ $req->orden_produccion_id }})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                            Ver original
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
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--blue-bright); margin: 0;">
            Listado de Órdenes de Producción
        </h3>
        
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <!-- Search by OP number -->
            <div style="position: relative; min-width: 220px;">
                <input 
                    type="text" 
                    id="search-op" 
                    placeholder="Buscar por número OP..." 
                    class="form-control" 
                    style="padding: 8px 12px; font-size: 0.9rem; height: 38px; width: 100%;"
                    oninput="applyAdminFilters()"
                >
            </div>
            
            <!-- Filter by Status -->
            <div style="min-width: 180px;">
                <select 
                    id="filter-status" 
                    class="form-control" 
                    style="padding: 8px 12px; font-size: 0.9rem; height: 38px; cursor: pointer; width: 100%;"
                    onchange="applyAdminFilters()"
                >
                    <option value="activas" selected>Todas activas</option>
                    <option value="Pendiente">Pendientes</option>
                    <option value="En proceso">En proceso</option>
                    <option value="En espera">En espera</option>
                    <option value="Terminado">Terminadas</option>
                    <option value="Cancelado">Canceladas</option>
                    <option value="todos">Todas</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Category Tabs -->
    @if(in_array(session('user_role'), ['admin', 'admin_branding', 'admin_promo']))
    <div class="category-tabs-container">
        <button class="category-tab active" data-category="todos" onclick="selectCategoryTab('todos')">Todas</button>
        <button class="category-tab" data-category="Branding" onclick="selectCategoryTab('Branding')">Branding</button>
        <button class="category-tab" data-category="Promocional" onclick="selectCategoryTab('Promocional')">Promocional</button>
        <button class="category-tab" data-category="Reprocesos" onclick="selectCategoryTab('Reprocesos')">Reprocesos</button>
    </div>
    @endif
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ticket OP</th>
                    <th>Categoría</th>
                    <th>Marca</th>
                    <th>Cliente</th>
                    <th>Presupuestista</th>
                    <th>Líder Producción</th>
                    <th>Fecha Entrega</th>
                    <th>Estado</th>
                    <th>Avance</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="admin-table-body">
                @forelse($ordenes as $orden)
                    <tr class="admin-row" id="row-{{ $orden->id }}" data-id="{{ $orden->id }}" data-estado="{{ $orden->estado }}" data-prioridad="{{ $orden->prioridad }}" data-numero-op="{{ $orden->numero_op }}" data-categoria="{{ $orden->categoria }}">
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
                        <td>{{ $orden->marca }}</td>
                        <td>{{ $orden->cliente }}</td>
                        <td>{{ $orden->presupuestista }}</td>
                        <td>
                            <input 
                                type="text" 
                                name="lider_produccion" 
                                form="form-{{ $orden->id }}" 
                                value="{{ $orden->lider_produccion }}" 
                                placeholder="Asignar líder..." 
                                class="admin-input-lider"
                            >
                        </td>
                        <td>
                            <span class="text-dash">{{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</span>
                            <br>
                            <small style="color: var(--text-muted); font-weight: 600;">
                                {{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }}
                            </small>
                        </td>
                        <td>
                            <select 
                                name="estado" 
                                form="form-{{ $orden->id }}" 
                                class="admin-select select-status" 
                                data-id="{{ $orden->id }}"
                            >
                                <option value="Pendiente" {{ $orden->estado === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                                <option value="En proceso" {{ $orden->estado === 'En proceso' ? 'selected' : '' }}>En proceso</option>
                                <option value="En espera" {{ $orden->estado === 'En espera' ? 'selected' : '' }}>En espera</option>
                                <option value="Terminado" {{ $orden->estado === 'Terminado' ? 'selected' : '' }}>Terminado</option>
                                <option value="Cancelado" {{ $orden->estado === 'Cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </select>
                        </td>
                        <td>
                            <div class="progress-container" style="min-width: 100px;">
                                <div class="progress-track">
                                    <div 
                                        id="progress-fill-{{ $orden->id }}" 
                                        class="progress-fill 
                                            @if($orden->estado === 'Pendiente') progress-fill-pendiente
                                            @elseif($orden->estado === 'En proceso') progress-fill-proceso
                                            @elseif($orden->estado === 'Cancelado') progress-fill-cancelado
                                            @elseif($orden->estado === 'En espera') progress-fill-en-espera
                                            @else progress-fill-terminado @endif"
                                        style="width: {{ $orden->avance }}%;"
                                    ></div>
                                </div>
                                <span id="progress-text-{{ $orden->id }}" class="progress-text">
                                    {{ $orden->avance }}%
                                </span>
                            </div>
                            <select 
                                name="avance" 
                                form="form-{{ $orden->id }}" 
                                class="admin-select select-avance" 
                                id="select-avance-{{ $orden->id }}"
                                style="margin-top: 5px; width: 100%; display: {{ $orden->estado === 'En proceso' ? 'block' : 'none' }}; background: rgba(0, 0, 0, 0.4); color: var(--text-white); border: 1px solid var(--border-glass); border-radius: 4px; padding: 2px 4px; font-size: 0.8rem;"
                            >
                                <option value="25" {{ $orden->avance == 25 ? 'selected' : '' }}>25%</option>
                                <option value="50" {{ $orden->avance == 50 ? 'selected' : '' }}>50%</option>
                                <option value="75" {{ $orden->avance == 75 ? 'selected' : '' }}>75%</option>
                            </select>
                        </td>
                        <td>
                            <button type="submit" form="form-{{ $orden->id }}" class="btn-save-inline">
                                Guardar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr id="empty-row">
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No hay órdenes de producción registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Panel de Detalle de Orden Seleccionada -->
<div id="detail-panel" class="detail-panel hidden" style="margin-top: 25px; position: relative;">
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
    
    <!-- Date change request section in details -->
    <div id="detail-date-change-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1.05rem; color: var(--blue-bright); margin-bottom: 10px; font-weight: 700;">Solicitud de Cambio de Fecha</h4>
        <div id="detail-date-change-status" style="margin-bottom: 10px; font-size: 0.9rem; line-height: 1.4;"></div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a id="btn-download-pdf-op" href="#" target="_blank" class="btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: rgba(0, 210, 255, 0.1); border-color: rgba(0, 210, 255, 0.2); color: var(--blue-bright);">
                📄 Descargar PDF OP
            </a>
            <button id="btn-request-date-change" onclick="openRequestDateChangeModal()" class="btn-primary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; display: none;">
                Solicitar cambio de fecha
            </button>
            <button id="btn-delete-op" onclick="openDeleteOPModal()" class="btn-logout" style="width: auto; padding: 8px 16px; font-size: 0.85rem; display: none; align-items: center; gap: 5px; margin: 0; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3); color: var(--priority-urgente);">
                🗑️ Eliminar OP
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

@if(session('user_role') === 'admin')
<!-- Hidden Reset Form -->
<form id="reset-form" action="{{ route('op.reset') }}" method="POST" class="hidden">
    @csrf
    <input type="hidden" name="reset_password" id="reset-password-input">
</form>

<!-- Maintenance Options Modal -->
<div id="maintenance-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 9999; transition: var(--transition);">
    <div class="card" style="max-width: 450px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); text-align: center; padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px;">
            ⚙ Opciones de Mantenimiento
        </h3>
        
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 25px; line-height: 1.5;">
            Acceso a funciones administrativas especiales para la gestión operativa del sistema BTL Producción.
        </p>

        <button onclick="triggerResetFromMaintenance()" class="btn-logout" style="width: 100%; padding: 12px 20px; font-size: 0.95rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px;">
            🗑️ Borrar todos los reportes
        </button>
        
        <button onclick="closeMaintenanceModal()" class="filter-btn" style="width: 100%; padding: 10px 20px; font-weight: 600;">
            Cerrar
        </button>
    </div>
</div>

<!-- Delete Order Double-Confirmation Modal -->
<div id="delete-op-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 9999; transition: var(--transition);">
    <form id="delete-op-form" action="" method="POST" class="card" style="max-width: 480px; width: 90%; border-color: rgba(255, 51, 102, 0.3); box-shadow: var(--card-shadow); text-align: left; padding: 25px; margin-bottom: 0;">
        @csrf
        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--priority-urgente); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,51,102,0.15); padding-bottom: 8px; margin-top: 0;">
            ⚠️ Eliminar Orden de Producción
        </h3>
        
        <div id="delete-step-1">
            <p style="color: var(--text-white); font-size: 0.95rem; margin-bottom: 15px; line-height: 1.5;">
                ¿Está seguro de que desea eliminar la orden <strong id="delete-op-num" style="color: var(--blue-bright);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; line-height: 1.4; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 6px; border: 1px solid var(--border-glass);">
                Por defecto se realizará un <strong>Soft Delete</strong> (la orden quedará oculta pero se mantendrá en el historial interno).
            </p>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--text-white); font-size: 0.9rem; user-select: none;">
                    <input type="checkbox" id="confirm-delete-check-1" style="transform: scale(1.2); cursor: pointer;">
                    <span>Confirmo que deseo eliminar esta orden de producción</span>
                </label>
            </div>
            
            @if(session('user_role') === 'admin')
            <div style="margin-bottom: 25px; background: rgba(255, 51, 102, 0.05); border: 1px solid rgba(255, 51, 102, 0.2); padding: 12px; border-radius: 8px;" id="hard-delete-container">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--priority-urgente); font-weight: 700; font-size: 0.9rem; user-select: none;">
                    <input type="checkbox" id="hard-delete-check" name="hard_delete" value="true" style="transform: scale(1.2); cursor: pointer;">
                    <span>Eliminar permanentemente (Hard Delete)</span>
                </label>
                <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; padding-left: 24px;">
                    Esta opción eliminará la OP y todos sus registros relacionados (historial, adjuntos, solicitudes) de forma definitiva en la base de datos.
                </div>
            </div>
            @endif
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeDeleteOPModal()" class="filter-btn" style="padding: 8px 16px; font-size: 0.9rem; font-weight: 600;">
                    Cancelar
                </button>
                <button type="button" id="btn-proceed-delete-step2" onclick="proceedToDeleteStep2()" class="btn-logout" style="padding: 8px 16px; font-size: 0.9rem; font-weight: 700; margin: 0; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                    Siguiente
                </button>
            </div>
        </div>

        <div id="delete-step-2" class="hidden">
            <p style="color: var(--text-white); font-size: 0.95rem; margin-bottom: 15px; line-height: 1.5;">
                <strong>Confirmación Doble Requerida:</strong>
            </p>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 15px;">
                Para confirmar la eliminación, escriba <strong style="color: var(--priority-urgente);">ELIMINAR</strong> a continuación:
            </p>
            
            <input type="text" id="confirm-delete-text" placeholder="ELIMINAR" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255, 51, 102, 0.3); background: rgba(0, 0, 0, 0.2); color: var(--text-white); font-size: 0.95rem; font-weight: 700; text-transform: uppercase; margin-bottom: 20px; box-sizing: border-box;">
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="backToDeleteStep1()" class="filter-btn" style="padding: 8px 16px; font-size: 0.9rem; font-weight: 600;">
                    Atrás
                </button>
                <button type="submit" id="btn-confirm-delete-final" class="btn-logout" style="padding: 8px 16px; font-size: 0.9rem; font-weight: 700; margin: 0; background: var(--priority-urgente); border-color: var(--priority-urgente); color: var(--text-white);" disabled>
                    Confirmar y Eliminar
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Secure Reset Modal -->
<div id="reset-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 500px; width: 90%; border-color: rgba(255, 51, 102, 0.4); box-shadow: 0 0 30px rgba(255, 51, 102, 0.25); text-align: center; padding: 30px; margin-bottom: 0;">
        <div style="font-size: 3rem; margin-bottom: 15px;">⚠️</div>
        <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--priority-urgente); margin-bottom: 15px;">Confirmación de Seguridad</h3>
        
        <p style="color: var(--text-white); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px; font-weight: 600;">
            Esta acción eliminará todas las órdenes de producción registradas. Esta acción no se puede deshacer.
        </p>
        
        <div style="margin-bottom: 20px; text-align: left;">
            <label for="reset-password-modal-input" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">
                Contraseña de Seguridad:
            </label>
            <input 
                type="password" 
                id="reset-password-modal-input" 
                class="form-control" 
                placeholder="Ingrese la contraseña de seguridad..."
                style="border-color: rgba(255, 51, 102, 0.2);"
                onkeydown="if(event.key === 'Enter') submitResetForm()"
            >
        </div>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="closeResetModal()" class="filter-btn" style="padding: 10px 20px; min-width: 100px;">Cancelar</button>
            <button onclick="submitResetForm()" class="btn-primary" style="background: linear-gradient(135deg, var(--priority-urgente) 0%, #d90036 100%); color: white; width: auto; padding: 10px 25px; box-shadow: 0 0 15px rgba(255, 51, 102, 0.3);">Confirmar y Borrar</button>
        </div>
    </div>
</div>


<!-- SECTION: GESTION DE TODOS LOS USUARIOS (Solo para Master Admin) -->
<div class="card" style="margin-top: 25px; margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0;">Módulo: Control de Usuarios y Accesos</h3>
        <button onclick="openNewUserModal()" class="btn-primary" style="width: auto; padding: 8px 16px; font-size: 0.85rem;">+ Crear Usuario</button>
    </div>
    <div style="overflow-x: auto;">
        <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr>
                    <th>Código de Acceso</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Rol / Permisos</th>
                    <th>Estado</th>
                    <th>OP Creadas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $user)
                    <tr>
                        <td><strong style="color: var(--blue-bright);">{{ $user->codigo }}</strong></td>
                        <td>{{ $user->nombre }}</td>
                        <td>{{ $user->apellido ?: '-' }}</td>
                        <td>
                            <span class="badge 
                                @if($user->rol === 'admin') badge-urgente
                                @elseif($user->rol === 'jefe_ventas') badge-normal
                                @elseif(in_array($user->rol, ['admin_branding', 'admin_promo'])) badge-proxima
                                @else badge-normal @endif">
                                {{ strtoupper(str_replace('_', ' ', $user->rol)) }}
                            </span>
                        </td>
                        <td>
                            @if($user->activo)
                                <span class="badge badge-terminado">Activo</span>
                            @else
                                <span class="badge badge-cancelado">Inactivo</span>
                            @endif
                        </td>
                        <td><span class="badge badge-normal" style="font-weight: bold;">{{ $user->op_count }}</span></td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button onclick="openEditUserModal({{ $user->id }}, '{{ $user->nombre }}', '{{ $user->apellido ?: '' }}', '{{ $user->rol }}', {{ $user->op_count }}, '{{ $user->jefe_codigo ?: '' }}')" class="btn-save-inline" style="padding: 4px 10px; font-size: 0.8rem;">
                                    Editar
                                </button>
                                
                                <form action="{{ route('jefe.usuarios.toggle', $user->id) }}" method="POST" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn-save-inline" style="padding: 4px 10px; font-size: 0.8rem; background: rgba(255,255,255,0.05); border-color: var(--border-glass); color: var(--text-white);">
                                        {{ $user->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                
                                @if($user->op_count === 0 && $user->codigo !== 'ADMIN-PROD-2026')
                                    <form action="{{ route('jefe.usuarios.delete', $user->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('¿Está seguro de eliminar este usuario?')">
                                        @csrf
                                        <button type="submit" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor:pointer;">
                                            Eliminar
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">No de baja</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 25px;">No hay usuarios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVO USUARIO (Master Admin) -->
<div id="new-user-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 450px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 20px;">
            👤 Registrar Nuevo Usuario
        </h3>
        
        <form action="{{ route('jefe.usuarios.store') }}" method="POST">
            @csrf
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="new-user-rol">Rol / Permisos *</label>
                <select name="rol" id="new-user-rol" class="form-control" required style="cursor: pointer;">
                    <option value="ventas">Usuario de Ventas (Vendedor)</option>
                    <option value="jefe_ventas">Jefe de Ventas</option>
                    <option value="admin_branding">Administrador de Branding</option>
                    <option value="admin_promo">Administrador de Promocional</option>
                    <option value="vista">Vista (Solo Consulta)</option>
                </select>
            </div>

            <div class="form-group" id="new-user-jefe-group" style="text-align: left; margin-bottom: 15px;">
                <label for="new-user-jefe">Jefe de Ventas Responsable *</label>
                <select name="jefe_codigo" id="new-user-jefe" class="form-control" style="cursor: pointer;">
                    <option value="JEFERIZO-PROD-2026">JEFE RIZO (JEFERIZO-PROD-2026)</option>
                    <option value="JEFECAJINA-PROD-2026">JEFE CAJINA (JEFECAJINA-PROD-2026)</option>
                </select>
            </div>
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="new-user-nombre">Nombre *</label>
                <input type="text" name="nombre" id="new-user-nombre" class="form-control" required placeholder="Ej: JUAN">
            </div>
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="new-user-apellido">Apellido (opcional para admins) *</label>
                <input type="text" name="apellido" id="new-user-apellido" class="form-control" required placeholder="Ej: PEREZ">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeNewUserModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 25px; font-weight: 700;">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR USUARIO (Master Admin) -->
<div id="edit-user-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 450px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 15px;">
            ✏️ Editar Usuario
        </h3>
        
        <p id="edit-user-warning" style="font-size: 0.8rem; color: var(--priority-proxima); margin-bottom: 20px; line-height: 1.4; display: none;">
            ⚠️ Este usuario ya tiene OPs creadas. El código de acceso y su rol NO cambiarán para mantener la trazabilidad.
        </p>
        
        <form id="edit-user-form" method="POST">
            @csrf
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="edit-user-rol">Rol / Permisos *</label>
                <select name="rol" id="edit-user-rol" class="form-control" required style="cursor: pointer;">
                    <option value="ventas">Usuario de Ventas (Vendedor)</option>
                    <option value="jefe_ventas">Jefe de Ventas</option>
                    <option value="admin_branding">Administrador de Branding</option>
                    <option value="admin_promo">Administrador de Promocional</option>
                    <option value="vista">Vista (Solo Consulta)</option>
                </select>
            </div>

            <div class="form-group" id="edit-user-jefe-group" style="text-align: left; margin-bottom: 15px;">
                <label for="edit-user-jefe">Jefe de Ventas Responsable *</label>
                <select name="jefe_codigo" id="edit-user-jefe" class="form-control" style="cursor: pointer;">
                    <option value="JEFERIZO-PROD-2026">JEFE RIZO (JEFERIZO-PROD-2026)</option>
                    <option value="JEFECAJINA-PROD-2026">JEFE CAJINA (JEFECAJINA-PROD-2026)</option>
                </select>
            </div>
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="edit-user-nombre">Nombre *</label>
                <input type="text" name="nombre" id="edit-user-nombre" class="form-control" required>
            </div>
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="edit-user-apellido">Apellido *</label>
                <input type="text" name="apellido" id="edit-user-apellido" class="form-control" required>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeEditUserModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 25px; font-weight: 700;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

@endif

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

<!-- Approve Reproceso Modal -->
<div id="approve-reproceso-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 500px; width: 90%; border-color: rgba(0, 242, 195, 0.4); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--green-lime); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            ✅ Aprobar Reproceso y Crear OP
        </h3>
        
        <form id="approve-reproceso-form" onsubmit="submitApproveReproceso(event)">
            @csrf
            <input type="hidden" id="approve-reproceso-id">
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="approve-reproceso-op">Código de Reproceso (Número OP) *</label>
                <input type="text" id="approve-reproceso-op" required class="form-control" placeholder="Ej: OP-2173-R1">
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="approve-reproceso-lider">Líder de Producción</label>
                <input type="text" id="approve-reproceso-lider" class="form-control" placeholder="Nombre del líder (opcional)">
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="approve-reproceso-estado">Estado Inicial *</label>
                <select id="approve-reproceso-estado" required style="width: 100%;">
                    <option value="Pendiente" selected>Pendiente</option>
                    <option value="En proceso">En proceso</option>
                    <option value="En espera">En espera</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeApproveReprocesoModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: var(--green-lime); border-color: var(--green-lime); color: var(--bg-dark); width: auto; padding: 10px 25px; font-weight: 700;">Aprobar y Crear</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Reproceso Modal -->
<div id="reject-reproceso-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 480px; width: 90%; border-color: rgba(255, 51, 102, 0.4); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--priority-urgente); margin-top: 0; margin-bottom: 20px;">
            ❌ Rechazar Solicitud de Reproceso
        </h3>
        
        <form id="reject-reproceso-form" onsubmit="submitRejectReproceso(event)">
            @csrf
            <input type="hidden" id="reject-reproceso-id">
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="reject-reproceso-razon">Razón del Rechazo *</label>
                <textarea id="reject-reproceso-razon" class="form-control" rows="3" required placeholder="Escriba el motivo por el cual se rechaza este reproceso..." style="resize: none;"></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRejectReprocesoModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: var(--priority-urgente); border-color: var(--priority-urgente); width: auto; padding: 10px 25px; font-weight: 700;">Rechazar Solicitud</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const loggedInUserCode = "{{ session('user_code') }}";
    const loggedInUserRole = "{{ session('user_role') }}";
    let activeCategory = '{{ session('user_role') === 'admin_branding' ? 'Branding' : (session('user_role') === 'admin_promo' ? 'Promocional' : 'todos') }}';
    let selectedOrderId = null;
    let allOrders = @json($ordenes);
    const storageBaseUrl = "/storage";
    let notifiedResolutions = new Set();

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
        
        applyAdminFilters();
    }

    function applyAdminFilters() {
        const searchVal = document.getElementById('search-op').value.toLowerCase().trim();
        const filterVal = document.getElementById('filter-status').value;
        const rows = document.querySelectorAll('.admin-row');
        
        let totalCount = 0;
        let pendienteCount = 0;
        let procesoCount = 0;
        let terminadoCount = 0;
        let esperaCount = 0;
        let urgenteCount = 0;
        let visibleCount = 0;

        rows.forEach(row => {
            const estado = row.getAttribute('data-estado');
            const prioridad = row.getAttribute('data-prioridad');
            const numeroOp = row.getAttribute('data-numero-op').toLowerCase();
            const categoria = row.getAttribute('data-categoria');

            // Category filter logic
            let matchesCategory = true;
            if (activeCategory !== 'todos') {
                matchesCategory = (categoria === activeCategory);
            }

            // Status filter logic
            let matchesFilter = false;
            if (filterVal === 'activas') {
                matchesFilter = (estado === 'Pendiente' || estado === 'En proceso' || estado === 'En espera');
            } else if (filterVal === 'todos') {
                matchesFilter = true;
            } else {
                matchesFilter = (estado === filterVal);
            }

            // Search by OP number logic
            let matchesSearch = true;
            if (searchVal) {
                matchesSearch = numeroOp.includes(searchVal);
            }

            if (matchesCategory && matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
                
                // Aggregate counts for only matching rows
                totalCount++;
                if (estado === 'Pendiente') pendienteCount++;
                if (estado === 'En proceso') procesoCount++;
                if (estado === 'Terminado') terminadoCount++;
                if (estado === 'En espera') esperaCount++;
                if (prioridad === 'URGENTE') urgenteCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Toggle empty message row
        if (rows.length > 0) {
            if (visibleCount === 0) {
                if (!document.getElementById('no-results-row')) {
                    const tbody = document.getElementById('admin-table-body');
                    const tr = document.createElement('tr');
                    tr.id = 'no-results-row';
                    tr.innerHTML = `<td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">No se encontraron órdenes con los filtros seleccionados.</td>`;
                    tbody.appendChild(tr);
                }
            } else {
                const noResults = document.getElementById('no-results-row');
                if (noResults) noResults.remove();
            }
        } else {
            const noResults = document.getElementById('no-results-row');
            if (noResults) noResults.remove();
        }

        // Update top KPIs grid values
        document.getElementById('kpi-total').textContent = totalCount;
        document.getElementById('kpi-pendientes').textContent = pendienteCount;
        document.getElementById('kpi-en-proceso').textContent = procesoCount;
        document.getElementById('kpi-en-espera').textContent = esperaCount;
        document.getElementById('kpi-terminadas').textContent = terminadoCount;
        document.getElementById('kpi-urgentes').textContent = urgenteCount;
    }

    function selectOrder(id) {
        const order = allOrders.find(o => o.id === id);
        if (!order) return;

        if (selectedOrderId === id) {
            hideDetail();
            return;
        }

        selectedOrderId = id;

        // Highlight selected row
        document.querySelectorAll('.admin-row').forEach(row => {
            row.classList.remove('active');
            if (parseInt(row.getAttribute('data-id')) === id) {
                row.classList.add('active');
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
        
        document.querySelectorAll('.admin-row').forEach(row => {
            row.classList.remove('active');
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
        
        // Format delivery date
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

        // Date Change Request status
        const statusDiv = document.getElementById('detail-date-change-status');
        const btnChange = document.getElementById('btn-request-date-change');
        if (order.solicitud_pendiente) {
            const req = order.solicitud_pendiente;
            const newDate = req.fecha_solicitada.split('-').reverse().join('/');
            const newTime = req.hora_solicitada.substring(0, 5);
            
            // Authorization logic
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

            statusDiv.innerHTML = `
                <div style="background: rgba(243, 166, 59, 0.1); border: 1px solid rgba(243, 166, 59, 0.3); padding: 12px; border-radius: 8px; color: var(--state-pendiente);">
                    <strong style="display: block; margin-bottom: 5px;">⚠️ Solicitud Pendiente</strong>
                    Nueva fecha: <strong>${newDate} ${newTime} hrs</strong><br>
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
            statusDiv.innerHTML = '';
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

        // Toggle Delete OP button depending on permission
        const deleteBtn = document.getElementById('btn-delete-op');
        if (deleteBtn) {
            let canDelete = false;
            if (loggedInUserRole === 'admin') {
                canDelete = true;
            } else if (loggedInUserRole === 'admin_branding' && order.categoria === 'Branding') {
                canDelete = true;
            } else if (loggedInUserRole === 'admin_promo' && order.categoria === 'Promocional') {
                canDelete = true;
            }

            if (canDelete && (order.estado === 'Terminado' || order.estado === 'Cancelado')) {
                deleteBtn.style.display = 'inline-flex';
            } else {
                deleteBtn.style.display = 'none';
            }
        }
    }

    function approveReproceso(id) {
        const row = document.getElementById(`req-repro-row-${id}`);
        let originalOp = '';
        if (row) {
            const opTd = row.querySelector('td:first-child');
            if (opTd) originalOp = opTd.textContent.trim();
        }
        
        document.getElementById('approve-reproceso-id').value = id;
        document.getElementById('approve-reproceso-op').value = originalOp ? `${originalOp}-R1` : '';
        document.getElementById('approve-reproceso-lider').value = '';
        document.getElementById('approve-reproceso-estado').value = 'Pendiente';
        document.getElementById('approve-reproceso-modal').classList.remove('hidden');
    }

    function closeApproveReprocesoModal() {
        document.getElementById('approve-reproceso-modal').classList.add('hidden');
    }

    function submitApproveReproceso(event) {
        event.preventDefault();
        const id = document.getElementById('approve-reproceso-id').value;
        const op = document.getElementById('approve-reproceso-op').value;
        const lider = document.getElementById('approve-reproceso-lider').value;
        const estado = document.getElementById('approve-reproceso-estado').value;

        fetch(`/admin/reproceso/aprobar/${id}`, {
            method: 'POST',
            body: JSON.stringify({
                numero_op: op,
                lider_produccion: lider,
                estado: estado
            }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            closeApproveReprocesoModal();
            if (data.success) {
                showToast(data.message, 'success');
                pollAdminUpdates();
                hideDetail();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            closeApproveReprocesoModal();
            showToast('Error de red al aprobar.', 'error');
        });
    }

    function rejectReproceso(id) {
        document.getElementById('reject-reproceso-id').value = id;
        document.getElementById('reject-reproceso-razon').value = '';
        document.getElementById('reject-reproceso-modal').classList.remove('hidden');
    }

    function closeRejectReprocesoModal() {
        document.getElementById('reject-reproceso-modal').classList.add('hidden');
    }

    function submitRejectReproceso(event) {
        event.preventDefault();
        const id = document.getElementById('reject-reproceso-id').value;
        const razon = document.getElementById('reject-reproceso-razon').value;

        fetch(`/admin/reproceso/rechazar/${id}`, {
            method: 'POST',
            body: JSON.stringify({
                razon_rechazo: razon
            }),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            closeRejectReprocesoModal();
            if (data.success) {
                showToast(data.message, 'success');
                pollAdminUpdates();
                hideDetail();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            closeRejectReprocesoModal();
            showToast('Error de red al rechazar.', 'error');
        });
    }

    function createRowElement(orden) {
        const tr = document.createElement('tr');
        tr.className = 'admin-row';
        tr.id = `row-${orden.id}`;
        tr.setAttribute('data-id', orden.id);
        tr.setAttribute('data-estado', orden.estado);
        tr.setAttribute('data-prioridad', orden.prioridad);
        tr.setAttribute('data-numero-op', orden.numero_op);
        tr.setAttribute('data-categoria', orden.categoria);

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
        const badgeCategoryClass = orden.categoria === 'Branding' ? 'badge-normal' : 
                                  (orden.categoria === 'Promocional' ? 'badge-proxima' : '');
        const styleCategory = orden.categoria === 'Reprocesos' ? 'background: rgba(255, 95, 56, 0.15); color: #ff5f38; border: 1px solid rgba(255, 95, 56, 0.3);' : '';
        const catText = orden.categoria === 'Reprocesos' ? 'REPROCESO' : orden.categoria;
        
        const progressFillClass = orden.estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                  (orden.estado === 'En proceso' ? 'progress-fill-proceso' :
                                  (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' :
                                  (orden.estado === 'En espera' ? 'progress-fill-en-espera' : 'progress-fill-terminado')));

        tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <strong style="color: var(--text-white);">${orden.numero_op}</strong>
                    <span id="fire-container-${orden.id}" class="fire-container ${fireClass}" title="Alerta de prioridad temporal">
                        <span class="fire-flame">🔥</span>
                    </span>
                </div>
            </td>
            <td>
                <span class="badge ${badgeCategoryClass}" style="${styleCategory}">
                    ${catText}
                </span>
            </td>
            <td>${orden.marca}</td>
            <td>${orden.cliente}</td>
            <td>${orden.presupuestista}</td>
            <td>
                <input 
                    type="text" 
                    name="lider_produccion" 
                    form="form-${orden.id}" 
                    value="${orden.lider_produccion || ''}" 
                    placeholder="Asignar líder..." 
                    class="admin-input-lider"
                >
            </td>
            <td>
                <span class="text-dash">${formattedDate}</span>
                <br>
                <small style="color: var(--text-muted); font-weight: 600;">
                    ${formattedTime}
                </small>
            </td>
            <td>
                <select 
                    name="estado" 
                    form="form-${orden.id}" 
                    class="admin-select select-status" 
                    data-id="${orden.id}"
                >
                    <option value="Pendiente" ${orden.estado === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                    <option value="En proceso" ${orden.estado === 'En proceso' ? 'selected' : ''}>En proceso</option>
                    <option value="En espera" ${orden.estado === 'En espera' ? 'selected' : ''}>En espera</option>
                    <option value="Terminado" ${orden.estado === 'Terminado' ? 'selected' : ''}>Terminado</option>
                    <option value="Cancelado" ${orden.estado === 'Cancelado' ? 'selected' : ''}>Cancelado</option>
                </select>
            </td>
            <td>
                <div class="progress-container" style="min-width: 100px;">
                    <div class="progress-track">
                        <div 
                            id="progress-fill-${orden.id}" 
                            class="progress-fill ${progressFillClass}"
                            style="width: ${orden.avance}%;"
                        ></div>
                    </div>
                    <span id="progress-text-${orden.id}" class="progress-text">
                        ${orden.avance}%
                    </span>
                </div>
                <select 
                    name="avance" 
                    form="form-${orden.id}" 
                    class="admin-select select-avance" 
                    id="select-avance-${orden.id}"
                    style="margin-top: 5px; width: 100%; display: ${orden.estado === 'En proceso' ? 'block' : 'none'}; background: rgba(0, 0, 0, 0.4); color: var(--text-white); border: 1px solid var(--border-glass); border-radius: 4px; padding: 2px 4px; font-size: 0.8rem;"
                >
                    <option value="25" ${orden.avance == 25 ? 'selected' : ''}>25%</option>
                    <option value="50" ${orden.avance == 50 ? 'selected' : ''}>50%</option>
                    <option value="75" ${orden.avance == 75 ? 'selected' : ''}>75%</option>
                </select>
            </td>
            <td>
                <button type="submit" form="form-${orden.id}" class="btn-save-inline">
                    Guardar
                </button>
            </td>
        `;
        return tr;
    }

    let pollingTimer = null;
    let resumeTimer = null;
    let isUserInteracting = false;
    const POLLING_INTERVAL_MS = 15000;

    function startPolling() {
        stopPolling();
        if (!isUserInteracting) {
            pollingTimer = setTimeout(() => {
                pollAdminUpdates();
                startPolling();
            }, POLLING_INTERVAL_MS);
        }
    }

    function stopPolling() {
        if (pollingTimer) {
            clearTimeout(pollingTimer);
            pollingTimer = null;
        }
    }

    function handleUserInteraction() {
        isUserInteracting = true;
        stopPolling();
        
        if (resumeTimer) {
            clearTimeout(resumeTimer);
        }
        
        resumeTimer = setTimeout(() => {
            isUserInteracting = false;
            startPolling();
        }, 10000); // Resume polling 10 seconds after last interaction
    }

    function pollAdminUpdates() {
        fetch('/op/admin/updates', {
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
            const tbody = document.getElementById('admin-table-body');
            const formsContainer = document.getElementById('forms-container');
            const token = document.querySelector('input[name="_token"]')?.value;

            // Remove deleted rows
            const existingRows = document.querySelectorAll('.admin-row');
            existingRows.forEach(row => {
                const rowId = parseInt(row.getAttribute('data-id'));
                if (!newOrders.some(o => o.id === rowId)) {
                    row.remove();
                    document.getElementById('form-' + rowId)?.remove();
                }
            });

            // Toggle empty state
            let emptyRow = document.getElementById('empty-row');
            if (newOrders.length === 0) {
                if (!emptyRow) {
                    tbody.innerHTML = `
                        <tr id="empty-row">
                            <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                No hay órdenes de producción registradas.
                            </td>
                        </tr>
                    `;
                }
            } else {
                if (emptyRow) emptyRow.remove();
            }

            // Update or add rows
            newOrders.forEach(orden => {
                let row = document.getElementById('row-' + orden.id);
                if (!row) {
                    // Create new row
                    row = createRowElement(orden);
                    
                    // Create corresponding form in forms-container
                    const form = document.createElement('form');
                    form.id = `form-${orden.id}`;
                    form.action = `/op/admin/update/${orden.id}`;
                    form.method = 'POST';
                    form.innerHTML = `<input type="hidden" name="_token" value="${token || ''}">`;
                    formsContainer.appendChild(form);
                } else {
                    // Skip updating if user has focus inside this row
                    if (row.contains(document.activeElement)) {
                        return;
                    }

                    // Update existing row attributes
                    row.setAttribute('data-estado', orden.estado);
                    row.setAttribute('data-prioridad', orden.prioridad);
                    row.setAttribute('data-categoria', orden.categoria);

                    // Update fire container class
                    const fireContainer = document.getElementById('fire-container-' + orden.id);
                    if (fireContainer) {
                        if (orden.estado === 'Terminado' || orden.estado === 'Cancelado') {
                            fireContainer.className = 'fire-container extinguished';
                        } else {
                            if (orden.mostrar_fuego) {
                                fireContainer.className = 'fire-container';
                            } else {
                                fireContainer.className = 'fire-container hidden-fire';
                            }
                        }
                    }

                    // Update Leader input
                    const inputLider = row.querySelector('input[name="lider_produccion"]');
                    if (inputLider) {
                        inputLider.value = orden.lider_produccion || '';
                    }

                    // Update Status select
                    const selectStatus = row.querySelector('select[name="estado"]');
                    if (selectStatus) {
                        selectStatus.value = orden.estado;
                    }

                    // Update progress bar
                    const progressFill = document.getElementById('progress-fill-' + orden.id);
                    const progressText = document.getElementById('progress-text-' + orden.id);
                    if (progressFill && progressText) {
                        progressText.textContent = orden.avance + '%';
                        progressFill.className = 'progress-fill';
                        let stateClass = 'progress-fill-pendiente';
                        if (orden.estado === 'En proceso') {
                            stateClass = 'progress-fill-proceso';
                        } else if (orden.estado === 'Terminado') {
                            stateClass = 'progress-fill-terminado';
                        } else if (orden.estado === 'Cancelado') {
                            stateClass = 'progress-fill-cancelado';
                        }
                        progressFill.classList.add(stateClass);
                        progressFill.style.width = orden.avance + '%';
                    }

                    const selectAvance = document.getElementById('select-avance-' + orden.id);
                    if (selectAvance) {
                        selectAvance.value = orden.avance;
                        if (orden.estado === 'En proceso') {
                            selectAvance.style.display = 'block';
                        } else {
                            selectAvance.style.display = 'none';
                        }
                    }
                }
                
                // Append or re-append to keep sorted order, but ONLY if it doesn't have focus
                if (!row.contains(document.activeElement)) {
                    tbody.appendChild(row);
                }

                // Keep selected/active class state
                if (selectedOrderId && orden.id === selectedOrderId) {
                    row.classList.add('active');
                } else {
                    row.classList.remove('active');
                }
            });

            // Update local allOrders
            allOrders = newOrders;

            // Sync detail panel
            if (selectedOrderId) {
                const refreshedOrder = allOrders.find(o => o.id === selectedOrderId);
                if (refreshedOrder) {
                    populateDetail(refreshedOrder);
                } else {
                    hideDetail();
                }
            }

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
                                <td><strong>${req.orden_produccion ? req.orden_produccion.numero_op : 'OP'}</strong></td>
                                <td>${req.orden_produccion ? req.orden_produccion.cliente : '-'}<br><small style="color: var(--text-muted);">${req.orden_produccion ? req.orden_produccion.marca : '-'}</small></td>
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

            // Sync solicitudes reproceso container
            if (data.solicitudes_reproceso) {
                const reproContainer = document.getElementById('solicitudes-reproceso-container');
                const newRepRequests = data.solicitudes_reproceso;
                if (newRepRequests.length === 0) {
                    reproContainer.innerHTML = `<p id="no-solicitudes-reproceso-msg" style="color: var(--text-muted); font-size: 0.95rem; font-style: italic;">No hay solicitudes de reproceso pendientes de aprobación.</p>`;
                } else {
                    let tableHtml = `
                        <div style="overflow-x: auto;">
                            <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th>OP Original</th>
                                        <th>Cliente / Proyecto</th>
                                        <th>Motivo / Descripción</th>
                                        <th>Requerido Para</th>
                                        <th>Adjunto</th>
                                        <th>Solicitado Por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="solicitudes-reproceso-table-body">
                    `;

                    newRepRequests.forEach(req => {
                        const opNumber = req.orden_produccion ? req.orden_produccion.numero_op : 'OP';
                        const opCliente = req.orden_produccion ? req.orden_produccion.cliente : '-';
                        const opProyecto = req.orden_produccion ? req.orden_produccion.proyecto : '-';
                        const reqDate = req.fecha_requerida ? new Date(req.fecha_requerida).toLocaleDateString('es-NI') : 'Sin especificar';
                        const fileHtml = req.archivo_adjunto ? 
                            `<a href="/storage/${req.archivo_adjunto}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; text-decoration: none; white-space: nowrap;">Descargar</a>` : '-';
                        
                        tableHtml += `
                            <tr id="req-repro-row-${req.id}">
                                <td><strong>${opNumber}</strong></td>
                                <td>
                                    ${opCliente}<br>
                                    <small style="color: var(--text-muted);">${opProyecto}</small>
                                </td>
                                <td style="max-width: 250px; white-space: normal;">
                                    <strong style="color: var(--blue-bright);">${req.motivo}</strong><br>
                                    <small style="color: var(--text-white);">${req.descripcion}</small>
                                </td>
                                <td>${reqDate}</td>
                                <td>${fileHtml}</td>
                                <td>${req.solicitado_por_nombre}<br><small style="color: var(--text-muted);">${req.solicitado_por_codigo}</small></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="approveReproceso(${req.id})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime);">
                                            Aprobar y Crear OP
                                        </button>
                                        <button onclick="rejectReproceso(${req.id})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                            Rechazar
                                        </button>
                                        <button onclick="selectOrder(${req.orden_produccion_id})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                            Ver original
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
                    reproContainer.innerHTML = tableHtml;
                }
            }

            // Re-apply filters
            applyAdminFilters();

            // Process recent resolutions to show toasts
            if (data.recent_resolutions && data.recent_resolutions.length > 0) {
                data.recent_resolutions.forEach(res => {
                    if (!notifiedResolutions.has(res.id)) {
                        notifiedResolutions.add(res.id);
                        
                        let message = '';
                        if (res.estado_solicitud === 'Aprobada') {
                            message = `Aprobado cambio de fecha para ${res.numero_op} a ${res.fecha_solicitada} ${res.hora_solicitada}.`;
                            showToast(message, 'success');
                        } else if (res.estado_solicitud === 'Rechazada') {
                            message = `Rechazado cambio de fecha para ${res.numero_op}. Razón: ${res.razon_rechazo}`;
                            showToast(message, 'error');
                        }
                    }
                });
            }

            if (data.recent_events) {
                processRecentEvents(data.recent_events);
            }
        })
        .catch(err => console.log("AJAX updates polling error:", err));
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial filters run
        applyAdminFilters();

        // Start polling updates
        startPolling();

        // Event delegation for table row clicks to show details
        const tableBody = document.getElementById('admin-table-body');
        if (tableBody) {
            tableBody.addEventListener('click', function(e) {
                const row = e.target.closest('.admin-row');
                if (!row) return;
                
                // Do not toggle detail panel if clicked on interactive elements (inputs, selects, buttons, links)
                if (e.target.closest('input, select, button, a')) {
                    return;
                }
                
                const id = parseInt(row.getAttribute('data-id'));
                selectOrder(id);
            });

            // Listen to writing, selecting, keypress and focus inside the table to pause polling
            tableBody.addEventListener('input', handleUserInteraction);
            tableBody.addEventListener('change', handleUserInteraction);
            tableBody.addEventListener('keydown', handleUserInteraction);
            tableBody.addEventListener('focusin', handleUserInteraction);
        }

        // Also pause polling when using search or status filters
        const searchInput = document.getElementById('search-op');
        const filterStatus = document.getElementById('filter-status');
        if (searchInput) {
            searchInput.addEventListener('input', handleUserInteraction);
            searchInput.addEventListener('focusin', handleUserInteraction);
        }
        if (filterStatus) {
            filterStatus.addEventListener('change', handleUserInteraction);
            filterStatus.addEventListener('focusin', handleUserInteraction);
        }

        // Event delegation for submit on forms inside forms-container
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form && form.id && form.id.startsWith('form-')) {
                e.preventDefault();
                
                const formId = form.id;
                const ordenId = formId.replace('form-', '');
                const rowElement = document.getElementById('row-' + ordenId);
                if (!rowElement) return;

                const formData = new FormData(form);
                const externalInputs = document.querySelectorAll(`[form="${formId}"]`);
                externalInputs.forEach(input => {
                    formData.set(input.name, input.value);
                });

                const actionUrl = form.getAttribute('action');
                
                fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Error al actualizar');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Orden actualizada correctamente.', 'success');
                        
                        const progressFill = document.getElementById(`progress-fill-${ordenId}`);
                        const progressText = document.getElementById(`progress-text-${ordenId}`);
                        
                        if (progressFill && progressText) {
                            progressText.textContent = data.avance + '%';
                            progressFill.className = 'progress-fill';
                            let stateClass = 'progress-fill-pendiente';
                            if (data.estado === 'En proceso') stateClass = 'progress-fill-proceso';
                            else if (data.estado === 'Terminado') stateClass = 'progress-fill-terminado';
                            else if (data.estado === 'Cancelado') stateClass = 'progress-fill-cancelado';
                            progressFill.classList.add(stateClass);
                            progressFill.style.width = data.avance + '%';
                        }

                        rowElement.setAttribute('data-estado', data.estado);
                        rowElement.setAttribute('data-prioridad', data.prioridad);

                        const fireContainer = document.getElementById(`fire-container-${ordenId}`);
                        if (fireContainer) {
                            if (data.estado === 'Terminado' || data.estado === 'Cancelado') {
                                fireContainer.className = 'fire-container extinguished';
                            } else {
                                if (data.mostrar_fuego) {
                                    fireContainer.className = 'fire-container';
                                } else {
                                    fireContainer.className = 'fire-container hidden-fire';
                                }
                            }
                        }

                        // Update local allOrders state
                        const localOrderId = parseInt(ordenId);
                        const localOrder = allOrders.find(o => o.id === localOrderId);
                        if (localOrder) {
                            localOrder.estado = data.estado;
                            localOrder.avance = data.avance;
                            localOrder.prioridad = data.prioridad;
                            const inputLider = rowElement.querySelector('input[name="lider_produccion"]');
                            if (inputLider) {
                                localOrder.lider_produccion = inputLider.value;
                            }
                            // Re-populate details if it's the selected one
                            if (selectedOrderId === localOrderId) {
                                populateDetail(localOrder);
                            }
                        }

                        // Pause polling for 10 seconds post-save
                        handleUserInteraction();

                        applyAdminFilters();
                    } else {
                        showToast('Ocurrió un error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error(error);
                    showToast('Error de conexión al servidor.', 'error');
                });
            }
        });
    });

    function openResetModal() {
        const modal = document.getElementById('reset-modal');
        if (modal) {
            modal.classList.remove('hidden');
            const input = document.getElementById('reset-password-modal-input');
            if (input) {
                input.value = '';
                setTimeout(() => input.focus(), 100);
            }
        }
    }

    function closeResetModal() {
        const modal = document.getElementById('reset-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function submitResetForm() {
        const input = document.getElementById('reset-password-modal-input');
        if (!input) return;
        const password = input.value;
        if (!password.trim()) {
            showToast("Por favor, ingrese la contraseña de seguridad.", "error");
            return;
        }
        
        const formInput = document.getElementById('reset-password-input');
        const form = document.getElementById('reset-form');
        if (formInput && form) {
            formInput.value = password;
            form.submit();
        }
    }

    function openMaintenanceModal() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeMaintenanceModal() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function triggerResetFromMaintenance() {
        closeMaintenanceModal();
        openResetModal();
    }

    function openRequestDateChangeModal() {
        if (!selectedOrderId) return;
        const order = allOrders.find(o => o.id === selectedOrderId);
        if (!order) return;

        const opIdInput = document.getElementById('change-op-id');
        if (opIdInput) opIdInput.value = order.id;
        
        let formattedDate = '-';
        let formattedTime = '-';
        if (order.fecha_entrega) {
            const rawDate = order.fecha_entrega.split('-');
            formattedDate = `${rawDate[2]}/${rawDate[1]}/${rawDate[0]}`;
        }
        if (order.hora_entrega) {
            formattedTime = order.hora_entrega.substring(0, 5);
        }
        
        const fechaActInput = document.getElementById('change-fecha-actual');
        if (fechaActInput) fechaActInput.value = formattedDate;
        
        const horaActInput = document.getElementById('change-hora-actual');
        if (horaActInput) horaActInput.value = formattedTime;
        
        const fechaSolInput = document.getElementById('fecha_solicitada');
        if (fechaSolInput) fechaSolInput.value = order.fecha_entrega;
        
        const horaSolInput = document.getElementById('hora_solicitada');
        if (horaSolInput) horaSolInput.value = order.hora_entrega.substring(0, 5);
        
        const razonInput = document.getElementById('razon_solicitud');
        if (razonInput) razonInput.value = '';

        const modal = document.getElementById('request-date-change-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeRequestDateChangeModal() {
        const modal = document.getElementById('request-date-change-modal');
        if (modal) modal.classList.add('hidden');
    }

    function submitDateChangeRequest(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        fetch('{{ route("op.solicitar_cambio_fecha") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(data => { throw new Error(data.message || 'Error en servidor') });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeRequestDateChangeModal();
                pollAdminUpdates(); // Fetch and re-render updates
            } else {
                showToast(data.message || 'Error al enviar la solicitud', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast(err.message || 'Error al procesar la solicitud.', 'error');
        });
    }
    function exportData(type) {
        const searchVal = document.getElementById('search-op')?.value || '';
        const filterVal = document.getElementById('filter-status')?.value || 'activas';
        const categoryVal = activeCategory || 'todos';
        const url = `/op/exportar/${type}?search=${encodeURIComponent(searchVal)}&status=${encodeURIComponent(filterVal)}&category=${encodeURIComponent(categoryVal)}`;
        if (type === 'pdf') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }

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

    // Modals: Master Admin user management panel functions
    function openNewUserModal() {
        const modal = document.getElementById('new-user-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeNewUserModal() {
        const modal = document.getElementById('new-user-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openEditUserModal(id, nombre, apellido, rol, count, jefeCodigo) {
        const form = document.getElementById('edit-user-form');
        if (!form) return;
        
        form.action = `/jefe/usuarios/update/${id}`;
        document.getElementById('edit-user-nombre').value = nombre;
        document.getElementById('edit-user-apellido').value = apellido;
        
        const selectRol = document.getElementById('edit-user-rol');
        if (selectRol) {
            selectRol.value = rol;
            // Disable role change if the user has OPs to maintain clean access records
            if (count > 0 || rol === 'admin') {
                selectRol.disabled = true;
            } else {
                selectRol.disabled = false;
            }
        }

        const selectJefe = document.getElementById('edit-user-jefe');
        if (selectJefe) {
            selectJefe.value = jefeCodigo || 'JEFERIZO-PROD-2026';
        }

        const editUserRol = document.getElementById('edit-user-rol');
        const editUserJefeGroup = document.getElementById('edit-user-jefe-group');
        if (editUserRol && editUserJefeGroup) {
            if (editUserRol.value === 'ventas') {
                editUserJefeGroup.style.display = 'block';
                if (selectJefe) selectJefe.required = true;
            } else {
                editUserJefeGroup.style.display = 'none';
                if (selectJefe) selectJefe.required = false;
            }
        }
        
        const warning = document.getElementById('edit-user-warning');
        if (warning) {
            warning.style.display = count > 0 ? 'block' : 'none';
        }

        const modal = document.getElementById('edit-user-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeEditUserModal() {
        const modal = document.getElementById('edit-user-modal');
        if (modal) modal.classList.add('hidden');
    }

    // Approval / Rejection JS handlers
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
                pollAdminUpdates();
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
                pollAdminUpdates();
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

    function openDeleteOPModal() {
        if (!selectedOrder) return;
        
        document.getElementById('delete-op-num').textContent = selectedOrder.numero_op;
        document.getElementById('delete-op-form').action = `/op/eliminar/${selectedOrder.id}`;
        
        // Reset steps
        document.getElementById('delete-step-1').classList.remove('hidden');
        document.getElementById('delete-step-2').classList.add('hidden');
        
        // Reset checkbox & text inputs
        document.getElementById('confirm-delete-check-1').checked = false;
        const hardCheck = document.getElementById('hard-delete-check');
        if (hardCheck) hardCheck.checked = false;
        
        const confirmText = document.getElementById('confirm-delete-text');
        confirmText.value = '';
        document.getElementById('btn-confirm-delete-final').disabled = true;
        
        document.getElementById('delete-op-modal').classList.remove('hidden');
    }

    function closeDeleteOPModal() {
        document.getElementById('delete-op-modal').classList.add('hidden');
    }

    function proceedToDeleteStep2() {
        const isChecked = document.getElementById('confirm-delete-check-1').checked;
        if (!isChecked) {
            alert('Por favor, confirme que desea eliminar la orden marcando la casilla.');
            return;
        }
        
        document.getElementById('delete-step-1').classList.add('hidden');
        document.getElementById('delete-step-2').classList.remove('hidden');
        document.getElementById('confirm-delete-text').focus();
    }

    function backToDeleteStep1() {
        document.getElementById('delete-step-2').classList.add('hidden');
        document.getElementById('delete-step-1').classList.remove('hidden');
    }

    // Dynamic role listener to show/hide Chief selector in creation/edit forms
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for change in status select dropdowns to toggle the visibility of the corresponding progress dropdown
        document.addEventListener('change', function(event) {
            if (event.target && event.target.classList.contains('select-status')) {
                const orderId = event.target.getAttribute('data-id');
                const selectAvance = document.getElementById('select-avance-' + orderId);
                if (selectAvance) {
                    if (event.target.value === 'En proceso') {
                        selectAvance.style.display = 'block';
                    } else {
                        selectAvance.style.display = 'none';
                    }
                }
            }
        });

        const newUserRol = document.getElementById('new-user-rol');
        const newUserJefeGroup = document.getElementById('new-user-jefe-group');
        const newUserJefe = document.getElementById('new-user-jefe');
        if (newUserRol && newUserJefeGroup) {
            const toggleNewJefeGroup = () => {
                if (newUserRol.value === 'ventas') {
                    newUserJefeGroup.style.display = 'block';
                    if (newUserJefe) newUserJefe.required = true;
                } else {
                    newUserJefeGroup.style.display = 'none';
                    if (newUserJefe) newUserJefe.required = false;
                }
            };
            newUserRol.addEventListener('change', toggleNewJefeGroup);
            toggleNewJefeGroup();
        }

        const editUserRol = document.getElementById('edit-user-rol');
        const editUserJefeGroup = document.getElementById('edit-user-jefe-group');
        const editUserJefe = document.getElementById('edit-user-jefe');
        if (editUserRol && editUserJefeGroup) {
            const toggleEditJefeGroup = () => {
                if (editUserRol.value === 'ventas') {
                    editUserJefeGroup.style.display = 'block';
                    if (editUserJefe) editUserJefe.required = true;
                } else {
                    editUserJefeGroup.style.display = 'none';
                    if (editUserJefe) editUserJefe.required = false;
                }
            };
            editUserRol.addEventListener('change', toggleEditJefeGroup);
        }

        const confirmText = document.getElementById('confirm-delete-text');
        if (confirmText) {
            confirmText.addEventListener('input', function(e) {
                const val = e.target.value.trim().toUpperCase();
                document.getElementById('btn-confirm-delete-final').disabled = (val !== 'ELIMINAR');
            });
        }
    });
</script>
@endsection
