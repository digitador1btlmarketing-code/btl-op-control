@extends('layouts.app')

@section('title', 'Panel Comercial Jefe de Ventas')

@section('content')
<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 800;">Panel Jefe de Ventas</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Control de solicitudes comerciales, trazabilidad y gestión de vendedores</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Export Buttons -->
        <button onclick="exportData('excel')" class="btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); font-weight: 600; cursor: pointer; border-radius: 8px;">📊 Exportar Excel</button>
        <button onclick="exportData('pdf')" class="btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); font-weight: 600; cursor: pointer; border-radius: 8px;">📄 Exportar PDF</button>

        <!-- Sound Toggle -->
        <button id="btn-sound-toggle" class="btn-view-brief" style="font-size: 0.85rem; padding: 8px 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; background: rgba(255, 255, 255, 0.05); border-color: var(--border-glass);" onclick="toggleSound()">
            <span>Activar Sonido</span> <span id="sound-icon">🔇</span>
        </button>
        <button onclick="openNewUserModal()" class="btn-primary" style="width: auto; padding: 8px 16px; font-size: 0.85rem;">
            + Nuevo Vendedor
        </button>
    </div>
</div>

<!-- KPIs Grid -->
<div class="grid-5" style="margin-bottom: 25px;">
    <div class="kpi-card">
        <div id="kpi-total" class="kpi-value">{{ $kpis['total'] }}</div>
        <div class="kpi-label">Total Órdenes</div>
    </div>
    <div class="kpi-card pending">
        <div id="kpi-pendientes" class="kpi-value" style="color: var(--state-pendiente);">{{ $kpis['pendientes'] }}</div>
        <div class="kpi-label">Pendientes</div>
    </div>
    <div class="kpi-card process">
        <div id="kpi-en-proceso" class="kpi-value" style="color: var(--state-en-proceso);">{{ $kpis['en_proceso'] }}</div>
        <div class="kpi-label">En Proceso</div>
    </div>
    <div class="kpi-card finished">
        <div id="kpi-terminadas" class="kpi-value" style="color: var(--state-terminado);">{{ $kpis['terminadas'] }}</div>
        <div class="kpi-label">Terminadas</div>
    </div>
    <div class="kpi-card urgent">
        <div id="kpi-solicitudes" class="kpi-value" style="color: var(--priority-urgente);">{{ $kpis['solicitudes_pendientes'] }}</div>
        <div class="kpi-label">Solicitudes Pendientes ⚠️</div>
    </div>
</div>

<!-- SECTION 1: SOLICITUDES DE CAMBIO DE FECHA -->
<div class="card" style="margin-bottom: 25px; border-color: rgba(0, 210, 255, 0.25);">
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
                            <tr id="req-row-{{ $req->id }}">
                                <td><strong>{{ $req->ordenProduccion->numero_op ?? 'OP' }}</strong></td>
                                <td>{{ $req->ordenProduccion->cliente ?? '-' }}<br><small style="color: var(--text-muted);">{{ $req->ordenProduccion->marca ?? '-' }}</small></td>
                                <td>{{ \Carbon\Carbon::parse($req->fecha_actual)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($req->hora_actual)->format('H:i') }}</td>
                                <td><strong style="color: var(--blue-bright);">{{ \Carbon\Carbon::parse($req->fecha_solicitada)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($req->hora_solicitada)->format('H:i') }}</strong></td>
                                <td style="max-width: 250px; white-space: normal;">{{ $req->razon_solicitud }}</td>
                                <td>{{ $req->solicitado_por_nombre }}<br><small style="color: var(--text-muted);">{{ $req->solicitado_por_codigo }}</small></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="approveRequest({{ $req->id }})" class="btn-save-inline" style="background: rgba(0, 242, 195, 0.15); border-color: rgba(0, 242, 195, 0.3); color: var(--green-lime);">
                                            Aprobar
                                        </button>
                                        <button onclick="openRejectRequestModal({{ $req->id }})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                            Rechazar
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

<!-- SECTION 2: ORDENES DE PRODUCCION GENERAL -->
<div class="card" style="margin-bottom: 25px;">
    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 20px;">Órdenes de Producción Activas</h3>
    
    <!-- Filtros y Buscador -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <!-- Category Tabs hidden for Jefe de Ventas as they focus exclusively on their vendors -->
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; max-width: 600px;">
            <input 
                type="text" 
                id="search-op" 
                class="form-control" 
                placeholder="Buscar por número de OP..." 
                style="flex: 2; min-width: 180px;"
                onkeyup="applyJefeFilters()"
            >
            
            <select id="filter-status" class="form-control" style="flex: 1; min-width: 130px;" onchange="applyJefeFilters()">
                <option value="todos">Todos los Estados</option>
                <option value="activas" selected>Todas Activas</option>
                <option value="Pendiente">Pendientes</option>
                <option value="En proceso">En proceso</option>
                <option value="Terminado">Terminadas</option>
                <option value="Cancelado">Canceladas</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div style="overflow-x: auto;">
        <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th>Ticket OP</th>
                    <th>Categoría</th>
                    <th>Cliente</th>
                    <th>Marca</th>
                    <th>Presupuestista</th>
                    <th>Creado Por</th>
                    <th>Entrega</th>
                    <th>Estado</th>
                    <th>Avance</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="jefe-table-body">
                @forelse($ordenes as $orden)
                    @php
                        $badgeCategoryClass = $orden->categoria === 'Branding' ? 'badge-normal' : 'badge-proxima';
                        $progressFillClass = $orden->estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                              ($orden->estado === 'En proceso' ? 'progress-fill-proceso' :
                                              ($orden->estado === 'Cancelado' ? 'progress-fill-cancelado' : 'progress-fill-terminado'));
                    @endphp
                    <tr class="admin-row" id="row-{{ $orden->id }}" onclick="selectOrder({{ $orden->id }})" data-id="{{ $orden->id }}" data-estado="{{ $orden->estado }}" data-prioridad="{{ $orden->prioridad }}" data-numero-op="{{ $orden->numero_op }}" data-categoria="{{ $orden->categoria }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="color: var(--text-white);">{{ $orden->numero_op }}</strong>
                                <span id="fire-container-{{ $orden->id }}" class="fire-container @if($orden->estado === 'Terminado' || $orden->estado === 'Cancelado') extinguished @elseif(!$orden->mostrar_fuego) hidden-fire @endif" title="Alerta de prioridad temporal">
                                    <span class="fire-flame">🔥</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $badgeCategoryClass }}">{{ $orden->categoria }}</span>
                        </td>
                        <td>{{ $orden->cliente }}</td>
                        <td>{{ $orden->marca }}</td>
                        <td>{{ $orden->presupuestista }}</td>
                        <td>
                            {{ $orden->creado_por_nombre ?? 'ADMINISTRADOR' }}<br>
                            <small style="color: var(--text-muted);">{{ $orden->creado_por_rol ?? 'admin' }}</small>
                        </td>
                        <td>
                            <span class="text-dash">{{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</span>
                            <br>
                            <small style="color: var(--text-muted); font-weight: 600;">
                                {{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }}
                            </small>
                        </td>
                        <td>
                            @php
                                $statusBadgeClass = 'badge-pendiente';
                                if ($orden->estado === 'En proceso') $statusBadgeClass = 'badge-proceso';
                                if ($orden->estado === 'Terminado') $statusBadgeClass = 'badge-terminado';
                                if ($orden->estado === 'Cancelado') $statusBadgeClass = 'badge-cancelado';
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
                            <button type="button" class="btn-save-inline" style="background: var(--bg-btn-view); border-color: var(--border-btn-view); color: var(--blue-bright);">
                                Ver
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

<!-- SECTION 3: GESTION DE USUARIOS DE VENTAS -->
<div class="card" style="margin-bottom: 25px;">
    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 15px;">Módulo: Usuarios de Ventas</h3>
    <div style="overflow-x: auto;">
        <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
            <thead>
                <tr>
                    <th>Código de Acceso</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
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
                        <td>{{ $user->apellido }}</td>
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
                                <button onclick="openEditUserModal({{ $user->id }}, '{{ $user->nombre }}', '{{ $user->apellido }}', {{ $user->op_count }})" class="btn-save-inline" style="padding: 4px 10px; font-size: 0.8rem;">
                                    Editar
                                </button>
                                
                                <form action="{{ route('jefe.usuarios.toggle', $user->id) }}" method="POST" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn-save-inline" style="padding: 4px 10px; font-size: 0.8rem; background: rgba(255,255,255,0.05); border-color: var(--border-glass); color: var(--text-white);">
                                        {{ $user->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                
                                @if($user->op_count === 0)
                                    <form action="{{ route('jefe.usuarios.delete', $user->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('¿Está seguro de eliminar este usuario?')">
                                        @csrf
                                        <button type="submit" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor:pointer;">
                                            Eliminar
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">No eliminable</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">No hay usuarios de ventas registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Panel de Detalle de Orden Seleccionada -->
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

    <div style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a id="btn-download-pdf-op" href="#" class="btn-secondary" style="width: auto; padding: 8px 16px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: rgba(0, 210, 255, 0.1); border-color: rgba(0, 210, 255, 0.2); color: var(--blue-bright);">
            📄 Descargar PDF OP
        </a>
    </div>

    <!-- Timeline of events (History) -->
    <div id="detail-history-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <button id="btn-show-history" onclick="openHistoryModal()" class="filter-btn" style="width: auto; padding: 8px 16px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; background: rgba(255, 255, 255, 0.05); border-color: var(--border-glass); color: var(--text-white);">
            📜 Ver historial
        </button>
    </div>
</div>

<!-- MODAL: NUEVO USUARIO DE VENTAS -->
<div id="new-user-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 450px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 20px;">
            👤 Registrar Nuevo Vendedor
        </h3>
        
        <form action="{{ route('jefe.usuarios.store') }}" method="POST">
            @csrf
            
            <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                <label for="new-user-nombre">Nombre *</label>
                <input type="text" name="nombre" id="new-user-nombre" class="form-control" required placeholder="Ej: Dafne">
            </div>
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="new-user-apellido">Apellido *</label>
                <input type="text" name="apellido" id="new-user-apellido" class="form-control" required placeholder="Ej: Ramirez">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeNewUserModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cancelar</button>
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 25px; font-weight: 700;">Crear Vendedor</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR USUARIO DE VENTAS -->
<div id="edit-user-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 450px; width: 90%; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 15px;">
            ✏️ Editar Vendedor
        </h3>
        
        <p id="edit-user-warning" style="font-size: 0.8rem; color: var(--priority-proxima); margin-bottom: 20px; line-height: 1.4; display: none;">
            ⚠️ Este vendedor ya tiene OPs creadas. El código de acceso NO cambiará para mantener la trazabilidad.
        </p>
        
        <form id="edit-user-form" method="POST">
            @csrf
            
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

<!-- MODAL: RECHAZAR SOLICITUD DE CAMBIO DE FECHA -->
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

@endsection

@section('scripts')
<script>
    let activeCategory = 'todos';
    let selectedOrderId = null;
    let allOrders = @json($ordenes);
    let pendingRequestIds = new Set();
    @foreach($solicitudes as $req)
        pendingRequestIds.add({{ $req->id }});
    @endforeach
    const storageBaseUrl = "/storage";



    // Modal: New Vendedor
    function openNewUserModal() {
        document.getElementById('new-user-modal').classList.remove('hidden');
    }
    function closeNewUserModal() {
        document.getElementById('new-user-modal').classList.add('hidden');
    }

    // Modal: Edit Vendedor
    function openEditUserModal(id, nombre, apellido, count) {
        const form = document.getElementById('edit-user-form');
        form.action = `/jefe/usuarios/update/${id}`;
        document.getElementById('edit-user-nombre').value = nombre;
        document.getElementById('edit-user-apellido').value = apellido;
        
        const warning = document.getElementById('edit-user-warning');
        if (count > 0) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }

        document.getElementById('edit-user-modal').classList.remove('hidden');
    }
    function closeEditUserModal() {
        document.getElementById('edit-user-modal').classList.add('hidden');
    }

    // Modal: Reject change date
    function openRejectRequestModal(id) {
        document.getElementById('reject-solicitud-id').value = id;
        document.getElementById('razon_rechazo').value = '';
        document.getElementById('reject-request-modal').classList.remove('hidden');
    }
    function closeRejectRequestModal() {
        document.getElementById('reject-request-modal').classList.add('hidden');
    }

    // Filter Logic
    function selectCategoryTab(category) {
        activeCategory = category;
        document.querySelectorAll('.category-tab').forEach(tab => {
            if (tab.getAttribute('data-category') === category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        applyJefeFilters();
    }

    function applyJefeFilters() {
        const searchVal = document.getElementById('search-op').value.toLowerCase().trim();
        const filterVal = document.getElementById('filter-status').value;
        const rows = document.querySelectorAll('#jefe-table-body .admin-row');
        
        let totalCount = 0;
        let pendingCount = 0;
        let processCount = 0;
        let finishedCount = 0;
        let visibleCount = 0;

        rows.forEach(row => {
            const estado = row.getAttribute('data-estado');
            const numeroOp = row.getAttribute('data-numero-op').toLowerCase();
            const categoria = row.getAttribute('data-categoria');

            let matchesCategory = true;
            if (activeCategory !== 'todos') {
                matchesCategory = (categoria === activeCategory);
            }

            let matchesFilter = false;
            if (filterVal === 'activas') {
                matchesFilter = (estado === 'Pendiente' || estado === 'En proceso');
            } else if (filterVal === 'todos') {
                matchesFilter = true;
            } else {
                matchesFilter = (estado === filterVal);
            }

            let matchesSearch = true;
            if (searchVal) {
                matchesSearch = numeroOp.includes(searchVal);
            }

            if (matchesCategory && matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
                totalCount++;
                if (estado === 'Pendiente') pendingCount++;
                if (estado === 'En proceso') processCount++;
                if (estado === 'Terminado') finishedCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Empty message row
        if (rows.length > 0) {
            if (visibleCount === 0) {
                if (!document.getElementById('no-results-row')) {
                    const tbody = document.getElementById('jefe-table-body');
                    const tr = document.createElement('tr');
                    tr.id = 'no-results-row';
                    tr.innerHTML = `<td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">No se encontraron órdenes con los filtros seleccionados.</td>`;
                    tbody.appendChild(tr);
                }
            } else {
                const noResults = document.getElementById('no-results-row');
                if (noResults) noResults.remove();
            }
        }
    }

    // Selection details
    function selectOrder(id) {
        const order = allOrders.find(o => o.id === id);
        if (!order) return;

        if (selectedOrderId === id) {
            hideDetail();
            return;
        }

        selectedOrderId = id;

        document.querySelectorAll('#jefe-table-body .admin-row').forEach(row => {
            row.classList.remove('active');
            if (parseInt(row.getAttribute('data-id')) === id) {
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
        document.querySelectorAll('#jefe-table-body .admin-row').forEach(row => {
            row.classList.remove('active');
        });
        const panel = document.getElementById('detail-panel');
        if (panel) panel.classList.add('hidden');
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
        
        const briefDiv = document.getElementById('detail-brief');
        if (order.brief) {
            briefDiv.innerHTML = `<a href="/op/descargar-brief/${order.id}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">Ver Brief</a>`;
        } else {
            briefDiv.textContent = '-';
        }

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
            
            let fmtDesinstDate = '-';
            let fmtDesinstTime = '-';
            if (order.fecha_desinstalacion) {
                const rawDesinstDate = order.fecha_desinstalacion.split('-');
                fmtDesinstDate = `${rawDesinstDate[2]}/${rawDesinstDate[1]}/${rawDesinstDate[0]}`;
            }
            if (order.hora_desinstalacion) {
                fmtDesinstTime = order.hora_desinstalacion.substring(0, 5);
            }
            document.getElementById('detail-fecha-desinst').textContent = `${fmtDesinstDate} - ${fmtDesinstTime} hrs`;
        } else {
            instSection.classList.add('hidden');
        }

        // Timeline of history loading is now handled inside the collapsible history modal
    }

    // Approve change date
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
                // Remove row locally
                const row = document.getElementById('req-row-' + id);
                if (row) row.remove();
                pollJefeUpdates();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de conexión al aprobar.', 'error');
        });
    }

    // Reject change date submit
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
                // Remove row locally
                const row = document.getElementById('req-row-' + id);
                if (row) row.remove();
                pollJefeUpdates();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de red al rechazar.', 'error');
        });
    }

    // Updates polling
    function pollJefeUpdates() {
        fetch('/op/jefe-ventas/updates', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            const newOrders = data.ordenes;
            const newRequests = data.solicitudes;
            const kpis = data.kpis;

            // Sync KPIs in DOM
            document.getElementById('kpi-total').textContent = kpis.total;
            document.getElementById('kpi-pendientes').textContent = kpis.pendientes;
            document.getElementById('kpi-en-proceso').textContent = kpis.en_proceso;
            document.getElementById('kpi-terminadas').textContent = kpis.terminadas;
            document.getElementById('kpi-solicitudes').textContent = kpis.solicitudes_pendientes;

            // Sync requests set
            newRequests.forEach(req => {
                if (!pendingRequestIds.has(req.id)) {
                    pendingRequestIds.add(req.id);
                }
            });

            if (data.recent_events) {
                processRecentEvents(data.recent_events);
            }

            // Sync table of requests
            const solContainer = document.getElementById('solicitudes-container');
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
                                        Aprobar
                                    </button>
                                    <button onclick="openRejectRequestModal(${req.id})" class="btn-logout" style="padding: 4px 10px; font-size: 0.8rem; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3);">
                                        Rechazar
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

            // Sync main OPs table
            const tbody = document.getElementById('jefe-table-body');
            const rows = tbody.querySelectorAll('.admin-row');
            rows.forEach(row => {
                const id = parseInt(row.getAttribute('data-id'));
                if (!newOrders.some(o => o.id === id)) {
                    row.remove();
                }
            });

            newOrders.forEach(orden => {
                let row = document.getElementById('row-' + orden.id);
                if (!row) {
                    row = document.createElement('tr');
                    row.className = 'admin-row';
                    row.id = 'row-' + orden.id;
                    row.onclick = () => selectOrder(orden.id);
                    tbody.appendChild(row);
                }

                row.setAttribute('data-estado', orden.estado);
                row.setAttribute('data-prioridad', orden.prioridad);
                row.setAttribute('data-numero-op', orden.numero_op);
                row.setAttribute('data-categoria', orden.categoria);

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
                const badgeCategoryClass = orden.categoria === 'Branding' ? 'badge-normal' : 'badge-proxima';
                const progressFillClass = orden.estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                          (orden.estado === 'En proceso' ? 'progress-fill-proceso' :
                                          (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' : 'progress-fill-terminado'));
                
                let statusBadgeClass = 'badge-pendiente';
                if (orden.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
                if (orden.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
                if (orden.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';

                row.innerHTML = `
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: var(--text-white);">${orden.numero_op}</strong>
                            <span id="fire-container-${orden.id}" class="fire-container ${fireClass}" title="Alerta de prioridad temporal">
                                <span class="fire-flame">🔥</span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="badge ${badgeCategoryClass}">${orden.categoria}</span>
                    </td>
                    <td>${orden.cliente}</td>
                    <td>${orden.marca}</td>
                    <td>${orden.presupuestista}</td>
                    <td>
                        ${orden.creado_por_nombre || 'ADMINISTRADOR'}<br>
                        <small style="color: var(--text-muted);">${orden.creado_por_rol || 'admin'}</small>
                    </td>
                    <td>
                        <span class="text-dash">${formattedDate}</span>
                        <br>
                        <small style="color: var(--text-muted); font-weight: 600;">
                            ${formattedTime}
                        </small>
                    </td>
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

            allOrders = newOrders;

            // Sync selected details
            if (selectedOrderId) {
                const refreshedOrder = allOrders.find(o => o.id === selectedOrderId);
                if (refreshedOrder) {
                    populateDetail(refreshedOrder);
                } else {
                    hideDetail();
                }
            }

            applyJefeFilters();
        })
        .catch(err => console.log("AJAX updates polling error:", err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyJefeFilters();
        updateSoundButtonUI();
        
        // Start polling updates every 5 seconds
        setInterval(pollJefeUpdates, 5000);
    });
    function exportData(type) {
        const searchVal = document.getElementById('search-op')?.value || '';
        const filterVal = document.getElementById('filter-status')?.value || 'activas';
        const categoryVal = activeCategory || 'todos';
        const url = `/op/exportar/${type}?search=${encodeURIComponent(searchVal)}&status=${encodeURIComponent(filterVal)}&category=${encodeURIComponent(categoryVal)}`;
        window.location.href = url;
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
</script>
@endsection
