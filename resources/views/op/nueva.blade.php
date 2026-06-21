@extends('layouts.app')

@section('title', 'Portal de Creación de OPs')

@section('content')
<div style="max-width: 850px; margin: 0 auto; padding-bottom: 50px;">
    
    <!-- Corporate Header Banner -->
    <div style="text-align: center; margin-bottom: 30px;">
        <div class="logo-glass-wrapper" style="padding: 22px; width: 150px; height: 150px; margin-bottom: 20px; display: inline-flex;">
            <img src="{{ asset('img/logo-btl.png') }}" alt="BTL Marketing Logo" style="height: 105px; object-fit: contain;">
        </div>
        <h1 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 10px; color: var(--text-white);">
            Portal de Creación de Órdenes de Producción <span style="color: var(--blue-bright);">BTL Marketing</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 1rem; max-width: 650px; margin: 0 auto 20px auto; line-height: 1.6;">
            Bienvenido al módulo de registro oficial de requerimientos. Ingrese los detalles de la Orden de Producción (OP) a continuación; toda la información ingresada se sincronizará automáticamente y se proyectará en tiempo real en los monitores operativos de producción.
        </p>
        <div style="display: flex; justify-content: center; gap: 15px;">
            <a href="{{ route('logout') }}" class="btn-logout" style="padding: 8px 24px;">Salir del Portal</a>
        </div>
    </div>

    <div class="card">
        <form action="{{ route('op.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <h3 style="font-size: 1.15rem; color: var(--blue-bright); margin-bottom: 20px; border-bottom: 1px solid var(--border-glass); padding-bottom: 8px;">
                1. Datos Generales de la OP
            </h3>

            <div class="grid-2">
                <!-- Categoría -->
                <div class="form-group">
                    <label for="categoria">Categoría *</label>
                    <select id="categoria" name="categoria" required>
                        <option value="" disabled {{ old('categoria') ? '' : 'selected' }}>Seleccione una categoría</option>
                        <option value="Branding" {{ old('categoria') === 'Branding' ? 'selected' : '' }}>Branding</option>
                        <option value="Promocional" {{ old('categoria') === 'Promocional' ? 'selected' : '' }}>Promocional</option>
                    </select>
                    @error('categoria')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Número OP -->
                <div class="form-group">
                    <label for="numero_op">Número OP *</label>
                    <input type="text" id="numero_op" name="numero_op" class="form-control" placeholder="Ej: OP-5421" value="{{ old('numero_op') }}" required>
                    @error('numero_op')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid-2">
                <!-- Proyecto -->
                <div class="form-group">
                    <label for="proyecto">Proyecto / Campaña *</label>
                    <input type="text" id="proyecto" name="proyecto" class="form-control" placeholder="Ej: Activación de Verano" value="{{ old('proyecto') }}" required>
                    @error('proyecto')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Presupuestista -->
                <div class="form-group">
                    <label for="presupuestista">Presupuestista *</label>
                    <input type="text" id="presupuestista" name="presupuestista" class="form-control" placeholder="Ej: Juan Pérez" value="{{ old('presupuestista') }}" required>
                    @error('presupuestista')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid-2">
                <!-- Cliente -->
                <div class="form-group">
                    <label for="cliente">Cliente *</label>
                    <input type="text" id="cliente" name="cliente" class="form-control" placeholder="Ej: Coca-Cola" value="{{ old('cliente') }}" required>
                    @error('cliente')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Marca -->
                <div class="form-group">
                    <label for="marca">Marca *</label>
                    <input type="text" id="marca" name="marca" class="form-control" placeholder="Ej: Sprite" value="{{ old('marca') }}" required>
                    @error('marca')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid-2">
                <!-- Fecha Entrega -->
                <div class="form-group">
                    <label for="fecha_entrega">Fecha de Entrega *</label>
                    <input type="date" id="fecha_entrega" name="fecha_entrega" class="form-control" value="{{ old('fecha_entrega') }}" required>
                    @error('fecha_entrega')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Hora Entrega -->
                <div class="form-group">
                    <label for="hora_entrega">Hora de Entrega *</label>
                    <input type="time" id="hora_entrega" name="hora_entrega" class="form-control" value="{{ old('hora_entrega') }}" required>
                    @error('hora_entrega')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- Brief File Upload -->
            <div class="form-group">
                <label for="brief">Subir Brief / Diseño (PDF, Imágenes, Zip) *</label>
                <input type="file" id="brief" name="brief" class="form-control">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Máximo archivo de 20MB</p>
                @error('brief')
                    <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                @enderror
            </div>

            <h3 style="font-size: 1.15rem; color: var(--blue-bright); margin-top: 30px; margin-bottom: 20px; border-bottom: 1px solid var(--border-glass); padding-bottom: 8px;">
                2. Destino y Entrega
            </h3>

            <!-- Entregar A -->
            <div class="form-group">
                <label for="entregar_a">Entregar A *</label>
                <select id="entregar_a" name="entregar_a" required>
                    <option value="Cliente" {{ old('entregar_a') === 'Cliente' ? 'selected' : '' }}>Cliente</option>
                    <option value="Bodega" {{ old('entregar_a') === 'Bodega' ? 'selected' : '' }}>Bodega</option>
                    <option value="Instaladores" {{ old('entregar_a') === 'Instaladores' ? 'selected' : '' }}>Instaladores</option>
                </select>
                @error('entregar_a')
                    <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                @enderror
            </div>

            <!-- Conditional Installation fields -->
            <div id="instalacion-fields-container" class="installation-fields hidden">
                <h4 style="font-size: 0.95rem; color: var(--priority-proxima); margin-bottom: 15px;">
                    Detalles de Instalación Requeridos
                </h4>
                
                <div class="form-group">
                    <label for="lugar_instalacion">Lugar de Instalación *</label>
                    <input type="text" id="lugar_instalacion" name="lugar_instalacion" class="form-control" placeholder="Ej: CC Miraflores, Plaza Central" value="{{ old('lugar_instalacion') }}">
                    @error('lugar_instalacion')
                        <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="fecha_instalacion">Fecha de Instalación *</label>
                        <input type="date" id="fecha_instalacion" name="fecha_instalacion" class="form-control" value="{{ old('fecha_instalacion') }}">
                        @error('fecha_instalacion')
                            <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="hora_instalacion">Hora de Instalación *</label>
                        <input type="time" id="hora_instalacion" name="hora_instalacion" class="form-control" value="{{ old('hora_instalacion') }}">
                        @error('hora_instalacion')
                            <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="fecha_desinstalacion">Fecha de Desinstalación *</label>
                        <input type="date" id="fecha_desinstalacion" name="fecha_desinstalacion" class="form-control" value="{{ old('fecha_desinstalacion') }}">
                        @error('fecha_desinstalacion')
                            <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="hora_desinstalacion">Hora de Desinstalación *</label>
                        <input type="time" id="hora_desinstalacion" name="hora_desinstalacion" class="form-control" value="{{ old('hora_desinstalacion') }}">
                        @error('hora_desinstalacion')
                            <span style="color: var(--priority-urgente); font-size: 0.8rem;">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-primary" style="margin-top: 30px;">
                Crear y Enviar a Producción
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function toggleInstallationFields() {
        const select = document.getElementById('entregar_a');
        const container = document.getElementById('instalacion-fields-container');
        if (!select || !container) return;
        
        const fields = container.querySelectorAll('input');
        if (select.value === 'Instaladores') {
            container.classList.remove('hidden');
            fields.forEach(f => f.setAttribute('required', 'required'));
        } else {
            container.classList.add('hidden');
            fields.forEach(f => f.removeAttribute('required'));
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleInstallationFields();
        document.getElementById('entregar_a').addEventListener('change', toggleInstallationFields);
    });
</script>
@endsection
