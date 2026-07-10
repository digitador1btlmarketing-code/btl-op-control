<?php

namespace App\Http\Controllers;

use App\Models\OrdenProduccion;
use App\Models\UsuarioAcceso;
use App\Models\SolicitudCambioFecha;
use App\Models\HistorialOrden;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

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
            'categoria' => 'required|in:Branding,Promocional,Reprocesos,Reproceso,REPROCESO',
            'numero_op' => 'required|string|max:100',
            'proyecto' => 'required|string|max:255',
            'presupuestista' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'marca' => 'required|string|max:255',
            'fecha_entrega' => 'required|date',
            'hora_entrega' => 'required',
            'entregar_a' => 'required|in:Cliente,Bodega,Instaladores',
            'brief' => 'nullable|array',
            'brief.*' => 'file|max:102400',
            'detalles' => 'nullable|string',
        ];

        // Conditional validation based on entregar_a
        if ($request->input('entregar_a') === 'Instaladores') {
            $rules['lugar_instalacion'] = 'required|string|max:255';
            $rules['fecha_instalacion'] = 'required|date';
            $rules['hora_instalacion'] = 'required';
            $rules['fecha_desinstalacion'] = 'nullable|date';
            $rules['hora_desinstalacion'] = 'nullable';
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
            'brief.*.max' => 'El archivo no debe pesar más de 100MB.',
        ]);

        // Manual extension validation to avoid mime type detection errors
        if ($request->hasFile('brief')) {
            $files = $request->file('brief');
            $allowedExtensions = ['pdf', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ai', 'psd', 'xls', 'xlsx', 'csv', 'doc', 'docx'];
            
            foreach ($files as $file) {
                if ($file) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, $allowedExtensions)) {
                        return redirect()->back()
                            ->withInput()
                            ->withErrors(['brief' => 'El archivo no es válido. Formatos permitidos: PDF, Excel, Word, PowerPoint, ZIP, imágenes, AI, PSD y CSV.']);
                    }
                }
            }
        }

        // Clean up installation fields if not Instaladores
        if ($request->input('entregar_a') !== 'Instaladores') {
            $validated['lugar_instalacion'] = null;
            $validated['fecha_instalacion'] = null;
            $validated['hora_instalacion'] = null;
            $validated['fecha_desinstalacion'] = null;
            $validated['hora_desinstalacion'] = null;
        }

        // We will save files after creating the order, so exclude brief from validated array for creation
        $briefFiles = $request->file('brief');
        unset($validated['brief']);

        // Add creator tracking
        $validated['creado_por_codigo'] = session('user_code');
        $validated['creado_por_nombre'] = session('user_name');
        $validated['creado_por_rol'] = session('user_role');

        // Create the order. 'avance' and 'estado' are set via defaults & events.
        $orden = OrdenProduccion::create($validated);

        // Handle multiple file uploads
        if ($briefFiles) {
            $firstPath = null;
            if (is_array($briefFiles)) {
                foreach ($briefFiles as $index => $file) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->store('briefs', 'public');
                    if ($index === 0) {
                        $firstPath = $path;
                    }
                    $orden->archivos()->create([
                        'file_path' => $path,
                        'file_name' => $originalName,
                    ]);
                }
            } else {
                $file = $briefFiles;
                $originalName = $file->getClientOriginalName();
                $path = $file->store('briefs', 'public');
                $firstPath = $path;
                $orden->archivos()->create([
                    'file_path' => $path,
                    'file_name' => $originalName,
                ]);
            }
            if ($firstPath) {
                $orden->update(['brief' => $firstPath]);
            }
        }

        // Log to history
        $orden->historial()->create([
            'tipo_evento' => 'creacion',
            'descripcion' => 'OP creada por ' . session('user_name') . ' (' . session('user_code') . ') con rol ' . session('user_role') . '.',
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        $this->clearDashboardCache();

        return redirect('/op/nueva')->with('success', 'Orden de Producción creada correctamente.');
    }

    /**
     * Display the admin production control panel.
     */
    public function admin()
    {
        $userRole = session('user_role');
        $query = OrdenProduccion::with(['solicitudPendiente', 'archivos', 'reprocesos', 'solicitudesReproceso']);

        if ($userRole === 'admin_branding') {
            $query->where(function ($q) {
                $q->where('categoria', 'Branding')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso'])
                          ->whereHas('original', function ($orig) {
                              $orig->where('categoria', 'Branding');
                          });
                  });
            });
        } elseif ($userRole === 'admin_promo') {
            $query->where(function ($q) {
                $q->where('categoria', 'Promocional')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso'])
                          ->whereHas('original', function ($orig) {
                              $orig->where('categoria', 'Promocional');
                          });
                  });
            });
        }

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $solicitudesReprocesoQuery = SolicitudReproceso::with('ordenProduccion')
            ->where('estado', 'Pendiente');

        if ($userRole === 'admin_branding') {
            $solicitudesReprocesoQuery->whereHas('ordenProduccion', function ($q) {
                $q->where('categoria', 'Branding');
            });
        } elseif ($userRole === 'admin_promo') {
            $solicitudesReprocesoQuery->whereHas('ordenProduccion', function ($q) {
                $q->where('categoria', 'Promocional');
            });
        }

        $solicitudesReproceso = $solicitudesReprocesoQuery->get();

        $kpis = \Illuminate\Support\Facades\Cache::remember("dashboard_stats_{$userRole}", 60, function () use ($ordenes, $solicitudes, $solicitudesReproceso) {
            return [
                'total' => $ordenes->count(),
                'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
                'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
                'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
                'en_espera' => $ordenes->where('estado', 'En espera')->count(),
                'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
                'solicitudes_pendientes' => $solicitudes->count() + $solicitudesReproceso->count(),
            ];
        });

        // Fetch all users for Master Admin user management panel
        $usuarios = [];
        if ($userRole === 'admin') {
            $usuarios = UsuarioAcceso::orderBy('rol', 'asc')
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($u) {
                    $u->op_count = OrdenProduccion::where('creado_por_codigo', $u->codigo)->count();
                    return $u;
                });
        }

        return view('op.admin', compact('ordenes', 'kpis', 'usuarios', 'solicitudes', 'solicitudesReproceso'));
    }

    /**
     * Quick update of status and leader.
     */
    public function updateQuick(Request $request, $id)
    {
        $request->validate([
            'lider_produccion' => 'nullable|string|max:255',
            'estado' => 'required|in:Pendiente,En proceso,Terminado,Cancelado,En espera',
            'avance' => 'nullable|in:25,50,75',
        ]);

        $orden = OrdenProduccion::findOrFail($id);
        $oldLider = $orden->lider_produccion;
        $oldEstado = $orden->estado;
        $oldAvance = $orden->avance;

        $updateData = [
            'lider_produccion' => $request->input('lider_produccion'),
            'estado' => $request->input('estado'),
        ];

        if ($request->input('estado') === 'En proceso' && $request->has('avance')) {
            $updateData['avance'] = (int) $request->input('avance');
        }

        $orden->update($updateData);

        // Refresh to get the auto-calculated progress
        $orden->refresh();

        $userCode = session('user_code') ?: 'SISTEMA';
        $userName = session('user_name') ?: 'SISTEMA';
        $userRol = session('user_role') ?: 'sistema';

        // Check leader change
        if ($oldLider !== $orden->lider_produccion) {
            $desc = $orden->lider_produccion 
                ? "{$userName} ({$userCode}) asignó a {$orden->lider_produccion} como líder de producción." 
                : "{$userName} ({$userCode}) quitó al líder de producción.";
            
            $orden->historial()->create([
                'tipo_evento' => 'asignacion_lider',
                'descripcion' => $desc,
                'realizado_por_codigo' => $userCode,
                'realizado_por_nombre' => $userName,
                'realizado_por_rol' => $userRol,
            ]);
        }

        // Check status change
        if ($oldEstado !== $orden->estado) {
            $orden->historial()->create([
                'tipo_evento' => 'cambio_estado',
                'descripcion' => "{$userName} ({$userCode}) cambió el estado a {$orden->estado} (avance a {$orden->avance}%).",
                'realizado_por_codigo' => $userCode,
                'realizado_por_nombre' => $userName,
                'realizado_por_rol' => $userRol,
            ]);
        }

        $this->clearDashboardCache();

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

        $ordenesRaw = OrdenProduccion::with('archivos')
            ->where(function($q) use ($categoria) {
                $q->where('categoria', $categoria)
                  ->orWhere(function($sub) use ($categoria) {
                      $sub->whereIn('categoria', ['Reprocesos', 'Reproceso', 'REPROCESO'])
                          ->whereHas('original', function($orig) use ($categoria) {
                              $orig->where('categoria', $categoria);
                          });
                  });
            })
            ->get();

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
        $ordenesRaw = OrdenProduccion::with(['archivos'])
            ->where(function($q) use ($categoria) {
                $q->where('categoria', $categoria)
                  ->orWhere(function($sub) use ($categoria) {
                      $sub->whereIn('categoria', ['Reprocesos', 'Reproceso', 'REPROCESO'])
                          ->whereHas('original', function($orig) use ($categoria) {
                              $orig->where('categoria', $categoria);
                          });
                  });
            })
            ->get();

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
            'kpis' => $kpis,
            'recent_events' => $this->getRecentEventsForTV($categoria)
        ]);
    }

    /**
     * Get real-time JSON updates for the Admin panel.
     */
    public function adminUpdates()
    {
        $userRole = session('user_role');
        $query = OrdenProduccion::with(['solicitudPendiente', 'archivos', 'reprocesos', 'solicitudesReproceso']);

        if ($userRole === 'admin_branding') {
            $query->where(function ($q) {
                $q->where('categoria', 'Branding')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso'])
                          ->whereHas('original', function ($orig) {
                              $orig->where('categoria', 'Branding');
                          });
                  });
            });
        } elseif ($userRole === 'admin_promo') {
            $query->where(function ($q) {
                $q->where('categoria', 'Promocional')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso'])
                          ->whereHas('original', function ($orig) {
                              $orig->where('categoria', 'Promocional');
                          });
                  });
            });
        }

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pending')
            ->orWhere('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $solicitudesReprocesoQuery = SolicitudReproceso::with('ordenProduccion')
            ->where('estado', 'Pendiente');

        if ($userRole === 'admin_branding') {
            $solicitudesReprocesoQuery->whereHas('ordenProduccion', function ($q) {
                $q->where('categoria', 'Branding');
            });
        } elseif ($userRole === 'admin_promo') {
            $solicitudesReprocesoQuery->whereHas('ordenProduccion', function ($q) {
                $q->where('categoria', 'Promocional');
            });
        }

        $solicitudesReproceso = $solicitudesReprocesoQuery->get();

        $kpis = \Illuminate\Support\Facades\Cache::remember("dashboard_stats_{$userRole}", 60, function () use ($ordenes, $solicitudes, $solicitudesReproceso) {
            return [
                'total' => $ordenes->count(),
                'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
                'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
                'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
                'en_espera' => $ordenes->where('estado', 'En espera')->count(),
                'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
                'solicitudes_pendientes' => $solicitudes->count() + $solicitudesReproceso->count(),
            ];
        });

        // Fetch requests resolved in the last 5 minutes (filtered by admin category)
        $recentResolutionsQuery = SolicitudCambioFecha::with('ordenProduccion')
            ->whereIn('estado_solicitud', ['Aprobada', 'Rechazada'])
            ->where('updated_at', '>=', now()->subMinutes(5));

        if ($userRole === 'admin_branding') {
            $recentResolutionsQuery->whereHas('ordenProduccion', function($q) {
                $q->where('categoria', 'Branding');
            });
        } elseif ($userRole === 'admin_promo') {
            $recentResolutionsQuery->whereHas('ordenProduccion', function($q) {
                $q->where('categoria', 'Promocional');
            });
        }

        $recentResolutions = $recentResolutionsQuery->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'numero_op' => $r->ordenProduccion->numero_op ?? 'OP',
                    'estado_solicitud' => $r->estado_solicitud,
                    'fecha_solicitada' => $r->fecha_solicitada ? Carbon::parse($r->fecha_solicitada)->format('d/m/Y') : '',
                    'hora_solicitada' => $r->hora_solicitada ? substr($r->hora_solicitada, 0, 5) : '',
                    'razon_rechazo' => $r->razon_rechazo,
                    'updated_at' => $r->updated_at->toIso8601String(),
                ];
            });

        return response()->json([
            'ordenes' => $ordenes,
            'solicitudes' => $solicitudes,
            'solicitudes_reproceso' => $solicitudesReproceso,
            'kpis' => $kpis,
            'recent_resolutions' => $recentResolutions,
            'recent_events' => $this->getRecentEvents()
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

    /**
     * Display Vendedor Panel.
     */
    public function misOrdenes()
    {
        $vendedorCodigo = session('user_code');
        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])
            ->where(function($q) use ($vendedorCodigo) {
                $q->where('creado_por_codigo', $vendedorCodigo)
                  ->orWhere(function($sub) use ($vendedorCodigo) {
                      $sub->where('categoria', 'Reprocesos')
                          ->whereHas('original', function($o) use ($vendedorCodigo) {
                              $o->where('creado_por_codigo', $vendedorCodigo);
                          });
                  });
            })
            ->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();
            
        $ordenes->load('solicitudPendiente');

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        return view('op.mis_ordenes', compact('ordenes', 'solicitudes'));
    }

    /**
     * Polling updates for Vendedor Panel.
     */
    public function misOrdenesUpdates()
    {
        $vendedorCodigo = session('user_code');
        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])
            ->where(function($q) use ($vendedorCodigo) {
                $q->where('creado_por_codigo', $vendedorCodigo)
                  ->orWhere(function($sub) use ($vendedorCodigo) {
                      $sub->where('categoria', 'Reprocesos')
                          ->whereHas('original', function($o) use ($vendedorCodigo) {
                              $o->where('creado_por_codigo', $vendedorCodigo);
                          });
                  });
            })
            ->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();
            
        $ordenes->load('solicitudPendiente');
        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        return response()->json([
            'ordenes' => $ordenes,
            'solicitudes' => $solicitudes,
            'recent_events' => $this->getRecentEvents()
        ]);
    }

    /**
     * Display Jefe de Ventas Panel.
     */
    public function jefeVentasPanel()
    {
        $jefeCodigo = session('user_code');

        // Filter OPs: created by vendors belonging to this jefe or by the jefe itself
        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])->where(function ($query) use ($jefeCodigo) {
            $query->where(function ($q) use ($jefeCodigo) {
                $q->whereIn('creado_por_codigo', function ($sub) use ($jefeCodigo) {
                    $sub->select('codigo')
                        ->from('usuarios_acceso')
                        ->where('jefe_codigo', $jefeCodigo);
                })
                ->orWhere('creado_por_codigo', $jefeCodigo);
            })
            ->orWhere(function ($sub) use ($jefeCodigo) {
                $sub->where('categoria', 'Reprocesos')
                    ->whereHas('original', function ($orig) use ($jefeCodigo) {
                        $orig->where(function ($q) use ($jefeCodigo) {
                            $q->whereIn('creado_por_codigo', function ($sub2) use ($jefeCodigo) {
                                $sub2->select('codigo')
                                    ->from('usuarios_acceso')
                                    ->where('jefe_codigo', $jefeCodigo);
                            })
                            ->orWhere('creado_por_codigo', $jefeCodigo);
                        });
                    });
            });
        })
        ->orderBy('fecha_entrega', 'asc')
        ->orderBy('hora_entrega', 'asc')
        ->get();
        $ordenes->load('solicitudPendiente');

        // Solicitudes filter: using unified canApproveOrRejectSolicitud helper
        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'en_espera' => $ordenes->where('estado', 'En espera')->count(),
            'solicitudes_pendientes' => $solicitudes->count(),
        ];

        // Filter vendors belonging to this jefe
        $usuarios = UsuarioAcceso::where('rol', 'ventas')
            ->where('jefe_codigo', $jefeCodigo)
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(function ($u) {
                $u->op_count = OrdenProduccion::where('creado_por_codigo', $u->codigo)->count();
                return $u;
            });

        return view('op.jefe_ventas', compact('ordenes', 'kpis', 'solicitudes', 'usuarios'));
    }

    /**
     * Polling updates for Jefe de Ventas Panel.
     */
    public function jefeVentasUpdates()
    {
        $jefeCodigo = session('user_code');

        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])->where(function ($query) use ($jefeCodigo) {
            $query->where(function ($q) use ($jefeCodigo) {
                $q->whereIn('creado_por_codigo', function ($sub) use ($jefeCodigo) {
                    $sub->select('codigo')
                        ->from('usuarios_acceso')
                        ->where('jefe_codigo', $jefeCodigo);
                })
                ->orWhere('creado_por_codigo', $jefeCodigo);
            })
            ->orWhere(function ($sub) use ($jefeCodigo) {
                $sub->where('categoria', 'Reprocesos')
                    ->whereHas('original', function ($orig) use ($jefeCodigo) {
                        $orig->where(function ($q) use ($jefeCodigo) {
                            $q->whereIn('creado_por_codigo', function ($sub2) use ($jefeCodigo) {
                                $sub2->select('codigo')
                                    ->from('usuarios_acceso')
                                    ->where('jefe_codigo', $jefeCodigo);
                            })
                            ->orWhere('creado_por_codigo', $jefeCodigo);
                        });
                    });
            });
        })
        ->orderBy('fecha_entrega', 'asc')
        ->orderBy('hora_entrega', 'asc')
        ->get();
        
        $ordenes->load('solicitudPendiente');
            
        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'en_espera' => $ordenes->where('estado', 'En espera')->count(),
            'solicitudes_pendientes' => $solicitudes->count(),
        ];

        return response()->json([
            'ordenes' => $ordenes,
            'solicitudes' => $solicitudes,
            'kpis' => $kpis,
            'recent_events' => $this->getRecentEvents()
        ]);
    }

    /**
     * Helper to clean names and generate code format.
     */
    private function cleanString($str)
    {
        $text = mb_strtoupper(trim($str), 'UTF-8');
        
        // Remove accents/tildes
        $replacements = [
            'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
            'Ú'=>'U', 'Ü'=>'U', 'Ñ'=>'N',
            'á'=>'A', 'é'=>'E', 'í'=>'I', 'ó'=>'O', 'ú'=>'U',
            'ü'=>'U', 'ñ'=>'N'
        ];
        $text = strtr($text, $replacements);
        
        // Replace spaces with hyphens
        $text = preg_replace('/\s+/', '-', $text);
        
        // Remove non-alphanumeric or hyphen
        $text = preg_replace('/[^A-Z0-9\-]/', '', $text);
        
        // Remove duplicate hyphens
        $text = preg_replace('/-+/', '-', $text);
        
        return $text;
    }

    /**
     * CRUD: Create User (Master Admin can create all roles, Sales Chiefs only sales).
     */
    public function storeUsuario(Request $request)
    {
        $userRole = session('user_role');
        
        $rules = [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'rol' => 'required|in:ventas,jefe_ventas,admin_branding,admin_promo,vista',
        ];
        
        if ($userRole === 'jefe_ventas') {
            $request->merge(['rol' => 'ventas']);
        } else {
            // Master Admin must choose a jefe if creating a sales user
            if ($request->input('rol') === 'ventas') {
                $rules['jefe_codigo'] = 'required|in:JEFERIZO-PROD-2026,JEFECAJINA-PROD-2026';
            }
        }
        
        $request->validate($rules);
        
        $rol = $request->input('rol');
        $nombre = strtoupper(trim($request->nombre));
        $apellido = strtoupper(trim($request->apellido));
        
        if ($rol === 'admin_branding') {
            $codigo = 'ADMIN-BRANDING-2026';
        } elseif ($rol === 'admin_promo') {
            $codigo = 'ADMIN-PROMO-2026';
        } elseif ($rol === 'jefe_ventas') {
            $cleanedApellido = $this->cleanString($apellido);
            $codigo = 'JEFE' . $cleanedApellido . '-PROD-2026';
        } elseif ($rol === 'vista') {
            $cleanedNombre = $this->cleanString($nombre);
            $cleanedApellido = $this->cleanString($apellido);
            $codigo = 'VISTA-' . $cleanedNombre . '-' . $cleanedApellido . '-PROD-2026';
        } else { // ventas
            $cleanedNombre = $this->cleanString($nombre);
            $cleanedApellido = $this->cleanString($apellido);
            $codigo = $cleanedNombre . '-' . $cleanedApellido . '-PROD-2026';
        }
        
        $exists = UsuarioAcceso::where('codigo', $codigo)->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'Ese código de acceso ya existe (' . $codigo . ').');
        }
        
        $jefe_codigo = null;
        if ($userRole === 'jefe_ventas') {
            $jefe_codigo = session('user_code');
        } elseif ($rol === 'ventas') {
            $jefe_codigo = $request->input('jefe_codigo');
        }
        
        UsuarioAcceso::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'rol' => $rol,
            'jefe_codigo' => $jefe_codigo,
            'activo' => true,
        ]);
        
        return redirect()->back()->with('success', 'Usuario creado correctamente. Código: ' . $codigo);
    }

    /**
     * CRUD: Edit User.
     */
    public function updateUsuario(Request $request, $id)
    {
        $userRole = session('user_role');
        
        $rules = [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
        ];
        
        if ($userRole === 'admin') {
            $rules['rol'] = 'required|in:ventas,jefe_ventas,admin_branding,admin_promo,vista';
            if ($request->input('rol') === 'ventas') {
                $rules['jefe_codigo'] = 'required|in:JEFERIZO-PROD-2026,JEFECAJINA-PROD-2026';
            }
        }
        
        $request->validate($rules);
        
        $usuario = UsuarioAcceso::findOrFail($id);
        
        // Prevent cross-jefe edit operations
        if ($userRole === 'jefe_ventas' && $usuario->jefe_codigo !== session('user_code')) {
            return redirect()->back()->with('error', 'No tiene permisos para modificar este usuario.');
        }
        
        $opCount = OrdenProduccion::where('creado_por_codigo', $usuario->codigo)->count();
        
        $usuario->nombre = strtoupper(trim($request->nombre));
        $usuario->apellido = strtoupper(trim($request->apellido));
        
        if ($userRole === 'admin') {
            $usuario->rol = $request->input('rol');
            if ($usuario->rol === 'ventas') {
                $usuario->jefe_codigo = $request->input('jefe_codigo');
            } else {
                $usuario->jefe_codigo = null;
            }
        }
        
        if ($opCount === 0) {
            $rol = $usuario->rol;
            $nombre = $usuario->nombre;
            $apellido = $usuario->apellido;
            
            if ($rol === 'admin_branding') {
                $nuevoCodigo = 'ADMIN-BRANDING-2026';
            } elseif ($rol === 'admin_promo') {
                $nuevoCodigo = 'ADMIN-PROMO-2026';
            } elseif ($rol === 'jefe_ventas') {
                $cleanedApellido = $this->cleanString($apellido);
                $nuevoCodigo = 'JEFE' . $cleanedApellido . '-PROD-2026';
            } elseif ($rol === 'vista') {
                $cleanedNombre = $this->cleanString($nombre);
                $cleanedApellido = $this->cleanString($apellido);
                $nuevoCodigo = 'VISTA-' . $cleanedNombre . '-' . $cleanedApellido . '-PROD-2026';
            } else { // ventas
                $cleanedNombre = $this->cleanString($nombre);
                $cleanedApellido = $this->cleanString($apellido);
                $nuevoCodigo = $cleanedNombre . '-' . $cleanedApellido . '-PROD-2026';
            }
            
            if ($nuevoCodigo !== $usuario->codigo) {
                $exists = UsuarioAcceso::where('codigo', $nuevoCodigo)->where('id', '!=', $id)->exists();
                if ($exists) {
                    return redirect()->back()->with('error', 'Ese código de acceso ya existe (' . $nuevoCodigo . ').');
                }
                $usuario->codigo = $nuevoCodigo;
            }
        }
        
        $usuario->save();
        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * CRUD: Activate / Deactivate Sales User.
     */
    public function toggleUsuarioVentas(Request $request, $id)
    {
        $userRole = session('user_role');
        $usuario = UsuarioAcceso::findOrFail($id);
        
        // Prevent cross-jefe toggle operations
        if ($userRole === 'jefe_ventas' && $usuario->jefe_codigo !== session('user_code')) {
            return redirect()->back()->with('error', 'No tiene permisos para modificar este usuario.');
        }

        $usuario->activo = !$usuario->activo;
        $usuario->save();

        $status = $usuario->activo ? 'activado' : 'desactivado';
        return redirect()->back()->with('success', "Usuario {$usuario->codigo} {$status} correctamente.");
    }

    /**
     * CRUD: Delete Sales User.
     */
    public function deleteUsuarioVentas(Request $request, $id)
    {
        $userRole = session('user_role');
        $usuario = UsuarioAcceso::findOrFail($id);
        
        // Prevent cross-jefe delete operations
        if ($userRole === 'jefe_ventas' && $usuario->jefe_codigo !== session('user_code')) {
            return redirect()->back()->with('error', 'No tiene permisos para modificar este usuario.');
        }

        $opCount = OrdenProduccion::where('creado_por_codigo', $usuario->codigo)->count();

        if ($opCount > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar un usuario con OP creadas.');
        }

        $usuario->delete();
        return redirect()->back()->with('success', 'Usuario de ventas eliminado correctamente.');
    }

    /**
     * Date Change Flow: Request Change.
     */
    public function solicitarCambioFecha(Request $request)
    {
        $request->validate([
            'orden_produccion_id' => 'required|exists:orden_produccions,id',
            'fecha_solicitada' => 'required|date',
            'hora_solicitada' => 'required',
            'razon_solicitud' => 'required|string|max:1000',
        ]);

        $op = OrdenProduccion::findOrFail($request->orden_produccion_id);
        
        $exists = SolicitudCambioFecha::where('orden_produccion_id', $op->id)
            ->where('estado_solicitud', 'Pendiente')
            ->exists();
            
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Ya existe una solicitud de cambio pendiente para esta orden.'], 422);
        }

        SolicitudCambioFecha::create([
            'orden_produccion_id' => $op->id,
            'fecha_actual' => $op->fecha_entrega,
            'hora_actual' => $op->hora_entrega,
            'fecha_solicitada' => $request->fecha_solicitada,
            'hora_solicitada' => $request->hora_solicitada,
            'razon_solicitud' => $request->razon_solicitud,
            'solicitado_por_codigo' => session('user_code'),
            'solicitado_por_nombre' => session('user_name'),
            'estado_solicitud' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        $fechaActStr = Carbon::parse($op->fecha_entrega)->format('d/m/Y');
        $horaActStr = Carbon::parse($op->hora_entrega)->format('H:i');
        $fechaSolStr = Carbon::parse($request->fecha_solicitada)->format('d/m/Y');
        $horaSolStr = Carbon::parse($request->hora_solicitada)->format('H:i');

        $op->historial()->create([
            'tipo_evento' => 'solicitud_cambio',
            'descripcion' => session('user_code') . " solicitó cambio de fecha de {$fechaActStr} {$horaActStr} a {$fechaSolStr} {$horaSolStr}. Razón: " . $request->razon_solicitud,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        return response()->json(['success' => true, 'message' => 'Solicitud de cambio de fecha enviada correctamente.']);
    }

    /**
     * Date Change Flow: Approve Change.
     */
    public function aprobarCambioFecha(Request $request, $id)
    {
        $solicitud = SolicitudCambioFecha::where('estado_solicitud', 'Pendiente')->findOrFail($id);
        
        if (!$this->canApproveOrRejectSolicitud($solicitud)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'No tiene permisos para aprobar esta solicitud.'], 403);
            }
            return redirect()->back()->with('error', 'No tiene permisos para aprobar esta solicitud.');
        }

        $solicitud->update([
            'estado_solicitud' => 'Aprobada',
            'aprobado_por_codigo' => session('user_code'),
            'fecha_aprobacion' => now(),
        ]);

        $op = $solicitud->ordenProduccion;
        $op->update([
            'fecha_entrega' => Carbon::parse($solicitud->fecha_solicitada)->format('Y-m-d'),
            'hora_entrega' => Carbon::parse($solicitud->hora_solicitada)->format('H:i:s'),
        ]);

        $fechaActStr = Carbon::parse($solicitud->fecha_actual)->format('d/m/Y');
        $horaActStr = Carbon::parse($solicitud->hora_actual)->format('H:i');
        $fechaSolStr = Carbon::parse($solicitud->fecha_solicitada)->format('d/m/Y');
        $horaSolStr = Carbon::parse($solicitud->hora_solicitada)->format('H:i');

        $op->historial()->create([
            'tipo_evento' => 'aprobacion_cambio',
            'descripcion' => $solicitud->solicitado_por_codigo . " solicitó cambio de fecha de {$fechaActStr} {$horaActStr} a {$fechaSolStr} {$horaSolStr}. Razón: " . $solicitud->razon_solicitud . " | " . session('user_code') . " aprobó el cambio.",
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        $this->clearDashboardCache();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Solicitud aprobada correctamente y fecha de entrega actualizada.']);
        }

        return redirect()->back()->with('success', 'Solicitud aprobada correctamente.');
    }

    /**
     * Date Change Flow: Reject Change.
     */
    public function rechazarCambioFecha(Request $request, $id)
    {
        $request->validate([
            'razon_rechazo' => 'required|string|max:1000',
        ]);

        $solicitud = SolicitudCambioFecha::where('estado_solicitud', 'Pendiente')->findOrFail($id);
        
        if (!$this->canApproveOrRejectSolicitud($solicitud)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'No tiene permisos para rechazar esta solicitud.'], 403);
            }
            return redirect()->back()->with('error', 'No tiene permisos para rechazar esta solicitud.');
        }

        $solicitud->update([
            'estado_solicitud' => 'Rechazada',
            'rechazado_por_codigo' => session('user_code'),
            'razon_rechazo' => $request->razon_rechazo,
            'fecha_rechazo' => now(),
        ]);

        $op = $solicitud->ordenProduccion;
        $fechaActStr = Carbon::parse($solicitud->fecha_actual)->format('d/m/Y');
        $horaActStr = Carbon::parse($solicitud->hora_actual)->format('H:i');
        $fechaSolStr = Carbon::parse($solicitud->fecha_solicitada)->format('d/m/Y');
        $horaSolStr = Carbon::parse($solicitud->hora_solicitada)->format('H:i');

        $op->historial()->create([
            'tipo_evento' => 'rechazo_cambio',
            'descripcion' => $solicitud->solicitado_por_codigo . " solicitó cambio de fecha de {$fechaActStr} {$horaActStr} a {$fechaSolStr} {$horaSolStr}. Razón: " . $solicitud->razon_solicitud . " | " . session('user_code') . " rechazó el cambio. Razón de rechazo: " . $request->razon_rechazo,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        $this->clearDashboardCache();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Solicitud rechazada correctamente.']);
        }

        return redirect()->back()->with('success', 'Solicitud rechazada.');
    }

    /**
     * Helper to verify if logged in user is authorized to approve/reject a solicitud.
     */
    private function canApproveOrRejectSolicitud($solicitud)
    {
        $userCode = session('user_code');
        $userRole = session('user_role');

        if ($solicitud->solicitado_por_codigo === $userCode) {
            return false;
        }

        $solicitante = UsuarioAcceso::where('codigo', $solicitud->solicitado_por_codigo)->first();
        $solicitanteRol = $solicitante ? $solicitante->rol : 'ventas';

        $op = $solicitud->ordenProduccion;
        if (!$op) {
            return false;
        }

        if ($solicitanteRol === 'jefe_ventas' || $solicitanteRol === 'ventas') {
            if ($userRole === 'admin') {
                return true;
            } elseif ($userRole === 'admin_branding' && $op->categoria === 'Branding') {
                return true;
            } elseif ($userRole === 'admin_promo' && $op->categoria === 'Promocional') {
                return true;
            }
        } elseif (in_array($solicitanteRol, ['admin', 'admin_promo', 'admin_branding'])) {
            if ($userRole === 'ventas' && $op->creado_por_codigo === $userCode) {
                return true;
            } elseif ($userRole === 'jefe_ventas') {
                $isCreatorVendorOfJefe = UsuarioAcceso::where('codigo', $op->creado_por_codigo)
                    ->where('jefe_codigo', $userCode)
                    ->exists();
                if ($op->creado_por_codigo === $userCode || $op->creado_por_codigo === 'ADMIN-PROD-2026' || $isCreatorVendorOfJefe) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Dynamic history lookup.
     */
    public function obtenerHistorial($id)
    {
        $orden = OrdenProduccion::findOrFail($id);
        $userRole = session('user_role');
        $userCode = session('user_code');

        $authorized = false;

        if ($userRole === 'admin') {
            $authorized = true;
        } elseif ($userRole === 'admin_branding') {
            if ($orden->categoria === 'Branding') {
                $authorized = true;
            }
        } elseif ($userRole === 'admin_promo') {
            if ($orden->categoria === 'Promocional') {
                $authorized = true;
            }
        } elseif ($userRole === 'jefe_ventas') {
            if ($orden->creado_por_codigo === $userCode) {
                $authorized = true;
            } else {
                $isCreatorVendorOfJefe = UsuarioAcceso::where('codigo', $orden->creado_por_codigo)
                    ->where('jefe_codigo', $userCode)
                    ->exists();
                if ($isCreatorVendorOfJefe) {
                    $authorized = true;
                }
            }
        } elseif ($userRole === 'ventas') {
            if ($orden->creado_por_codigo === $userCode) {
                $authorized = true;
            }
        } elseif ($userRole === 'vista') {
            $authorized = true;
        }

        if (!$authorized) {
            return response()->json(['error' => 'No tiene permisos para ver el historial de esta orden.'], 403);
        }

        $historial = HistorialOrden::where('orden_produccion_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($historial);
    }

    /**
     * View/download brief controller bypass route to solve 403 Forbidden.
     */
    public function descargarBrief($id)
    {
        $orden = OrdenProduccion::findOrFail($id);
        
        if (!$orden->brief) {
            abort(404, 'Esta orden no tiene un brief adjunto.');
        }
        
        $filePath = $orden->brief; // e.g., briefs/xyz.jpg
        
        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'El archivo del brief no existe físicamente en el servidor.');
        }
        
        $absolutePath = Storage::disk('public')->path($filePath);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        
        $mimeType = mime_content_type($absolutePath);
        
        $inlineExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (in_array($extension, $inlineExtensions)) {
            return response()->file($absolutePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($absolutePath) . '"'
            ]);
        } else {
            return response()->download($absolutePath, basename($absolutePath));
        }
    }

    /**
     * Export active filtered table to Excel (CSV format).
     */
    public function exportarExcel(Request $request)
    {
        $userRole = session('user_role');
        $userCode = session('user_code');
        $query = OrdenProduccion::query();

        // Role-based category/vendor filter
        if ($userRole === 'admin_branding') {
            $query->where('categoria', 'Branding');
        } elseif ($userRole === 'admin_promo') {
            $query->where('categoria', 'Promocional');
        } elseif ($userRole === 'jefe_ventas') {
            $query->where(function($q) use ($userCode) {
                $q->whereIn('creado_por_codigo', function($sub) use ($userCode) {
                    $sub->select('codigo')
                        ->from('usuarios_acceso')
                        ->where('jefe_codigo', $userCode);
                })
                ->orWhere('creado_por_codigo', $userCode);
            });
        }

        // Apply filters passed as query params
        $search = $request->query('search');
        $status = $request->query('status');
        $category = $request->query('category');

        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            $query->where('categoria', $category);
        }

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Reporte_OP_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($ordenes) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Número OP',
                'Categoría',
                'Cliente',
                'Marca',
                'Proyecto / Campaña',
                'Presupuestista',
                'Creado por',
                'Líder Producción',
                'Fecha entrega',
                'Hora entrega',
                'Estado',
                'Avance',
                'Entregar a',
                'Fecha creación',
                'Última actualización'
            ], ';');

            foreach ($ordenes as $o) {
                fputcsv($file, [
                    $o->numero_op,
                    $o->categoria,
                    $o->cliente,
                    $o->marca,
                    $o->proyecto,
                    $o->presupuestista,
                    $o->creado_por_nombre ? "{$o->creado_por_nombre} ({$o->creado_por_codigo})" : '-',
                    $o->lider_produccion ?: '-',
                    $o->fecha_entrega ? Carbon::parse($o->fecha_entrega)->format('d/m/Y') : '-',
                    $o->hora_entrega ? substr($o->hora_entrega, 0, 5) : '-',
                    $o->estado,
                    $o->avance . '%',
                    $o->entregar_a,
                    $o->created_at ? $o->created_at->format('d/m/Y H:i') : '-',
                    $o->updated_at ? $o->updated_at->format('d/m/Y H:i') : '-'
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export active filtered table to PDF using Dompdf.
     */
    public function exportarPDF(Request $request)
    {
        try {
            $userRole = session('user_role');
            $userCode = session('user_code');
            $query = OrdenProduccion::query();

            // Role-based category/vendor filter
            if ($userRole === 'admin_branding') {
                $query->where('categoria', 'Branding');
            } elseif ($userRole === 'admin_promo') {
                $query->where('categoria', 'Promocional');
            } elseif ($userRole === 'jefe_ventas') {
                $query->where(function($q) use ($userCode) {
                    $q->whereIn('creado_por_codigo', function($sub) use ($userCode) {
                        $sub->select('codigo')
                            ->from('usuarios_acceso')
                            ->where('jefe_codigo', $userCode);
                    })
                    ->orWhere('creado_por_codigo', $userCode);
                });
            }

            // Apply filters
            $search = $request->query('search');
            $status = $request->query('status');
            $category = $request->query('category');

            if ($search) {
                $query->where('numero_op', 'ilike', '%' . $search . '%');
            }

            if ($status && $status !== 'todos') {
                if ($status === 'activas') {
                    $query->whereIn('estado', ['Pendiente', 'En proceso']);
                } else {
                    $query->where('estado', $status);
                }
            }

            if ($category && $category !== 'todos') {
                $query->where('categoria', $category);
            }

            $ordenes = $query->orderBy('fecha_entrega', 'asc')
                ->orderBy('hora_entrega', 'asc')
                ->get();

            $kpis = [
                'total' => $ordenes->count(),
                'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
                'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
                'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
                'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
            ];

            $statusTextMap = [
                'activas' => 'Todas activas',
                'Pendiente' => 'Pendientes',
                'En proceso' => 'En proceso',
                'Terminado' => 'Terminadas',
                'Cancelado' => 'Canceladas',
                'todos' => 'Todas'
            ];
            $statusText = isset($statusTextMap[$status]) ? $statusTextMap[$status] : 'Todas activas';

            $filtros = [
                'search' => $search,
                'status_text' => $statusText,
                'category' => $category
            ];

            $data = [
                'ordenes' => $ordenes,
                'kpis' => $kpis,
                'filtros' => $filtros,
                'user_name' => session('user_name'),
                'user_code' => session('user_code'),
                'fecha_emision' => now()->format('d/m/Y H:i:s'),
            ];

            $html = view('pdf.reporte_pdf', $data)->render();

            $fontPath = storage_path('fonts');
            if (!file_exists($fontPath)) {
                mkdir($fontPath, 0755, true);
            }

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'sans-serif');
            $options->set('tempDir', storage_path('app'));
            $options->set('fontDir', $fontPath);
            $options->set('fontCache', $fontPath);

            try {
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('letter', 'landscape');
                $dompdf->render();
            } catch (\Exception $e) {
                // If it fails (e.g. PHP GD extension missing on Render), strip <img> tags and re-render
                $htmlSinLogo = preg_replace('/<img[^>]+>/i', '<div style="font-weight: bold; font-size: 16px; color: #00D2FF; letter-spacing: 1px;">BTL MARKETING</div>', $html);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($htmlSinLogo);
                $dompdf->setPaper('letter', 'landscape');
                $dompdf->render();
            }

            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Reporte_OP_' . date('Ymd_His') . '.pdf"');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al exportar PDF general: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export individual OP detailed sheet in PDF using Dompdf.
     */
    public function exportarDetallePDF($id)
    {
        try {
            $orden = OrdenProduccion::with('historial')->findOrFail($id);
            $userRole = session('user_role');
            $userCode = session('user_code');

            // Verify access to this specific OP
            if ($userRole === 'admin_branding' && $orden->categoria !== 'Branding') {
                abort(403, 'No tiene permisos para exportar esta orden.');
            }
            if ($userRole === 'admin_promo' && $orden->categoria !== 'Promocional') {
                abort(403, 'No tiene permisos para exportar esta orden.');
            }
            if ($userRole === 'jefe_ventas') {
                $creadoPor = $orden->creado_por_codigo;
                $isCreatorVendorOfJefe = UsuarioAcceso::where('codigo', $creadoPor)
                    ->where('jefe_codigo', $userCode)
                    ->exists();
                if ($creadoPor !== $userCode && !$isCreatorVendorOfJefe) {
                    abort(403, 'No tiene permisos para exportar esta orden.');
                }
            }
            if ($userRole === 'ventas' && $orden->creado_por_codigo !== $userCode) {
                abort(403, 'No tiene permisos para exportar esta orden.');
            }

            $data = [
                'orden' => $orden,
                'user_name' => session('user_name'),
                'user_code' => session('user_code'),
                'fecha_emision' => now()->format('d/m/Y H:i:s'),
            ];

            $html = view('pdf.detalle_pdf', $data)->render();

            $fontPath = storage_path('fonts');
            if (!file_exists($fontPath)) {
                mkdir($fontPath, 0755, true);
            }

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'sans-serif');
            $options->set('tempDir', storage_path('app'));
            $options->set('fontDir', $fontPath);
            $options->set('fontCache', $fontPath);

            try {
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
            } catch (\Exception $e) {
                // If it fails (e.g. PHP GD extension missing on Render), strip <img> tags and re-render
                $htmlSinLogo = preg_replace('/<img[^>]+>/i', '<div style="font-weight: bold; font-size: 16px; color: #00D2FF; letter-spacing: 1px;">BTL MARKETING</div>', $html);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($htmlSinLogo);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
            }

            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Ficha_OP_' . $orden->numero_op . '.pdf"');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al exportar PDF detalle (ID ' . $id . '): ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response('Error al generar el PDF de detalle: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent events for real-time notifications, filtered by role permissions.
     */
    private function getRecentEvents()
    {
        $userRole = session('user_role');
        $userCode = session('user_code');
        
        $query = HistorialOrden::with('ordenProduccion')
            ->where('created_at', '>=', now()->subSeconds(30));
            
        if ($userCode) {
            $query->where('realizado_por_codigo', '!=', $userCode);
        }
        
        if ($userRole === 'admin_branding') {
            $query->whereHas('ordenProduccion', function($q) {
                $q->where('categoria', 'Branding');
            });
        } elseif ($userRole === 'admin_promo') {
            $query->whereHas('ordenProduccion', function($q) {
                $q->where('categoria', 'Promocional');
            });
        } elseif ($userRole === 'jefe_ventas') {
            $query->whereHas('ordenProduccion', function($q) use ($userCode) {
                $q->where(function($sub) use ($userCode) {
                    $sub->whereIn('creado_por_codigo', function($uQuery) use ($userCode) {
                        $uQuery->select('codigo')
                            ->from('usuarios_acceso')
                            ->where('jefe_codigo', $userCode);
                    })
                    ->orWhere('creado_por_codigo', $userCode);
                });
            });
        } elseif ($userRole === 'ventas') {
            $query->whereHas('ordenProduccion', function($q) use ($userCode) {
                $q->where('creado_por_codigo', $userCode);
            });
        }
        
        return $query->orderBy('created_at', 'asc')->get()->map(function($event) {
            return [
                'id' => $event->id,
                'orden_produccion_id' => $event->orden_produccion_id,
                'numero_op' => $event->ordenProduccion->numero_op ?? 'OP',
                'proyecto' => $event->ordenProduccion->proyecto ?? '',
                'tipo_evento' => $event->tipo_evento,
                'descripcion' => $event->descripcion,
                'created_at' => $event->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get recent events for TV view, filtered by category.
     */
    private function getRecentEventsForTV($categoria)
    {
        return HistorialOrden::with('ordenProduccion')
            ->where('created_at', '>=', now()->subSeconds(30))
            ->whereHas('ordenProduccion', function($q) use ($categoria) {
                $q->where('categoria', $categoria);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($event) {
                return [
                    'id' => $event->id,
                    'orden_produccion_id' => $event->orden_produccion_id,
                    'numero_op' => $event->ordenProduccion->numero_op ?? 'OP',
                    'proyecto' => $event->ordenProduccion->proyecto ?? '',
                    'tipo_evento' => $event->tipo_evento,
                    'descripcion' => $event->descripcion,
                    'created_at' => $event->created_at->toIso8601String(),
                ];
            });
    }

    /**
     * Display the Vista panel (solo lectura).
     */
    public function vistaPanel()
    {
        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])
            ->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'en_espera' => $ordenes->where('estado', 'En espera')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
        ];

        return view('op.vista', compact('ordenes', 'kpis'));
    }

    /**
     * Get updates for the Vista panel.
     */
    public function vistaUpdates()
    {
        $ordenes = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso'])
            ->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->get();

        $ordenes->each(function ($o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        });

        $kpis = [
            'total' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', 'Pendiente')->count(),
            'en_proceso' => $ordenes->where('estado', 'En proceso')->count(),
            'en_espera' => $ordenes->where('estado', 'En espera')->count(),
            'terminadas' => $ordenes->where('estado', 'Terminado')->count(),
            'urgentes' => $ordenes->filter(fn($o) => $o->prioridad === 'URGENTE')->count(),
        ];

        return response()->json([
            'ordenes' => $ordenes,
            'kpis' => $kpis
        ]);
    }

    /**
     * Download a specific file by its ID from the files table.
     */
    public function descargarArchivo($id)
    {
        $archivo = OrdenProduccionArchivo::findOrFail($id);
        $filePath = $archivo->file_path;
        
        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'El archivo no existe físicamente en el servidor.');
        }
        
        $absolutePath = Storage::disk('public')->path($filePath);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($absolutePath);
        
        $inlineExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        
        if (in_array($extension, $inlineExtensions)) {
            return response()->file($absolutePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $archivo->file_name . '"'
            ]);
        } else {
            return response()->download($absolutePath, $archivo->file_name);
        }
    }

    /**
     * Submit a reproceso request for a finished order.
     */
    public function solicitarReproceso(Request $request, $id)
    {
        $orden = OrdenProduccion::findOrFail($id);
        
        if ($orden->estado !== 'Terminado') {
            return response()->json(['success' => false, 'message' => 'Solo se puede solicitar reproceso para órdenes terminadas.'], 400);
        }

        // Check if there is already a pending request or an approved reproceso
        $existsPending = SolicitudReproceso::where('orden_produccion_id', $id)
            ->where('estado', 'Pendiente')
            ->exists();
        if ($existsPending) {
            return response()->json(['success' => false, 'message' => 'Ya existe una solicitud de reproceso pendiente para esta orden.'], 400);
        }

        $existsApproved = OrdenProduccion::where('reproceso_de_id', $id)->exists();
        if ($existsApproved) {
            return response()->json(['success' => false, 'message' => 'Esta orden ya cuenta con un reproceso activo.'], 400);
        }

        $request->validate([
            'motivo' => 'required|string|in:Error de diseño,Error de producción,Daño en transporte,Solicitud del cliente,Otro',
            'descripcion' => 'required|string|max:2000',
            'fecha_requerida' => 'nullable|date',
            'archivo' => 'nullable|file|max:102400',
        ]);

        $filePath = null;
        if ($request->hasFile('archivo')) {
            $filePath = $request->file('archivo')->store('reprocesos_adjuntos', 'public');
        }

        SolicitudReproceso::create([
            'orden_produccion_id' => $id,
            'motivo' => $request->motivo,
            'descripcion' => $request->descripcion,
            'fecha_requerida' => $request->fecha_requerida,
            'archivo_adjunto' => $filePath,
            'estado' => 'Pendiente',
            'solicitado_por_codigo' => session('user_code'),
            'solicitado_por_nombre' => session('user_name'),
        ]);

        // Log to history
        $orden->historial()->create([
            'tipo_evento' => 'solicitud_reproceso',
            'descripcion' => 'Solicitud de reproceso creada por ' . session('user_name') . ' (' . session('user_code') . '). Motivo: ' . $request->motivo . '. Descripción: ' . $request->descripcion,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        $this->clearDashboardCache();

        return response()->json(['success' => true, 'message' => 'Solicitud de reproceso enviada correctamente.']);
    }

    /**
     * Approve a reproceso request and create the new reproceso OP.
     */
    public function aprobarReproceso(Request $request, $id)
    {
        $solicitud = SolicitudReproceso::findOrFail($id);
        
        if ($solicitud->estado !== 'Pendiente') {
            return response()->json(['success' => false, 'message' => 'Esta solicitud ya fue procesada.'], 400);
        }

        $request->validate([
            'numero_op' => 'required|string|max:100|unique:orden_produccions,numero_op',
            'lider_produccion' => 'nullable|string|max:255',
            'estado' => 'required|in:Pendiente,En proceso,En espera',
        ]);

        $original = $solicitud->ordenProduccion;

        // Check if there is already an active reproceso
        $existsApproved = OrdenProduccion::where('reproceso_de_id', $original->id)->exists();
        if ($existsApproved) {
            $solicitud->update(['estado' => 'Rechazado']);
            return response()->json(['success' => false, 'message' => 'Esta orden ya cuenta con un reproceso activo.'], 400);
        }

        // Create the new reproceso OP
        $reproceso = OrdenProduccion::create([
            'categoria' => 'Reprocesos',
            'numero_op' => $request->numero_op,
            'proyecto' => $original->proyecto . ' (Reproceso)',
            'presupuestista' => $original->presupuestista,
            'cliente' => $original->cliente,
            'marca' => $original->marca,
            'fecha_entrega' => now()->addDays(2)->format('Y-m-d'), // Default: 2 days from now
            'hora_entrega' => '18:00:00',
            'entregar_a' => $original->entregar_a,
            'lugar_instalacion' => $original->lugar_instalacion,
            'fecha_instalacion' => $original->fecha_instalacion,
            'hora_instalacion' => $original->hora_instalacion,
            'fecha_desinstalacion' => $original->fecha_desinstalacion,
            'hora_desinstalacion' => $original->hora_desinstalacion,
            'brief' => $original->brief,
            'lider_produccion' => $request->lider_produccion,
            'estado' => $request->estado,
            'avance' => 0,
            'creado_por_codigo' => session('user_code'),
            'creado_por_nombre' => session('user_name'),
            'creado_por_rol' => session('user_role'),
            'reproceso_de_id' => $original->id,
            'parent_op_id' => $original->id,
        ]);

        $this->clearDashboardCache();

        // Copy files/archivos associated with original OP to the new one
        if ($original->archivos()->exists()) {
            foreach ($original->archivos as $archivo) {
                $reproceso->archivos()->create([
                    'file_path' => $archivo->file_path,
                    'file_name' => $archivo->file_name,
                ]);
            }
        }

        $solicitud->update([
            'estado' => 'Aprobado',
            'reproceso_id' => $reproceso->id,
        ]);

        // Log to original OP history
        $original->historial()->create([
            'tipo_evento' => 'aprobacion_reproceso',
            'descripcion' => 'Solicitud de reproceso APROBADA por ' . session('user_name') . '. Se creó la OP: ' . $reproceso->numero_op,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        // Log to new OP history
        $reproceso->historial()->create([
            'tipo_evento' => 'creacion',
            'descripcion' => 'OP de Reproceso creada automáticamente al aprobar solicitud para OP: ' . $original->numero_op,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        return response()->json(['success' => true, 'message' => 'Solicitud de reproceso aprobada y nueva OP creada con éxito.']);
    }

    /**
     * Reject a reproceso request.
     */
    public function rechazarReproceso(Request $request, $id)
    {
        $solicitud = SolicitudReproceso::findOrFail($id);
        
        if ($solicitud->estado !== 'Pendiente') {
            return response()->json(['success' => false, 'message' => 'Esta solicitud ya fue procesada.'], 400);
        }

        $request->validate([
            'razon_rechazo' => 'required|string|max:1000',
        ]);

        $solicitud->update([
            'estado' => 'Rechazado',
            'razon_rechazo' => $request->razon_rechazo,
        ]);

        $original = $solicitud->ordenProduccion;

        // Log to original OP history
        $original->historial()->create([
            'tipo_evento' => 'rechazo_reproceso',
            'descripcion' => 'Solicitud de reproceso RECHAZADA por ' . session('user_name') . ' (' . session('user_code') . '). Razón: ' . $request->razon_rechazo,
            'realizado_por_codigo' => session('user_code'),
            'realizado_por_nombre' => session('user_name'),
            'realizado_por_rol' => session('user_role'),
        ]);

        $this->clearDashboardCache();

        return response()->json(['success' => true, 'message' => 'Solicitud de reproceso rechazada.']);
    }

    public function eliminarOP(Request $request, $id)
    {
        $op = OrdenProduccion::findOrFail($id);
        $userRole = session('user_role');
        $userCode = session('user_code');

        // Only allow deletion if status is Terminado or Cancelado
        if (!in_array($op->estado, ['Terminado', 'Cancelado'])) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Solo se pueden eliminar órdenes con estado Terminado o Cancelado.'], 400);
            }
            return redirect()->route('op.admin')->with('error', 'Solo se pueden eliminar órdenes con estado Terminado o Cancelado.');
        }

        // Check permission
        $hasPermission = false;
        if ($userRole === 'admin') {
            $hasPermission = true;
        } elseif ($userRole === 'admin_branding') {
            if ($op->categoria === 'Branding') {
                $hasPermission = true;
            } elseif (in_array($op->categoria, ['Reprocesos', 'Reproceso', 'REPROCESO']) && $op->original && $op->original->categoria === 'Branding') {
                $hasPermission = true;
            }
        } elseif ($userRole === 'admin_promo') {
            if ($op->categoria === 'Promocional') {
                $hasPermission = true;
            } elseif (in_array($op->categoria, ['Reprocesos', 'Reproceso', 'REPROCESO']) && $op->original && $op->original->categoria === 'Promocional') {
                $hasPermission = true;
            }
        }

        if (!$hasPermission) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'No tiene permisos para eliminar esta OP.'], 403);
            }
            return redirect()->route('op.admin')->with('error', 'No tiene permisos para eliminar esta OP.');
        }

        // Check if hard delete was requested
        $hardDelete = $request->input('hard_delete') === 'true' || $request->input('hard_delete') === true;

        if ($hardDelete) {
            // Only super admin (role 'admin') can hard delete
            if ($userRole !== 'admin') {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Solo el Administrador General/Super Admin puede eliminar permanentemente.'], 403);
                }
                return redirect()->route('op.admin')->with('error', 'Solo el Administrador General/Super Admin puede eliminar permanentemente.');
            }
            
            // Hard delete
            // First delete related records to avoid foreign key errors in databases like Postgres/Supabase
            $op->archivos()->delete();
            $op->solicitudesCambio()->delete();
            $op->solicitudesReproceso()->delete();
            $op->historial()->delete();
            // Clear relationships
            OrdenProduccion::where('reproceso_de_id', $op->id)->update(['reproceso_de_id' => null, 'parent_op_id' => null]);
            $op->forceDelete();
            
            $this->clearDashboardCache();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'OP eliminada permanentemente con éxito.']);
            }
            return redirect()->route('op.admin')->with('success', 'OP eliminada permanentemente con éxito.');
        } else {
            // Soft delete
            // Create history record before soft deleting so it's tracked
            $op->historial()->create([
                'tipo_evento' => 'eliminacion_soft',
                'descripcion' => 'OP eliminada (Soft Delete) por ' . session('user_name') . ' (' . session('user_code') . ')',
                'realizado_por_codigo' => $userCode,
                'realizado_por_nombre' => session('user_name'),
                'realizado_por_rol' => $userRole,
            ]);

            $op->delete();
            
            $this->clearDashboardCache();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'OP eliminada correctamente (Soft Delete).']);
            }
            return redirect()->route('op.admin')->with('success', 'OP eliminada correctamente (Soft Delete).');
        }
    }

    private function clearDashboardCache()
    {
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats_admin');
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats_admin_branding');
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats_admin_promo');
    }
}
