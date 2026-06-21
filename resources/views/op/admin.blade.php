@extends('layouts.app')

@section('title', 'Bandeja de Producción')

@section('content')
<!-- Forms container for HTML5 form association -->
@foreach($ordenes as $orden)
    <form id="form-{{ $orden->id }}" action="{{ route('op.update', $orden->id) }}" method="POST">
        @csrf
    </form>
@endforeach

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
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
        <div class="kpi-value">{{ $kpis['total'] }}</div>
        <div class="kpi-label">Total Órdenes</div>
    </div>
    <!-- Pendientes -->
    <div class="kpi-card pending">
        <div class="kpi-value" style="color: var(--state-pendiente);">{{ $kpis['pendientes'] }}</div>
        <div class="kpi-label">Pendientes</div>
    </div>
    <!-- En Proceso -->
    <div class="kpi-card process">
        <div class="kpi-value" style="color: var(--state-en-proceso);">{{ $kpis['en_proceso'] }}</div>
        <div class="kpi-label">En Proceso</div>
    </div>
    <!-- Terminadas -->
    <div class="kpi-card finished">
        <div class="kpi-value" style="color: var(--state-terminado);">{{ $kpis['terminadas'] }}</div>
        <div class="kpi-label">Terminadas</div>
    </div>
    <!-- Urgentes -->
    <div class="kpi-card urgent">
        <div class="kpi-value" style="color: var(--priority-urgente);">{{ $kpis['urgentes'] }}</div>
        <div class="kpi-label">Urgentes 🔥</div>
    </div>
</div>

<!-- Main Table -->
<div class="card">
    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; color: var(--blue-bright);">
        Listado de Órdenes de Producción
    </h3>
    
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
            <tbody>
                @forelse($ordenes as $orden)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="color: var(--text-white);">{{ $orden->numero_op }}</strong>
                                <span id="fire-container-{{ $orden->id }}" class="fire-container @if($orden->estado === 'Terminado') extinguished @elseif(!$orden->mostrar_fuego) hidden-fire @endif" title="Alerta de prioridad temporal">
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
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No hay órdenes de producción registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form[id^="form-"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const formId = this.getAttribute('id');
                
                // Fetch external fields attached via 'form' attribute
                const externalInputs = document.querySelectorAll(`[form="${formId}"]`);
                externalInputs.forEach(input => {
                    formData.append(input.name, input.value);
                });

                const actionUrl = this.getAttribute('action');
                
                fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error al actualizar');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Orden actualizada correctamente.', 'success');
                        
                        // Update progress bar dynamically
                        const ordenId = formId.replace('form-', '');
                        const progressFill = document.getElementById(`progress-fill-${ordenId}`);
                        const progressText = document.getElementById(`progress-text-${ordenId}`);
                        
                        if (progressFill && progressText) {
                            progressText.textContent = data.avance + '%';
                            
                            // Rebuild class string
                            progressFill.className = 'progress-fill';
                            let stateClass = 'progress-fill-pendiente';
                            if (data.estado === 'En proceso') {
                                stateClass = 'progress-fill-proceso';
                            } else if (data.estado === 'Terminado') {
                                stateClass = 'progress-fill-terminado';
                            } else if (data.estado === 'Cancelado') {
                                stateClass = 'progress-fill-cancelado';
                            }
                            progressFill.classList.add(stateClass);
                        }

                        // Update fire animation classes dynamically
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
                    } else {
                        showToast('Ocurrió un error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error(error);
                    showToast('Error de conexión al servidor.', 'error');
                });
            });
        });
    });
</script>
@endsection
