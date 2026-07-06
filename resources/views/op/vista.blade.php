@extends('layouts.app')

@section('title', 'Panel de Consulta - Vista General')

@section('content')
<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 800;">Panel de Consulta | General</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Visualización y seguimiento de órdenes en tiempo real (Modo Consulta)</p>
    </div>
</div>

<!-- KPIs Grid -->
<div class="grid-5" style="margin-bottom: 25px;">
    <div class="kpi-card">
        <div id="kpi-total" class="kpi-value">{{ $ordenes->count() }}</div>
        <div class="kpi-label">Total Órdenes</div>
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

<div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 25px; border-color: var(--border-glass);">
    <style>
        .category-tabs-container {
            display: flex;
            gap: 5px;
            border-bottom: 1px solid var(--border-glass);
            margin-bottom: 20px;
            padding-bottom: 0;
        }
        .category-tab {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
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
            Listado General de Órdenes
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
                    oninput="applyVistaFilters()"
                >
            </div>
            
            <!-- Filter by Status -->
            <div style="min-width: 180px;">
                <select 
                    id="filter-status" 
                    class="form-control" 
                    style="padding: 8px 12px; font-size: 0.9rem; height: 38px; cursor: pointer; width: 100%;"
                    onchange="applyVistaFilters()"
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
    <div class="category-tabs-container">
        <button class="category-tab active" data-category="todos" onclick="selectCategoryTab('todos')">Todas</button>
        <button class="category-tab" data-category="Branding" onclick="selectCategoryTab('Branding')">Branding</button>
        <button class="category-tab" data-category="Promocional" onclick="selectCategoryTab('Promocional')">Promocional</button>
        <button class="category-tab" data-category="Reprocesos" onclick="selectCategoryTab('Reprocesos')">Reprocesos</button>
    </div>
    
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th>Ticket OP</th>
                    <th>Categoría</th>
                    <th>Marca</th>
                    <th>Cliente</th>
                    <th>Presupuestista</th>
                    <th>Líder</th>
                    <th>Fecha Entrega</th>
                    <th>Estado</th>
                    <th>Avance</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody id="vista-table-body">
                @forelse($ordenes as $orden)
                    @php
                        $badgeCategoryClass = $orden->categoria === 'Branding' ? 'badge-normal' : ($orden->categoria === 'Promocional' ? 'badge-proxima' : 'badge-urgente');
                        $progressFillClass = $orden->estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                              ($orden->estado === 'En proceso' ? 'progress-fill-proceso' :
                                              ($orden->estado === 'Cancelado' ? 'progress-fill-cancelado' :
                                              ($orden->estado === 'En espera' ? 'progress-fill-en-espera' : 'progress-fill-terminado')));
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
                        <td>{{ $orden->marca }}</td>
                        <td>{{ $orden->cliente }}</td>
                        <td>{{ $orden->presupuestista }}</td>
                        <td>
                            @if($orden->lider_produccion)
                                <span style="color: var(--text-white); font-weight: 500;">{{ $orden->lider_produccion }}</span>
                            @else
                                <em style="color: var(--text-muted); font-size: 0.9em;">Sin asignar</em>
                            @endif
                        </td>
                        <td>
                            <span style="color: var(--text-white); font-weight: 500;">{{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }}</span>
                            <br>
                            <small style="color: var(--text-muted);">{{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }}</small>
                        </td>
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
                        <td style="text-align: center;" onclick="event.stopPropagation();">
                            <button onclick="selectOrder({{ $orden->id }})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                Ver detalle
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 1.1rem;">
                            No hay órdenes de producción registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Bottom Grid: Detalle OP de Solo Consulta -->
<div id="detail-panel" class="detail-panel hidden" style="margin-top: 25px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
        <h3 style="font-size: 1.35rem; font-weight: 800; margin: 0;">
            📋 Detalle OP: <span id="detail-op-title" style="color: var(--blue-bright);"></span>
        </h3>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a id="btn-download-pdf-op" href="#" target="_blank" class="btn-view-brief" style="background: rgba(0, 242, 195, 0.15); color: var(--green-lime); border-color: rgba(0, 242, 195, 0.3); padding: 6px 12px; font-size: 0.85rem; text-decoration: none; border-radius: 6px; white-space: nowrap;">
                🖨 Exportar Ficha PDF
            </a>
            <button onclick="openHistoryModal()" class="btn-view-brief" style="padding: 6px 12px; font-size: 0.85rem; border-radius: 6px; cursor: pointer; background: rgba(255, 255, 255, 0.05); border-color: var(--border-glass); white-space: nowrap;">
                ⏳ Ver Historial OP
            </button>
            <button onclick="hideDetail()" class="btn-logout" style="font-size: 0.85rem; padding: 6px 12px; cursor: pointer; background: rgba(255, 51, 102, 0.15); border-color: rgba(255, 51, 102, 0.3); color: #ff3366; border-radius: 6px; white-space: nowrap; margin-bottom: 0; display: inline-block;">
                ❌ Ocultar detalle
            </button>
        </div>
    </div>
    
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
            <div class="detail-label">Planos / Archivos Adjuntos</div>
            <div id="detail-brief" class="detail-val brief-container">-</div>
        </div>
    </div>

    <!-- Conditional Installation fields -->
    <div id="detail-installation-section" class="hidden" style="margin-top: 20px; border-top: 1px solid var(--border-glass); padding-top: 20px;">
        <h4 style="font-size: 1.05rem; color: var(--priority-proxima); margin-bottom: 15px; font-weight: 700;">Detalles de Montaje e Instalación</h4>
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

    <!-- Reproceso request block and history -->
    <div id="reproceso-action-container" style="margin-top: 15px;"></div>

    <div id="detail-reprocesos-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1.05rem; color: var(--priority-proxima); margin-bottom: 10px; font-weight: 700;">🔄 Historial de Reprocesos</h4>
        <div id="detail-reprocesos-content">-</div>
    </div>
</div>

<!-- MODAL: HISTORIAL DE CAMBIOS -->
<div id="history-modal" class="custom-modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; transition: var(--transition);">
    <div class="card" style="max-width: 650px; width: 90%; max-height: 80vh; overflow-y: auto; border-color: var(--border-glass); box-shadow: var(--card-shadow); padding: 30px; margin-bottom: 0;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--blue-bright); margin-bottom: 20px; border-bottom: 1px solid var(--border-glass); padding-bottom: 10px;">
            ⏳ Historial de Cambios de la OP
        </h3>
        
        <div id="history-timeline" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; text-align: left;">
            <!-- Timeline items loaded dynamically via JS -->
        </div>
        
        <div style="display: flex; justify-content: flex-end;">
            <button onclick="closeHistoryModal()" class="filter-btn" style="padding: 10px 20px; width: auto; font-weight: 600;">Cerrar Historial</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    let currentOrders = @json($ordenes);
    const database = [...currentOrders];
    const storageBaseUrl = "/storage";
    let selectedOrderId = null;
    let activeCategory = 'todos';

    function selectCategoryTab(category) {
        activeCategory = category;
        document.querySelectorAll('.category-tab').forEach(tab => {
            if (tab.getAttribute('data-category') === category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        applyVistaFilters();
    }

    function applyVistaFilters() {
        const searchVal = document.getElementById('search-op').value.toLowerCase().trim();
        const filterVal = document.getElementById('filter-status').value;
        const rows = document.querySelectorAll('.admin-row');

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
                matchesFilter = (estado === 'Pendiente' || estado === 'En proceso' || estado === 'En espera');
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
            } else {
                row.style.display = 'none';
            }
        });
    }

    function selectOrder(id) {
        const order = database.find(o => o.id === id);
        if (!order) return;

        if (selectedOrderId === id) {
            hideDetail();
            return;
        }

        selectedOrderId = id;

        document.querySelectorAll('.admin-row').forEach(row => {
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
        document.querySelectorAll('.admin-row').forEach(row => {
            row.classList.remove('active');
        });
        document.getElementById('detail-panel').classList.add('hidden');
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
            const parts = order.fecha_entrega.split('-');
            formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        if (order.hora_entrega) {
            formattedTime = order.hora_entrega.substring(0, 5);
        }
        document.getElementById('detail-entrega').textContent = `${formattedDate} a las ${formattedTime} hrs`;
        
        let statusBadgeClass = 'badge-pendiente';
        if (order.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
        if (order.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
        if (order.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';
        if (order.estado === 'En espera') statusBadgeClass = 'badge-en-espera';
        document.getElementById('detail-estado').innerHTML = `<span class="badge ${statusBadgeClass}">${order.estado}</span>`;
        
        document.getElementById('detail-avance').textContent = `${order.avance}%`;
        document.getElementById('detail-entregar-a').textContent = order.entregar_a;
        
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
    }

    function openHistoryModal() {
        if (!selectedOrderId) return;
        const timeline = document.getElementById('history-timeline');
        timeline.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 20px;">Cargando historial...</div>';
        
        document.getElementById('history-modal').classList.remove('hidden');
        
        fetch(`/op/historial/${selectedOrderId}`)
            .then(res => {
                if (!res.ok) throw new Error("Error loading history data");
                return res.json();
            })
            .then(history => {
                if (history.length === 0) {
                    timeline.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 20px;">No hay cambios registrados en esta orden.</div>';
                    return;
                }
                
                let html = '';
                history.forEach(item => {
                    const dt = new Date(item.created_at);
                    const formattedDate = `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}/${dt.getFullYear()} - ${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
                    
                    let eventIcon = '📝';
                    if (item.tipo_evento === 'cambio_estado') eventIcon = '🔄';
                    else if (item.tipo_evento === 'creacion') eventIcon = '🆕';
                    else if (item.tipo_evento === 'asignacion_lider') eventIcon = '👤';
                    else if (item.tipo_evento === 'solicitud_cambio') eventIcon = '📅';
                    else if (item.tipo_evento === 'aprobacion_cambio') eventIcon = '✅';
                    else if (item.tipo_evento === 'rechazo_cambio') eventIcon = '❌';
                    
                    html += `
                        <div style="background: rgba(255, 255, 255, 0.02); border-left: 3px solid var(--blue-bright); padding: 12px 16px; border-radius: 0 8px 8px 0; margin-bottom: 5px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <strong style="font-size: 0.9rem; color: var(--blue-bright);">${eventIcon} ${item.tipo_evento.toUpperCase().replace('_', ' ')}</strong>
                                <small style="font-size: 0.75rem; color: var(--text-muted);">${formattedDate}</small>
                            </div>
                            <p style="margin: 0; font-size: 0.85rem; color: var(--text-white); line-height: 1.4;">${item.descripcion}</p>
                            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 5px;">
                                Realizado por: ${item.realizado_por_nombre || 'SISTEMA'} (${item.realizado_por_codigo || '-'}) - Rol: ${item.realizado_por_rol || '-'}
                            </small>
                        </div>
                    `;
                });
                
                timeline.innerHTML = html;
            })
            .catch(err => {
                timeline.innerHTML = '<div style="color: var(--priority-urgente); text-align: center; padding: 20px;">Ocurrió un error al cargar el historial.</div>';
                console.log(err);
            });
    }

    function closeHistoryModal() {
        document.getElementById('history-modal').classList.add('hidden');
    }

    // Polling logic for Vista panel (updates lists in background)
    let isPolling = false;
    function pollVistaUpdates() {
        if (isPolling) return;
        isPolling = true;

        fetch(`/op/vista/updates?_t=${Date.now()}`)
            .then(res => {
                if (!res.ok) throw new Error("HTTP error polling status");
                return res.json();
            })
            .then(data => {
                // Sync internal orders database
                currentOrders = data.ordenes;
                database.length = 0;
                database.push(...currentOrders);

                // Update UI KPIs
                document.getElementById('kpi-total').textContent = data.kpis.total;
                document.getElementById('kpi-pendientes').textContent = data.kpis.pendientes;
                document.getElementById('kpi-en-proceso').textContent = data.kpis.en_proceso;
                document.getElementById('kpi-en-espera').textContent = data.kpis.en_espera;
                document.getElementById('kpi-terminadas').textContent = data.kpis.terminadas;

                // Re-render table rows
                const tbody = document.getElementById('vista-table-body');
                if (tbody) {
                    if (database.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 1.1rem;">
                                    No hay órdenes de producción registradas.
                                </td>
                            </tr>`;
                    } else {
                        let html = '';
                        database.forEach(orden => {
                            const badgeCategoryClass = orden.categoria === 'Branding' ? 'badge-normal' : (orden.categoria === 'Promocional' ? 'badge-proxima' : 'badge-urgente');
                            const progressFillClass = orden.estado === 'Pendiente' ? 'progress-fill-pendiente' :
                                                  (orden.estado === 'En proceso' ? 'progress-fill-proceso' :
                                                  (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' :
                                                  (orden.estado === 'En espera' ? 'progress-fill-en-espera' : 'progress-fill-terminado')));
                            
                            const fireClass = (orden.estado === 'Terminado' || orden.estado === 'Cancelado') ? 'extinguished' : (!orden.mostrar_fuego ? 'hidden-fire' : '');
                            const statusBadgeClass = orden.estado === 'En proceso' ? 'badge-proceso' :
                                                     (orden.estado === 'Terminado' ? 'badge-terminado' :
                                                     (orden.estado === 'Cancelado' ? 'badge-cancelado' :
                                                     (orden.estado === 'En espera' ? 'badge-en-espera' : 'badge-pendiente')));

                            let formattedDate = '-';
                            let formattedTime = '-';
                            if (orden.fecha_entrega) {
                                const parts = orden.fecha_entrega.split('-');
                                formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
                            }
                            if (orden.hora_entrega) {
                                formattedTime = orden.hora_entrega.substring(0, 5);
                            }

                            const isActive = selectedOrderId === orden.id ? 'active' : '';

                            html += `
                                <tr class="admin-row ${isActive}" id="row-${orden.id}" onclick="selectOrder(${orden.id})" data-id="${orden.id}" data-estado="${orden.estado}" data-prioridad="${orden.prioridad}" data-numero-op="${orden.numero_op}" data-categoria="${orden.categoria}">
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
                                    <td>${orden.marca}</td>
                                    <td>${orden.cliente}</td>
                                    <td>${orden.presupuestista}</td>
                                    <td>${orden.lider_produccion ? `<span style="color: var(--text-white); font-weight: 500;">${orden.lider_produccion}</span>` : `<em style="color: var(--text-muted); font-size: 0.9em;">Sin asignar</em>`}</td>
                                    <td>
                                        <span style="color: var(--text-white); font-weight: 500;">${formattedDate}</span><br>
                                        <small style="color: var(--text-muted);">${formattedTime}</small>
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
                                    <td style="text-align: center;" onclick="event.stopPropagation();">
                                        <button onclick="selectOrder(${orden.id})" class="btn-save-inline" style="background: rgba(0, 210, 255, 0.15); border-color: rgba(0, 210, 255, 0.3); color: var(--blue-bright);">
                                            Ver detalle
                                        </button>
                                    </td>
                                </tr>`;
                        });
                        tbody.innerHTML = html;
                    }
                }

                // Apply active filters on newly rendered table
                applyVistaFilters();

                // Sync details view
                if (selectedOrderId) {
                    const refreshedOrder = database.find(o => o.id === selectedOrderId);
                    if (refreshedOrder) {
                        populateDetail(refreshedOrder);
                    } else {
                        hideDetail();
                    }
                }
            })
            .catch(err => console.log("AJAX updates polling error (vista):", err))
            .finally(() => {
                isPolling = false;
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyVistaFilters();
        
        // Start polling updates every 15 seconds
        setInterval(pollVistaUpdates, 15000);
    });
</script>
@endsection
