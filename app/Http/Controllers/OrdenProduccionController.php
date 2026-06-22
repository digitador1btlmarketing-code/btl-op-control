<?php

namespace App\Http\Controllers;

use App\Models\OrdenProduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrdenProduccionController extends Controller
{
    /**
     * Show the form to create a new order.
     */
    public function create()
    {
        return view('op.nueva');
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
        $rules = [
            'categoria' => 'required|in:Branding,Promocional',
            'numero_op' => 'required|string|max:100',
            'proyecto' => 'required|string|max:255',
            'presupuestista' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'marca' => 'required|string|max:255',
            'fecha_entrega' => 'required|date',
            'hora_entrega' => 'required',
            'entregar_a' => 'required|in:Cliente,Bodega,Instaladores',
            'brief' => 'nullable|file|mimes:pdf,ppt,pptx,zip,jpg,jpeg,png,ai,psd|max:102400',
        ];

        // Conditional validation based on entregar_a
        if ($request->input('entregar_a') === 'Instaladores') {
            $rules['lugar_instalacion'] = 'required|string|max:255';
            $rules['fecha_instalacion'] = 'required|date';
            $rules['hora_instalacion'] = 'required';
            $rules['fecha_desinstalacion'] = 'required|date';
            $rules['hora_desinstalacion'] = 'required';
        }

        $validated = $request->validate($rules, [
            'categoria.required' => 'La categoría es obligatoria.',
            'numero_op.required' => 'El número de OP es obligatorio.',
            'proyecto.required' => 'El nombre del proyecto es obligatorio.',
            'presupuestista.required' => 'El presupuestista es obligatorio.',
            'cliente.required' => 'El cliente es obligatorio.',
            'marca.required' => 'La marca es obligatoria.',
            'fecha_entrega.required' => 'La fecha de entrega es obligatoria.',
            'hora_entrega.required' => 'La hora de entrega es obligatoria.',
            'entregar_a.required' => 'El destino de entrega es obligatorio.',
            'lugar_instalacion.required' => 'El lugar de instalación es obligatorio.',
            'fecha_instalacion.required' => 'La fecha de instalación es obligatoria.',
            'hora_instalacion.required' => 'La hora de instalación es obligatoria.',
            'fecha_desinstalacion.required' => 'La fecha de desinstalación es obligatoria.',
            'hora_desinstalacion.required' => 'La hora de desinstalación es obligatoria.',
            'brief.max' => 'El archivo brief no debe pesar más de 100MB.',
        ]);

        // Clean up installation fields if not Instaladores
        if ($request->input('entregar_a') !== 'Instaladores') {
            $validated['lugar_instalacion'] = null;
            $validated['fecha_instalacion'] = null;
            $validated['hora_instalacion'] = null;
            $validated['fecha_desinstalacion'] = null;
            $validated['hora_desinstalacion'] = null;
        }

        // Handle file upload
        if ($request->hasFile('brief')) {
            $path = $request->file('brief')->store('briefs', 'public');
            $validated['brief'] = $path;
        }

        // Create the order. 'avance' and 'estado' are set via defaults & events.
        OrdenProduccion::create($validated);

        return redirect('/op/nueva')->with('success', 'Orden de Producción creada correctamente.');
    }

    /**
     * Display the admin production control panel.
     */
    public function admin()
    {
        $ordenes = OrdenProduccion::orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
        ];

        return view('op.admin', compact('ordenes', 'kpis'));
    }

    /**
     * Quick update of status and leader.
     */
    public function updateQuick(Request $request, $id)
    {
        $request->validate([
            'lider_produccion' => 'nullable|string|max:255',
            'estado' => 'required|in:Pendiente,En proceso,Terminado,Cancelado',
        ]);

        $orden = OrdenProduccion::findOrFail($id);
        $orden->update([
            'lider_produccion' => $request->input('lider_produccion'),
            'estado' => $request->input('estado'),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Orden de producción actualizada correctamente.',
                'avance' => $orden->avance,
                'prioridad' => $orden->prioridad,
                'dias_restantes' => $orden->dias_restantes,
                'mostrar_fuego' => $orden->mostrar_fuego,
                'estado' => $orden->estado,
            ]);
        }

        return redirect()->back()->with('success', 'Orden actualizada.');
    }

    /**
     * Display the TV screen.
     */
    public function tv(Request $request)
    {
        // Enforced by middleware, but fallback is Branding
        $categoria = $request->query('categoria', 'Branding');

        $ordenesRaw = OrdenProduccion::where('categoria', $categoria)->get();

        // Sort: Urgent first, then Próxima, then Normal
        // We can sort by dias_restantes ascending
        $ordenes = $ordenesRaw->sortBy(function ($orden) {
            return $orden->dias_restantes;
        })->values();

        $kpis = [
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
            'visibles' => $ordenes->count(),
        ];

        return view('op.tv', compact('ordenes', 'kpis', 'categoria'));
    }

    /**
     * Get real-time JSON updates for the TV screen.
     */
    public function tvUpdates(Request $request)
    {
        $categoria = $request->query('categoria', 'Branding');
        $ordenesRaw = OrdenProduccion::where('categoria', $categoria)->get();

        $ordenes = $ordenesRaw->sortBy(function ($orden) {
            return $orden->dias_restantes;
        })->values();

        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $kpis = [
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
            'visibles' => $ordenes->count(),
        ];

        return response()->json([
            'ordenes' => $ordenes,
            'kpis' => $kpis
        ]);
    }

    /**
     * Get real-time JSON updates for the Admin panel.
     */
    public function adminUpdates()
    {
        $ordenes = OrdenProduccion::orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
        ];

        return response()->json([
            'ordenes' => $ordenes,
            'kpis' => $kpis
        ]);
    }

    /**
     * Delete all production orders securely.
     */
    public function reset(Request $request)
    {
        // Secondary security check (redundant with middleware but safe)
        if (session('user_role') !== 'admin') {
            return redirect('/')->with('error', 'No tiene permisos para acceder a esta sección.');
        }

        $request->validate([
            'reset_password' => 'required|string',
        ], [
            'reset_password.required' => 'La contraseña de seguridad es obligatoria.',
        ]);

        $inputPassword = $request->input('reset_password');
        $expectedPassword = env('RESET_PASSWORD');

        if ($inputPassword !== $expectedPassword) {
            return redirect()->route('op.admin')->with('error', 'Contraseña de seguridad incorrecta.');
        }

        // Truncate only the orden_produccions table
        OrdenProduccion::truncate();

        return redirect()->route('op.admin')->with('success', 'Órdenes eliminadas correctamente.');
    }
}
