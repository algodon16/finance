<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Accountant → Security and Audit Trail (own relevant activity, read-only). */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::with('user')->where('user_id', auth()->id())->latest('id');
        if ($request->filled('action')) $q->where('action', $request->action);
        if ($request->filled('module')) $q->where('module', $request->module);
        $records = $q->paginate(20)->withQueryString();
        $actions = AuditLog::where('user_id', auth()->id())->distinct()->pluck('action');
        $modules = AuditLog::where('user_id', auth()->id())->distinct()->pluck('module');
        return view('accountant.audit.index', compact('records', 'actions', 'modules'));
    }
}
