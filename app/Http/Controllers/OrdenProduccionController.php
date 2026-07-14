<?php

namespace App\Http\Controllers;

use App\Models\OrdenProduccion;
use App\Models\UsuarioAcceso;
use App\Models\SolicitudCambioFecha;
use App\Models\HistorialOrden;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use App\Services\AdjuntoStorageService;
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
            'brief.*' => 'file|max:51200|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,zip',
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
            'brief.*.max' => 'El archivo no debe pesar más de 50MB.',
            'brief.*.mimes' => 'El formato del archivo no está permitido. Formatos válidos: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX, TXT y ZIP.',
        ]);

        // Manual extension validation to avoid mime type detection errors
        if ($request->hasFile('brief')) {
            $files = $request->file('brief');
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
            
            foreach ($files as $file) {
                if ($file) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, $allowedExtensions)) {
                        return redirect()->back()
                            ->withInput()
                            ->withErrors(['brief' => 'El archivo no es válido. Formatos permitidos: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX, TXT y ZIP.']);
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

        $uploadedPaths = [];
        DB::beginTransaction();
        try {
            // Create the order. 'avance' and 'estado' are set via defaults & events.
            $orden = OrdenProduccion::create($validated);

            // Handle multiple file uploads
            if ($briefFiles) {
                $firstPath = null;
                $filesArray = is_array($briefFiles) ? $briefFiles : [$briefFiles];
                foreach ($filesArray as $index => $file) {
                    if ($file) {
                        $uploadResult = AdjuntoStorageService::uploadFile($file, $orden->id);
                        $uploadedPaths[] = $uploadResult['path'];
                        if ($index === 0) {
                            $firstPath = $uploadResult['path'];
                        }
                        $orden->archivos()->create([
                            'file_path'   => $uploadResult['path'],
                            'file_name'   => $uploadResult['name'],
                            'file_size'   => $uploadResult['size'],
                            'mime_type'   => $uploadResult['mime_type'],
                            'uploaded_by' => session('user_code') ?: 'SISTEMA',
                        ]);
                    }
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

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up uploaded files from Storage since DB failed
            foreach ($uploadedPaths as $path) {
                try {
                    AdjuntoStorageService::deleteFile($path);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error("Failed to delete orphaned file after DB failure: " . $path);
                }
            }
            throw $e;
        }

        $this->clearDashboardCache();

        return redirect('/op/nueva')->with('success', 'Orden de Producción creada correctamente.');
    }

    /**
     * Display the admin production control panel.
     */
    public function admin(Request $request)
    {
        $userRole = session('user_role');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['solicitudPendiente', 'archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent']);

        // Role-based category restriction
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
            if ($category === 'todos') {
            } elseif ($category === 'Branding') {
                $query->where('categoria', 'Branding');
            } elseif ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('id', 0);
            }
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
            if ($category === 'todos') {
            } elseif ($category === 'Promocional') {
                $query->where('categoria', 'Promocional');
            } elseif ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('id', 0);
            }
        } else {
            if ($category && $category !== 'todos') {
                if ($category === 'Reprocesos') {
                    $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
                } else {
                    $query->where('categoria', $category);
                }
            }
        }

        // Apply filters
        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->withQueryString();

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

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $urgentesCount = $kpiQuery->clone()
            ->whereNotIn('estado', ['Terminado', 'Cancelado'])
            ->where('fecha_entrega', '<=', now()->format('Y-m-d'))
            ->count();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
            'urgentes' => $urgentesCount,
            'solicitudes_pendientes' => $solicitudes->count() + $solicitudesReproceso->count(),
        ];

        // Fetch all users for Master Admin user management panel
        $usuarios = [];
        if ($userRole === 'admin') {
            $usuarios = UsuarioAcceso::withCount('ordenes')
                ->orderBy('rol', 'asc')
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($u) {
                    $u->op_count = $u->ordenes_count;
                    return $u;
                });
        }

        // Detectar petición AJAX de filtros — responder solo el partial + datos JSON
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $html = view('op.partials.listado-op', compact('ordenes'))->render();
            return response()->json([
                'html'        => $html,
                'kpis'        => $kpis,
                'ordenes'     => $ordenes->items(),
                'currentPage' => $ordenes->currentPage(),
                'lastPage'    => $ordenes->lastPage(),
            ]);
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
    public function adminUpdates(Request $request)
    {
        $userRole = session('user_role');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['solicitudPendiente', 'archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent']);

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
            if ($category === 'todos') {
            } elseif ($category === 'Branding') {
                $query->where('categoria', 'Branding');
            } elseif ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('id', 0);
            }
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
            if ($category === 'todos') {
            } elseif ($category === 'Promocional') {
                $query->where('categoria', 'Promocional');
            } elseif ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('id', 0);
            }
        } else {
            if ($category && $category !== 'todos') {
                if ($category === 'Reprocesos') {
                    $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
                } else {
                    $query->where('categoria', $category);
                }
            }
        }

        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->items();

        foreach ($ordenes as $o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        }

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

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $urgentesCount = $kpiQuery->clone()
            ->whereNotIn('estado', ['Terminado', 'Cancelado'])
            ->where('fecha_entrega', '<=', now()->format('Y-m-d'))
            ->count();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
            'urgentes' => $urgentesCount,
            'solicitudes_pendientes' => $solicitudes->count() + $solicitudesReproceso->count(),
        ];

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
    public function misOrdenes(Request $request)
    {
        $vendedorCodigo = session('user_code');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent', 'solicitudPendiente'])
            ->where(function($q) use ($vendedorCodigo) {
                $q->where('creado_por_codigo', $vendedorCodigo)
                  ->orWhere(function($sub) use ($vendedorCodigo) {
                      $sub->where('categoria', 'Reprocesos')
                          ->whereHas('original', function($o) use ($vendedorCodigo) {
                              $o->where('creado_por_codigo', $vendedorCodigo);
                          });
                  });
            });

        // Apply filters
        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();
        $statusCounts = $kpiQuery->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();
        $totalCount = array_sum($statusCounts);
        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
        ];

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->withQueryString();

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        return view('op.mis_ordenes', compact('ordenes', 'solicitudes', 'kpis'));
    }

    /**
     * Polling updates for Vendedor Panel.
     */
    public function misOrdenesUpdates(Request $request)
    {
        $vendedorCodigo = session('user_code');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent', 'solicitudPendiente'])
            ->where(function($q) use ($vendedorCodigo) {
                $q->where('creado_por_codigo', $vendedorCodigo)
                  ->orWhere(function($sub) use ($vendedorCodigo) {
                      $sub->where('categoria', 'Reprocesos')
                          ->whereHas('original', function($o) use ($vendedorCodigo) {
                              $o->where('creado_por_codigo', $vendedorCodigo);
                          });
                  });
            });

        // Apply filters
        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();
        $statusCounts = $kpiQuery->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();
        $totalCount = array_sum($statusCounts);
        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
        ];

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->items();
        
        foreach ($ordenes as $o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        }

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        return response()->json([
            'ordenes' => $ordenes,
            'solicitudes' => $solicitudes,
            'kpis' => $kpis,
            'recent_events' => $this->getRecentEvents()
        ]);
    }

    /**
     * Display Jefe de Ventas Panel.
     */
    public function jefeVentasPanel(Request $request)
    {
        $jefeCodigo = session('user_code');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        // Filter OPs: created by vendors belonging to this jefe or by the jefe itself
        $vendedoresCodigos = UsuarioAcceso::where('jefe_codigo', $jefeCodigo)->pluck('codigo')->toArray();
        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent', 'solicitudPendiente'])->where(function ($query) use ($jefeCodigo, $vendedoresCodigos) {
            $query->where(function ($q) use ($jefeCodigo, $vendedoresCodigos) {
                $q->whereIn('creado_por_codigo', $vendedoresCodigos)
                  ->orWhere('creado_por_codigo', $jefeCodigo);
            })
            ->orWhere(function ($sub) use ($jefeCodigo, $vendedoresCodigos) {
                $sub->where('categoria', 'Reprocesos')
                    ->whereHas('original', function ($orig) use ($jefeCodigo, $vendedoresCodigos) {
                        $orig->where(function ($q) use ($jefeCodigo, $vendedoresCodigos) {
                            $q->whereIn('creado_por_codigo', $vendedoresCodigos)
                              ->orWhere('creado_por_codigo', $jefeCodigo);
                        });
                    });
            });
        });

        // Apply filters
        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->withQueryString();

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
            'solicitudes_pendientes' => $solicitudes->count(),
        ];

        // Filter vendors belonging to this jefe
        $usuarios = UsuarioAcceso::where('rol', 'ventas')
            ->where('jefe_codigo', $jefeCodigo)
            ->withCount('ordenes')
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(function ($u) {
                $u->op_count = $u->ordenes_count;
                return $u;
            });

        return view('op.jefe_ventas', compact('ordenes', 'kpis', 'solicitudes', 'usuarios'));
    }

    /**
     * Polling updates for Jefe de Ventas Panel.
     */
    public function jefeVentasUpdates(Request $request)
    {
        $jefeCodigo = session('user_code');
        
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $vendedoresCodigos = UsuarioAcceso::where('jefe_codigo', $jefeCodigo)->pluck('codigo')->toArray();
        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent', 'solicitudPendiente'])->where(function ($query) use ($jefeCodigo, $vendedoresCodigos) {
            $query->where(function ($q) use ($jefeCodigo, $vendedoresCodigos) {
                $q->whereIn('creado_por_codigo', $vendedoresCodigos)
                  ->orWhere('creado_por_codigo', $jefeCodigo);
            })
            ->orWhere(function ($sub) use ($jefeCodigo, $vendedoresCodigos) {
                $sub->where('categoria', 'Reprocesos')
                    ->whereHas('original', function ($orig) use ($jefeCodigo, $vendedoresCodigos) {
                        $orig->where(function ($q) use ($jefeCodigo, $vendedoresCodigos) {
                            $q->whereIn('creado_por_codigo', $vendedoresCodigos)
                              ->orWhere('creado_por_codigo', $jefeCodigo);
                        });
                    });
            });
        });

        // Apply filters
        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->items();
        
        foreach ($ordenes as $o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        }

        $solicitudes = SolicitudCambioFecha::with('ordenProduccion')
            ->where('estado_solicitud', 'Pendiente')
            ->get()
            ->filter(fn($sol) => $this->canApproveOrRejectSolicitud($sol))
            ->values();

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
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

        static $usuariosCache = null;
        if ($usuariosCache === null || app()->runningUnitTests()) {
            $usuariosCache = UsuarioAcceso::all()->keyBy('codigo')->all();
        }

        $solicitante = $usuariosCache[$solicitud->solicitado_por_codigo] ?? null;
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
                $creator = $usuariosCache[$op->creado_por_codigo] ?? null;
                $isCreatorVendorOfJefe = $creator && $creator->jefe_codigo === $userCode;
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

        if (!$this->checkUserAuthorization($orden)) {
            abort(403, 'No tienes permiso para descargar este archivo.');
        }

        if (!$orden->brief) {
            abort(404, 'Esta orden no tiene un brief adjunto.');
        }

        $filePath = $orden->brief;

        $archivo = \App\Models\OrdenProduccionArchivo::where('orden_produccion_id', $orden->id)
            ->where('file_path', $filePath)
            ->first();

        if ($archivo && $archivo->is_missing) {
            abort(404, 'Archivo no disponible');
        }

        if (!AdjuntoStorageService::exists($filePath)) {
            abort(404, 'El archivo solicitado no está disponible.');
        }

        $stream = AdjuntoStorageService::getStream($filePath);
        if (!$stream) {
            abort(404, 'El archivo no se pudo leer.');
        }

        $fileName = $archivo ? $archivo->file_name : basename($filePath);
        $mimeType = $archivo ? $archivo->mime_type : $this->getMimeTypeByExtension(pathinfo($filePath, PATHINFO_EXTENSION));

        $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']) ? 'inline' : 'attachment';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
        ]);
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
            $vendedoresCodigos = UsuarioAcceso::where('jefe_codigo', $userCode)->pluck('codigo')->toArray();
            $query->where(function($q) use ($userCode, $vendedoresCodigos) {
                $q->whereIn('creado_por_codigo', $vendedoresCodigos)
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
                $vendedoresCodigos = UsuarioAcceso::where('jefe_codigo', $userCode)->pluck('codigo')->toArray();
                $query->where(function($q) use ($userCode, $vendedoresCodigos) {
                    $q->whereIn('creado_por_codigo', $vendedoresCodigos)
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
            $vendedoresCodigos = UsuarioAcceso::where('jefe_codigo', $userCode)->pluck('codigo')->toArray();
            $query->whereHas('ordenProduccion', function($q) use ($userCode, $vendedoresCodigos) {
                $q->where(function($sub) use ($userCode, $vendedoresCodigos) {
                    $sub->whereIn('creado_por_codigo', $vendedoresCodigos)
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
    /**
     * Display the Vista panel (solo lectura).
     */
    public function vistaPanel(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent']);

        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->withQueryString();

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $urgentesCount = $kpiQuery->clone()
            ->whereNotIn('estado', ['Terminado', 'Cancelado'])
            ->where('fecha_entrega', '<=', now()->format('Y-m-d'))
            ->count();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'urgentes' => $urgentesCount,
        ];

        return view('op.vista', compact('ordenes', 'kpis'));
    }

    /**
     * Get updates for the Vista panel.
     */
    public function vistaUpdates(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'activas');
        $category = $request->input('category', 'todos');

        $query = OrdenProduccion::with(['archivos', 'reprocesos', 'solicitudesReproceso', 'original', 'parent']);

        if ($search) {
            $query->where('numero_op', 'ilike', '%' . $search . '%');
        }

        if ($status && $status !== 'todos') {
            if ($status === 'activas') {
                $query->whereIn('estado', ['Pendiente', 'En proceso', 'En espera']);
            } else {
                $query->where('estado', $status);
            }
        }

        if ($category && $category !== 'todos') {
            if ($category === 'Reprocesos') {
                $query->whereIn('categoria', ['Reprocesos', 'REPROCESO', 'reproceso']);
            } else {
                $query->where('categoria', $category);
            }
        }

        $kpiQuery = $query->clone();

        $ordenes = $query->orderBy('fecha_entrega', 'asc')
            ->orderBy('hora_entrega', 'asc')
            ->paginate(20)
            ->items();

        foreach ($ordenes as $o) {
            $o->append(['dias_restantes', 'prioridad', 'mostrar_fuego']);
        }

        $statusCounts = $kpiQuery->clone()
            ->select('estado', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $urgentesCount = $kpiQuery->clone()
            ->whereNotIn('estado', ['Terminado', 'Cancelado'])
            ->where('fecha_entrega', '<=', now()->format('Y-m-d'))
            ->count();

        $totalCount = array_sum($statusCounts);

        $kpis = [
            'total' => $totalCount,
            'pendientes' => $statusCounts['Pendiente'] ?? 0,
            'en_proceso' => $statusCounts['En proceso'] ?? 0,
            'en_espera' => $statusCounts['En espera'] ?? 0,
            'terminadas' => $statusCounts['Terminado'] ?? 0,
            'urgentes' => $urgentesCount,
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
        $orden = $archivo->ordenProduccion;

        if (!$orden || !$this->checkUserAuthorization($orden)) {
            abort(403, 'No tienes permiso para descargar este archivo.');
        }

        if ($archivo->is_missing) {
            abort(404, 'Archivo no disponible');
        }

        $filePath = $archivo->file_path;

        if (!AdjuntoStorageService::exists($filePath)) {
            abort(404, 'El archivo solicitado no está disponible.');
        }

        $stream = AdjuntoStorageService::getStream($filePath);
        if (!$stream) {
            abort(404, 'El archivo no se pudo leer.');
        }

        $fileName = $archivo->file_name;
        $mimeType = $archivo->mime_type ?? $this->getMimeTypeByExtension(pathinfo($filePath, PATHINFO_EXTENSION));

        $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']) ? 'inline' : 'attachment';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Download/Stream a specific reproceso attachment by its ID.
     */
    public function descargarReproceso($id)
    {
        $reproceso = SolicitudReproceso::findOrFail($id);
        $orden = $reproceso->ordenProduccion;

        if (!$orden || !$this->checkUserAuthorization($orden)) {
            abort(403, 'No tienes permiso para descargar este archivo.');
        }

        $filePath = $reproceso->archivo_adjunto;
        if (empty($filePath)) {
            abort(404, 'No hay archivo adjunto en esta solicitud.');
        }

        if (!AdjuntoStorageService::exists($filePath)) {
            abort(404, 'El archivo solicitado no está disponible.');
        }

        $stream = AdjuntoStorageService::getStream($filePath);
        if (!$stream) {
            abort(404, 'El archivo no se pudo leer.');
        }

        $fileName = basename($filePath);
        $mimeType = $reproceso->archivo_mime_type ?? $this->getMimeTypeByExtension(pathinfo($filePath, PATHINFO_EXTENSION));

        $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']) ? 'inline' : 'attachment';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
        ]);
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
            'archivo' => 'nullable|file|max:51200|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,zip',
        ], [
            'archivo.max' => 'El archivo no debe pesar más de 50MB.',
            'archivo.mimes' => 'El formato del archivo no está permitido. Formatos válidos: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX, TXT y ZIP.',
        ]);

        $uploadedPaths = [];
        DB::beginTransaction();
        try {
            $filePath = null;
            $fileSize = null;
            $fileMime = null;
            $uploadedBy = null;

            if ($request->hasFile('archivo')) {
                $uploadResult = AdjuntoStorageService::uploadFile($request->file('archivo'), $orden->id);
                $filePath = $uploadResult['path'];
                $uploadedPaths[] = $filePath;
                $fileSize = $uploadResult['size'];
                $fileMime = $uploadResult['mime_type'];
                $uploadedBy = session('user_code') ?: 'SISTEMA';
            }

            SolicitudReproceso::create([
                'orden_produccion_id' => $id,
                'motivo' => $request->motivo,
                'descripcion' => $request->descripcion,
                'fecha_requerida' => $request->fecha_requerida,
                'archivo_adjunto' => $filePath,
                'archivo_size' => $fileSize,
                'archivo_mime_type' => $fileMime,
                'archivo_uploaded_by' => $uploadedBy,
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

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up uploaded file if DB failed
            foreach ($uploadedPaths as $path) {
                try {
                    AdjuntoStorageService::deleteFile($path);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error("Failed to delete orphaned file after DB failure: " . $path);
                }
            }
            throw $e;
        }

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
            // First delete physical files associated with the OP
            foreach ($op->archivos as $archivo) {
                try {
                    AdjuntoStorageService::deleteFile($archivo->file_path);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error("Failed to delete physical file during hard delete: " . $archivo->file_path);
                }
            }

            // Also check brief column if it is stored elsewhere and not in archivos relation
            if ($op->brief) {
                $isLinked = false;
                foreach ($op->archivos as $archivo) {
                    if ($archivo->file_path === $op->brief) {
                        $isLinked = true;
                        break;
                    }
                }
                if (!$isLinked) {
                    try {
                        AdjuntoStorageService::deleteFile($op->brief);
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::error("Failed to delete brief physical file during hard delete: " . $op->brief);
                    }
                }
            }

            // Also check reproceso files
            foreach ($op->solicitudesReproceso as $reproceso) {
                if ($reproceso->archivo_adjunto) {
                    try {
                        AdjuntoStorageService::deleteFile($reproceso->archivo_adjunto);
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::error("Failed to delete reproceso physical file during hard delete: " . $reproceso->archivo_adjunto);
                    }
                }
            }

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

    /**
     * Return full OP data (with files) for the Edit OP modal (AJAX GET).
     */
    public function editarOP(Request $request, $id)
    {
        $userRole = session('user_role');
        $userCode = session('user_code');

        $isAdmin = in_array($userRole, ['admin', 'admin_branding', 'admin_promo']);
        $isSales = in_array($userRole, ['ventas', 'jefe_ventas']);

        // Secondary role check
        if (!$isAdmin && !$isSales) {
            return response()->json(['error' => 'No tiene permisos para editar órdenes de producción.'], 403);
        }

        $orden = OrdenProduccion::with('archivos')->findOrFail($id);

        // Vendedor/Jefe: can only edit their own OPs
        if ($isSales && $orden->creado_por_codigo !== $userCode) {
            return response()->json([
                'error'    => 'No tienes permiso para editar esta Orden de Producción.',
                'bloqueado' => true,
            ], 403);
        }

        // Admin category-based access restriction
        if ($userRole === 'admin_branding' && !in_array($orden->categoria, ['Branding', 'Reprocesos', 'REPROCESO', 'reproceso'])) {
            return response()->json(['error' => 'No tiene permisos para editar esta orden.'], 403);
        }
        if ($userRole === 'admin_promo' && !in_array($orden->categoria, ['Promocional', 'Reprocesos', 'REPROCESO', 'reproceso'])) {
            return response()->json(['error' => 'No tiene permisos para editar esta orden.'], 403);
        }

        // Block editing for in-production states
        $estadosBloqueados = ['En proceso', 'Terminado', 'Finalizado'];
        if (in_array($orden->estado, $estadosBloqueados)) {
            return response()->json([
                'error'     => 'No es posible editar esta orden porque el proceso de producción ya inició o fue finalizado.',
                'estado'    => $orden->estado,
                'bloqueado' => true,
            ], 422);
        }

        return response()->json([
            'orden'    => $orden,
            'archivos' => $orden->archivos,
        ]);
    }

    /**
     * Update an existing OP (edición pre-producción).
     * Only allowed when estado is NOT En proceso / Terminado / Finalizado.
     */
    public function actualizarOP(Request $request, $id)
    {
        $userRole = session('user_role');
        $userCode = session('user_code');

        $isAdmin = in_array($userRole, ['admin', 'admin_branding', 'admin_promo']);
        $isSales = in_array($userRole, ['ventas', 'jefe_ventas']);

        if (!$isAdmin && !$isSales) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'No tiene permisos para editar órdenes de producción.'], 403);
            }
            return redirect()->route('login')->with('error', 'No tiene permisos para editar órdenes de producción.');
        }

        $orden = OrdenProduccion::with('archivos')->findOrFail($id);

        // Vendedor/Jefe: can only edit their own OPs
        if ($isSales && $orden->creado_por_codigo !== $userCode) {
            return response()->json(['error' => 'No tienes permiso para editar esta Orden de Producción.'], 403);
        }

        // Admin category-based access restriction
        if ($userRole === 'admin_branding' && !in_array($orden->categoria, ['Branding', 'Reprocesos', 'REPROCESO', 'reproceso'])) {
            return response()->json(['error' => 'No tiene permisos para editar esta orden.'], 403);
        }
        if ($userRole === 'admin_promo' && !in_array($orden->categoria, ['Promocional', 'Reprocesos', 'REPROCESO', 'reproceso'])) {
            return response()->json(['error' => 'No tiene permisos para editar esta orden.'], 403);
        }

        // Re-verify state before saving
        $estadosBloqueados = ['En proceso', 'Terminado', 'Finalizado'];
        if (in_array($orden->estado, $estadosBloqueados)) {
            $msg = 'No es posible editar esta orden porque el proceso de producción ya inició o fue finalizado.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => $msg, 'bloqueado' => true], 422);
            }
            $redirectRoute = $isAdmin ? 'op.admin' : ($userRole === 'jefe_ventas' ? 'op.jefe_ventas' : 'op.mis_ordenes');
            return redirect()->route($redirectRoute)->with('error', $msg);
        }

        // Validation rules — fecha_entrega y hora_entrega son de solo lectura y no se actualizan
        $rules = [
            'categoria'    => 'required|in:Branding,Promocional,Reprocesos,Reproceso,REPROCESO',
            'numero_op'    => 'required|string|max:100',
            'proyecto'     => 'required|string|max:255',
            'presupuestista' => 'required|string|max:255',
            'cliente'      => 'required|string|max:255',
            'marca'        => 'required|string|max:255',
            'entregar_a'   => 'required|in:Cliente,Bodega,Instaladores',
            'detalles'     => 'nullable|string',
            'brief'        => 'nullable|array',
            'brief.*'      => 'file|max:51200|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,zip',
            'archivos_eliminar' => 'nullable|array',
            'archivos_eliminar.*' => 'integer',
        ];

        if ($request->input('entregar_a') === 'Instaladores') {
            $rules['lugar_instalacion']    = 'required|string|max:255';
            $rules['fecha_instalacion']    = 'required|date';
            $rules['hora_instalacion']     = 'required';
            $rules['fecha_desinstalacion'] = 'nullable|date';
            $rules['hora_desinstalacion']  = 'nullable';
        }

        $validated = $request->validate($rules, [
            'categoria.required'    => 'La categoría es obligatoria.',
            'numero_op.required'    => 'El número de OP es obligatorio.',
            'proyecto.required'     => 'El nombre del proyecto es obligatorio.',
            'presupuestista.required' => 'El presupuestista es obligatorio.',
            'cliente.required'      => 'El cliente es obligatorio.',
            'marca.required'        => 'La marca es obligatoria.',
            'entregar_a.required'   => 'El destino de entrega es obligatorio.',
            'lugar_instalacion.required' => 'El lugar de instalación es obligatorio.',
            'fecha_instalacion.required' => 'La fecha de instalación es obligatoria.',
            'hora_instalacion.required'  => 'La hora de instalación es obligatoria.',
            'brief.*.max'           => 'El archivo no debe pesar más de 50MB.',
            'brief.*.mimes'         => 'El formato del archivo no está permitido. Formatos válidos: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX, TXT y ZIP.',
        ]);

        // Manual extension validation for new files
        if ($request->hasFile('brief')) {
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
            foreach ($request->file('brief') as $file) {
                if ($file) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, $allowedExtensions)) {
                        $errorMsg = 'El archivo no es válido. Formatos permitidos: PDF, JPG, PNG, DOC/DOCX, XLS/XLSX, TXT y ZIP.';
                        if ($request->ajax() || $request->wantsJson()) {
                            return response()->json(['error' => $errorMsg], 422);
                        }
                        return redirect()->back()->withInput()->withErrors(['brief' => $errorMsg]);
                    }
                }
            }
        }

        // Clean up installation fields if not Instaladores
        if ($request->input('entregar_a') !== 'Instaladores') {
            $validated['lugar_instalacion']    = null;
            $validated['fecha_instalacion']    = null;
            $validated['hora_instalacion']     = null;
            $validated['fecha_desinstalacion'] = null;
            $validated['hora_desinstalacion']  = null;
        }

        // Extract file-related data from validated array before updating order
        $nuevosArchivos    = $request->file('brief');
        $archivosEliminar  = $request->input('archivos_eliminar', []);
        unset($validated['brief'], $validated['archivos_eliminar']);

        // Snapshot old values for history
        $oldNumeroOp    = $orden->numero_op;
        $oldCategoria   = $orden->categoria;
        $oldFecha       = $orden->fecha_entrega;
        $oldPresup      = $orden->presupuestista;

        $newUploadedPaths = [];
        $filesToDeletePaths = [];

        DB::beginTransaction();
        try {
            // Update order fields
            $orden->update($validated);

            // 1. Delete files marked for removal from DB first, collect paths to delete later
            if (!empty($archivosEliminar)) {
                $archivosAEliminar = OrdenProduccionArchivo::whereIn('id', $archivosEliminar)
                    ->where('orden_produccion_id', $orden->id)
                    ->get();

                foreach ($archivosAEliminar as $archivo) {
                    $filesToDeletePaths[] = $archivo->file_path;
                    $archivo->delete();
                }
            }

            // 2. Add new files
            if ($nuevosArchivos && is_array($nuevosArchivos)) {
                foreach ($nuevosArchivos as $file) {
                    if ($file) {
                        $uploadResult = AdjuntoStorageService::uploadFile($file, $orden->id);
                        $newUploadedPaths[] = $uploadResult['path'];
                        $orden->archivos()->create([
                            'file_path'   => $uploadResult['path'],
                            'file_name'   => $uploadResult['name'],
                            'file_size'   => $uploadResult['size'],
                            'mime_type'   => $uploadResult['mime_type'],
                            'uploaded_by' => session('user_code') ?: 'SISTEMA',
                        ]);
                    }
                }
            }

            // --- History log ---
            $userCode = session('user_code') ?: 'SISTEMA';
            $userName = session('user_name') ?: 'SISTEMA';
            $userRol  = session('user_role')  ?: 'sistema';

            $orden->historial()->create([
                'tipo_evento'          => 'edicion',
                'descripcion'          => "{$userName} ({$userCode}) editó la OP. Datos anteriores: OP={$oldNumeroOp}, Categoría={$oldCategoria}, Fecha={$oldFecha}, Presupuestista={$oldPresup}.",
                'realizado_por_codigo' => $userCode,
                'realizado_por_nombre' => $userName,
                'realizado_por_rol'    => $userRol,
            ]);

            DB::commit();

            // 3. Commit succeeded, safe to delete old files from storage
            foreach ($filesToDeletePaths as $path) {
                try {
                    AdjuntoStorageService::deleteFile($path);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error("Failed to delete old file from storage: " . $path);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up newly uploaded files from Storage since DB failed
            foreach ($newUploadedPaths as $path) {
                try {
                    AdjuntoStorageService::deleteFile($path);
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::error("Failed to delete uploaded file after DB failure: " . $path);
                }
            }
            throw $e;
        }

        $this->clearDashboardCache();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Orden de producción actualizada correctamente.',
            ]);
        }

        // Redirect to the correct panel depending on role
        if ($userRole === 'jefe_ventas') {
            return redirect()->route('op.jefe_ventas')->with('success', 'Orden de producción actualizada correctamente.');
        }
        if ($userRole === 'ventas') {
            return redirect()->route('op.mis_ordenes')->with('success', 'Orden de producción actualizada correctamente.');
        }
        return redirect()->route('op.admin')->with('success', 'Orden de producción actualizada correctamente.');
    }

    /**
     * Check if the current user has access to view/download files for a given production order.
     */
    private function checkUserAuthorization(OrdenProduccion $orden): bool
    {
        $userRole = session('user_role');
        $userCode = session('user_code');

        if ($userRole === 'admin') {
            return true;
        }

        if ($userRole === 'admin_branding') {
            return in_array($orden->categoria, ['Branding', 'Reprocesos', 'REPROCESO', 'reproceso']) &&
                (!$orden->original || $orden->original->categoria === 'Branding');
        }

        if ($userRole === 'admin_promo') {
            return in_array($orden->categoria, ['Promocional', 'Reprocesos', 'REPROCESO', 'reproceso']) &&
                (!$orden->original || $orden->original->categoria === 'Promocional');
        }

        if ($userRole === 'jefe_ventas') {
            if ($orden->creado_por_codigo === $userCode) {
                return true;
            }
            // Check if the creator is a seller under this manager
            return UsuarioAcceso::where('codigo', $orden->creado_por_codigo)
                ->where('jefe_codigo', $userCode)
                ->exists();
        }

        if ($userRole === 'ventas') {
            return $orden->creado_por_codigo === $userCode;
        }

        if (in_array($userRole, ['vista', 'tv_branding', 'tv_promocional'])) {
            return true;
        }

        return false;
    }

    /**
     * Helper to get MIME type by file extension.
     */
    private function getMimeTypeByExtension(string $ext): string
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];
        return $mimes[strtolower($ext)] ?? 'application/octet-stream';
    }
}

