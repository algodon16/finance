<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::with('user');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('action', 'ilike', "%{$s}%")->orWhere('module', 'ilike', "%{$s}%")->orWhere('description', 'ilike', "%{$s}%")->orWhere('record_id', 'ilike', "%{$s}%"));
        }
        if ($request->filled('module')) $q->where('module', $request->module);
        if ($request->filled('action')) $q->where('action', $request->action);
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->date_to);
        $logs = $q->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $modules = AuditLog::select('module')->distinct()->pluck('module');
        $actions = AuditLog::select('action')->distinct()->pluck('action');
        return view('admin.fms.audit.index', compact('logs', 'modules', 'actions'));
    }
}
