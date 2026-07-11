{{--
    Partial: listado-op.blade.php
    Contiene: formularios hidden, filtros, tabs de categoría, tabla de OPs y paginación.
    Utilizado tanto en la vista completa (op.admin) como en respuestas AJAX parciales.
    Variables requeridas: $ordenes (LengthAwarePaginator)
--}}

{{-- Formularios ocultos para los selects inline de cada fila --}}
<div id="forms-container">
@foreach($ordenes as $orden)
    <form id="form-{{ $orden->id }}" action="{{ route('op.update', $orden->id) }}" method="POST">
        @csrf
    </form>
@endforeach
</div>

{{-- Cabecera: título + filtros de búsqueda y estado --}}
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--blue-bright); margin: 0;">
        Listado de Órdenes de Producción
    </h3>
    
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        {{-- Búsqueda por número de OP --}}
        <div style="position: relative; min-width: 220px;">
            <input 
                type="text" 
                id="search-op" 
                placeholder="Buscar por número OP..." 
                class="form-control" 
                style="padding: 8px 12px; font-size: 0.9rem; height: 38px; width: 100%;"
                value="{{ request('search') }}"
                autocomplete="off"
            >
        </div>
        
        {{-- Filtro por estado --}}
        <div style="min-width: 180px;">
            <select 
                id="filter-status" 
                class="form-control" 
                style="padding: 8px 12px; font-size: 0.9rem; height: 38px; cursor: pointer; width: 100%;"
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

{{-- Pestañas de Categoría --}}
@if(in_array(session('user_role'), ['admin', 'admin_branding', 'admin_promo']))
@php
    $defaultCat = session('user_role') === 'admin_branding' ? 'Branding' : (session('user_role') === 'admin_promo' ? 'Promocional' : 'todos');
    $activeCat = request('category', $defaultCat);
@endphp
<div class="category-tabs-container" id="category-tabs-container">
    <button class="category-tab {{ $activeCat === 'todos' ? 'active' : '' }}" data-category="todos" onclick="selectCategoryTab('todos')">Todas</button>
    <button class="category-tab {{ $activeCat === 'Branding' ? 'active' : '' }}" data-category="Branding" onclick="selectCategoryTab('Branding')">Branding</button>
    <button class="category-tab {{ $activeCat === 'Promocional' ? 'active' : '' }}" data-category="Promocional" onclick="selectCategoryTab('Promocional')">Promocional</button>
    <button class="category-tab {{ $activeCat === 'Reprocesos' ? 'active' : '' }}" data-category="Reprocesos" onclick="selectCategoryTab('Reprocesos')">Reprocesos</button>
</div>
@endif

{{-- Loader discreto (oculto por defecto, visible durante fetch AJAX) --}}
<div id="listado-loader" style="display: none; text-align: center; padding: 20px 0;">
    <div style="display: inline-flex; align-items: center; gap: 10px; color: var(--text-muted); font-size: 0.9rem;">
        <span style="display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(0,210,255,0.3); border-top-color: var(--blue-bright); border-radius: 50%; animation: spin-listado 0.7s linear infinite;"></span>
        Actualizando listado...
    </div>
</div>

{{-- Mensaje de error AJAX (oculto por defecto) --}}
<div id="listado-error" style="display: none; padding: 12px 16px; background: rgba(255,51,102,0.1); border: 1px solid rgba(255,51,102,0.3); border-radius: 8px; color: #ff3366; font-size: 0.9rem; margin-bottom: 12px;">
    ⚠️ No se pudo actualizar el listado. 
    <button onclick="retryLastFilter()" style="background: none; border: none; color: var(--blue-bright); cursor: pointer; font-size: 0.9rem; text-decoration: underline; padding: 0 4px;">Reintentar</button>
</div>

{{-- Tabla principal --}}
<div class="table-responsive" id="listado-table-wrapper">
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

{{-- Paginación --}}
@if($ordenes->hasPages())
    <div class="pagination-container" id="listado-pagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding: 10px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-glass); border-radius: 8px;">
        @if($ordenes->onFirstPage())
            <span style="opacity: 0.5; pointer-events: none; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 6px; color: var(--text-muted); font-size: 0.85rem;">« Anterior</span>
        @else
            <a href="{{ $ordenes->appends(request()->query())->previousPageUrl() }}" class="btn-save-inline pagination-link" style="padding: 6px 12px; background: rgba(0, 210, 255, 0.1); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 6px; color: var(--blue-bright); text-decoration: none; font-size: 0.85rem;">« Anterior</a>
        @endif

        <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Página {{ $ordenes->currentPage() }} de {{ $ordenes->lastPage() }}</span>

        @if($ordenes->hasMorePages())
            <a href="{{ $ordenes->appends(request()->query())->nextPageUrl() }}" class="btn-save-inline pagination-link" style="padding: 6px 12px; background: rgba(0, 210, 255, 0.1); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 6px; color: var(--blue-bright); text-decoration: none; font-size: 0.85rem;">Siguiente »</a>
        @else
            <span style="opacity: 0.5; pointer-events: none; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 6px; color: var(--text-muted); font-size: 0.85rem;">Siguiente »</span>
        @endif
    </div>
@endif

{{-- Datos JSON para actualizar allOrders en el contexto padre (solo se usa al reemplazar el partial vía AJAX) --}}
<script id="listado-orders-data" type="application/json">@json($ordenes->items())</script>
