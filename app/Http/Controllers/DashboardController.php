<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'empresas' => Empresa::count(),
                'usuarios' => User::count(),
                'roles'    => Rol::count(),
                'modulos'  => Modulo::count(),
            ],
        ]);
    }
}
