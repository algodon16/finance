<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Fms\DashboardController as FmsDashboard;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(FmsDashboard $fms)
    {
        // Admin landing page is the full FMS financial overview dashboard.
        return $fms->index();
    }
}
