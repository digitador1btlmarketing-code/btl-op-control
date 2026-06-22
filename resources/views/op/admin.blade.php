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
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('op.create') }}" class="btn-primary" style="width: auto; padding: 10px 20px;">+ Crear OP</a>
    </div>
</div>

<!-- KPIs Grid -->
<div class="grid-5" style="margin-bottom: 25px;">
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
                    <option value="Terminado">Terminadas</option>
                    <option value="Cancelado">Canceladas</option>
                    <option value="todos">Todas</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Category Tabs -->
    <div class="category-tabs-container">
        <button class="category-tab active" data-category="todos" onclick="selectCategoryTab('todos')">Todas</button>
        <button class="category-tab" data-category="Branding" onclick="selectCategoryTab('Branding')">Branding</button>
        <button class="category-tab" data-category="Promocional" onclick="selectCategoryTab('Promocional')">Promocional</button>
    </div>
    
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
                            <span class="badge {{ $orden->categoria === 'Branding' ? 'badge-normal' : 'badge-proxima' }}">
                                {{ $orden->categoria }}
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
                                            @else progress-fill-terminado @endif"
                                        style="width: {{ $orden->avance }}%;"
                                    ></div>
                                </div>
                                <span id="progress-text-{{ $orden->id }}" class="progress-text">
                                    {{ $orden->avance }}%
                                </span>
                            </div>
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
@endif
@endsection

@section('scripts')
<script>
    let activeCategory = 'todos';
    let selectedOrderId = null;
    let allOrders = @json($ordenes);
    const storageBaseUrl = "/storage";

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
                matchesFilter = (estado === 'Pendiente' || estado === 'En proceso');
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
        
        const briefDiv = document.getElementById('detail-brief');
        if (order.brief) {
            briefDiv.innerHTML = `<a href="${storageBaseUrl}/${order.brief}" target="_blank" class="btn-view-brief" style="background: rgba(0, 210, 255, 0.15); color: var(--blue-bright); border: 1px solid rgba(0, 210, 255, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">Ver Brief</a>`;
        } else {
            briefDiv.textContent = '-';
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
        const badgeCategoryClass = orden.categoria === 'Branding' ? 'badge-normal' : 'badge-proxima';
        
        const progressFillClass = orden.estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                  (orden.estado === 'En proceso' ? 'progress-fill-proceso' :
                                  (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' : 'progress-fill-terminado'));

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
                <span class="badge ${badgeCategoryClass}">
                    ${orden.categoria}
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
            </td>
            <td>
                <button type="submit" form="form-${orden.id}" class="btn-save-inline">
                    Guardar
                </button>
            </td>
        `;
        return tr;
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

                    // Update Leader input only if not focused
                    const inputLider = row.querySelector('input[name="lider_produccion"]');
                    if (inputLider && document.activeElement !== inputLider) {
                        inputLider.value = orden.lider_produccion || '';
                    }

                    // Update Status select only if not focused
                    const selectStatus = row.querySelector('select[name="estado"]');
                    if (selectStatus && document.activeElement !== selectStatus) {
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
                }
                // Appending an existing or new child moves it to the end of tbody
                tbody.appendChild(row);

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

            // Re-apply filters
            applyAdminFilters();
        })
        .catch(err => console.log("AJAX updates polling error:", err));
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial filters run
        applyAdminFilters();

        // Start polling updates every 5 seconds
        setInterval(pollAdminUpdates, 5000);

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
</script>
@endsection
