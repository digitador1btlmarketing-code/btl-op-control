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
<div class="grid-4" style="margin-bottom: 25px;">
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
    <div class="kpi-card finished">
        <div id="kpi-terminadas" class="kpi-value" style="color: var(--state-terminado);">{{ $ordenes->where('estado', 'Terminado')->count() }}</div>
        <div class="kpi-label">Terminadas</div>
    </div>
</div>

<!-- Main Table -->
<div class="card">
    <div style="overflow-x: auto;">
        <table class="table-tv" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th>Ticket OP</th>
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
                                              ($orden->estado === 'Cancelado' ? 'progress-fill-cancelado' : 'progress-fill-terminado'));
                    @endphp
                    <tr class="admin-row" id="row-{{ $orden->id }}" onclick="selectOrder({{ $orden->id }})">
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="color: var(--text-white);">{{ $orden->numero_op }}</strong>
                                <span id="fire-container-{{ $orden->id }}" class="fire-container @if($orden->estado === 'Terminado' || $orden->estado === 'Cancelado') extinguished @elseif(!$orden->mostrar_fuego) hidden-fire @endif" title="Alerta de prioridad temporal">
                                    <span class="fire-flame">🔥</span>
                                </span>
                            </div>
                        </td>
                        <td>{{ $orden->cliente }}</td>
                        <td>{{ $orden->marca }}</td>
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
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No tiene órdenes de producción registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
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

    <!-- Date change status view in details -->
    <div id="detail-date-change-status-section" style="margin-top: 15px; border-top: 1px solid var(--border-glass); padding-top: 15px;">
        <h4 style="font-size: 1.05rem; color: var(--blue-bright); margin-bottom: 10px; font-weight: 700;">Estado de Cambio de Fecha</h4>
        <div id="detail-date-change-info" style="font-size: 0.9rem; line-height: 1.4;">-</div>
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
    let selectedOrderId = null;
    let allOrders = @json($ordenes);
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

        // Display change request info
        const infoDiv = document.getElementById('detail-date-change-info');
        if (order.solicitud_pendiente) {
            const req = order.solicitud_pendiente;
            const reqDate = req.fecha_solicitada.split('-').reverse().join('/');
            const reqTime = req.hora_solicitada.substring(0, 5);
            infoDiv.innerHTML = `
                <div style="background: rgba(243, 166, 59, 0.08); border: 1px solid rgba(243, 166, 59, 0.2); padding: 10px; border-radius: 8px;">
                    <span style="color: var(--state-pendiente); font-weight: 600; display: block; margin-bottom: 5px;">⚠️ Solicitud Pendiente de Aprobación</span>
                    Nueva fecha sugerida: <strong>${reqDate} ${reqTime} hrs</strong><br>
                    Razón: <span style="font-style: italic;">"${req.razon_solicitud}"</span>
                </div>
            `;
        } else {
            infoDiv.innerHTML = '<span style="color: var(--text-muted);">No hay cambios pendientes de aprobación.</span>';
        }

        // History is loaded inside the collapsible History modal on demand
    }

    // Polling updates for vendedor panel
    function pollVendedorUpdates() {
        fetch('/op/mis-ordenes/updates', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
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
            let terminadas = 0;

            newOrders.forEach(orden => {
                if (orden.estado === 'Pendiente') pendientes++;
                if (orden.estado === 'En proceso') proceso++;
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
                                          (orden.estado === 'Cancelado' ? 'progress-fill-cancelado' : 'progress-fill-terminado'));
                
                let statusBadgeClass = 'badge-pendiente';
                if (orden.estado === 'En proceso') statusBadgeClass = 'badge-proceso';
                if (orden.estado === 'Terminado') statusBadgeClass = 'badge-terminado';
                if (orden.estado === 'Cancelado') statusBadgeClass = 'badge-cancelado';

                let requestBadge = '<span style="color: var(--text-muted); font-weight: 500;">-</span>';
                if (orden.solicitud_pendiente) {
                    requestBadge = '<span class="badge badge-pendiente">Pendiente de aprobación</span>';
                }

                row.innerHTML = `
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: var(--text-white);">${orden.numero_op}</strong>
                            <span id="fire-container-${orden.id}" class="fire-container ${fireClass}" title="Alerta de prioridad temporal">
                                <span class="fire-flame">🔥</span>
                            </span>
                        </div>
                    </td>
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
            document.getElementById('kpi-terminadas').textContent = terminadas;

            allOrders = newOrders;

            // Sync open detail
            if (selectedOrderId) {
                const refreshedOrder = allOrders.find(o => o.id === selectedOrderId);
                if (refreshedOrder) {
                    populateDetail(refreshedOrder);
                } else {
                    hideDetail();
                }
            }
        })
        .catch(err => console.log("AJAX updates polling error:", err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Start polling updates every 10 seconds
        setInterval(pollVendedorUpdates, 10000);
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
</script>
@endsection
