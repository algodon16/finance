<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BudgetPlan extends Model
{
    protected $fillable = [
        'budget_name', 'academic_year', 'department', 'budget_category',
        'allocated_amount', 'utilized_amount', 'requested_amount', 'proposed_amount', 'approved_amount',
        'start_date', 'end_date',
        'status', 'description', 'justification', 'funding_source',
        'supporting_document', 'rejection_reason', 'admin_remarks', 'accountant_remarks',
        'allocation_recommendation', 'documents_verified',
        'request_type', 'request_details',
        'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by',
        'approved_by', 'approved_at', 'revision_number', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'utilized_amount' => 'decimal:2',
        'requested_amount' => 'decimal:2',
        'proposed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'documents_verified' => 'array',
        'request_details' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetPlanItem::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Official = part of the books (submitted → approved/active). Drafts/rejected/cancelled are working copies. */
    public const OFFICIAL = ['submitted', 'under_review', 'for_approval', 'approved', 'active'];

    /** Request pipeline groups for the Budget Requests hub. */
    public const REQUEST_PENDING = ['submitted'];
    public const REQUEST_REVIEW = ['under_review'];
    public const REQUEST_AWAITING_ADMIN = ['for_approval'];
    public const REQUEST_APPROVED = ['approved', 'active'];
    public const REQUEST_REJECTED = ['rejected'];
    public const REQUEST_RETURNED = ['for_revision', 'revision'];

    /** Accountant allocation recommendations. */
    public const RECOMMENDATIONS = ['full' => 'Full Allocation', 'partial' => 'Partial Allocation', 'no_allocation' => 'No Allocation'];

    /** Supporting-document verification (single confirmation). */
    public const DOC_CHECKLIST = ['verified' => 'Documents Verified'];

    /** Request types: stable internal values with human-readable labels. */
    public const REQUEST_TYPES = [
        'department_operating' => 'Department Operating Budget',
        'item_procurement' => 'Item / Procurement Request',
        'payroll_personnel' => 'Payroll / Personnel Budget',
        'program_activity' => 'Program / Activity Budget',
        'project' => 'Project Budget',
        'asset_acquisition' => 'Asset Acquisition Budget',
        'maintenance_repair' => 'Maintenance / Repair Budget',
        'research_grant' => 'Research / Grant Budget',
        'emergency_contingency' => 'Emergency / Contingency Budget',
        'fund_reallocation' => 'Fund Transfer / Reallocation',
        'other' => 'Other Financial Request',
    ];

    /** Breakdown categories per request type. */
    public const TYPE_CATEGORIES = [
        'department_operating' => ['Office Operations', 'Supplies', 'Utilities', 'Training', 'Travel', 'Other Operating Expenses'],
        'item_procurement' => ['Office Equipment', 'IT Equipment', 'Furniture', 'Supplies', 'Other Items'],
        'payroll_personnel' => ['Salaries', 'Overtime', 'Allowances', 'Benefits', 'Other Personnel Cost'],
        'program_activity' => ['Venue', 'Materials', 'Food & Catering', 'Honoraria', 'Transport', 'Other Activity Cost'],
        'project' => ['Labor', 'Materials', 'Equipment', 'Services', 'Other Project Cost'],
        'asset_acquisition' => ['IT Equipment', 'Furniture & Fixtures', 'Vehicles', 'Machinery', 'Other Assets'],
        'maintenance_repair' => ['Labor', 'Parts & Materials', 'Services', 'Other Maintenance Cost'],
        'research_grant' => ['Personnel', 'Equipment', 'Materials', 'Travel', 'Publication', 'Other Research Cost'],
        'emergency_contingency' => ['Relief', 'Repairs', 'Medical', 'Logistics', 'Other Emergency Cost'],
        'fund_reallocation' => ['Transfer', 'Other'],
        'other' => ['General', 'Other'],
    ];

    /** Item rows carry quantity/unit cost (qty × unit math). */
    public const QTY_TYPES = ['item_procurement', 'asset_acquisition'];

    public function getRequestTypeLabelAttribute(): string
    {
        return static::REQUEST_TYPES[$this->request_type] ?? ($this->request_type ?: '—');
    }

    /**
     * Canonical year column is `academic_year` (user-facing term).
     * Resolved dynamically so queries never crash with SQLSTATE[42703].
     */
    public static function yearColumn(): string
    {
        try {
            if (Schema::hasColumn('budget_plans', 'academic_year')) {
                return 'academic_year';
            }
            if (Schema::hasColumn('budget_plans', 'fiscal_year')) {
                return 'fiscal_year';
            }
        } catch (\Throwable $e) {
            // fall through to default
        }
        return 'academic_year';
    }

    /** Accessor: $plan->academic_year works regardless of physical column. */
    public function getAcademicYearAttribute(): ?string
    {
        return $this->attributes['academic_year']
            ?? $this->attributes['fiscal_year']
            ?? null;
    }

    /** Back-compat alias. */
    public function getFiscalYearAttribute(): ?string
    {
        return $this->academic_year;
    }

    public function isOfficial(): bool
    {
        return in_array($this->status, self::OFFICIAL, true);
    }

    /**
     * Find another official plan for the same department + academic year
     * (case-insensitive). Used to block duplicate budgets across roles.
     */
    public static function officialDuplicateExists(string $department, string $academicYear, ?int $ignoreId = null): ?self
    {
        $col = static::yearColumn();
        $q = self::where($col, $academicYear)
            ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim($department))])
            ->whereIn('status', self::OFFICIAL);
        if ($ignoreId) $q->where('id', '!=', $ignoreId);
        return $q->first();
    }

    /** Server-side: remaining = allocated - utilized. Never trust frontend. */
    public function getRemainingAmountAttribute(): string
    {
        return bcsub((string) $this->allocated_amount, (string) $this->utilized_amount, 2);
    }

    public function getUtilizationRateAttribute(): float
    {
        if ((float) $this->allocated_amount <= 0) return 0.0;
        return round(((float) $this->utilized_amount / (float) $this->allocated_amount) * 100, 2);
    }

    /** Human request reference, e.g. BR-2027-0001 (derived from academic year + id). */
    public function getRequestIdAttribute(): string
    {
        $year = $this->created_at?->format('Y');
        if (preg_match('/(\d{4})/', (string) ($this->attributes['academic_year'] ?? $this->attributes['fiscal_year'] ?? ''), $m)) {
            $year = $m[1];
        }
        return 'BR-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Department's original ask (falls back to legacy single-column value). */
    public function getRequestedAmountValueAttribute(): float
    {
        if ($this->requested_amount !== null && (float) $this->requested_amount > 0) {
            return (float) $this->requested_amount;
        }
        return (float) $this->allocated_amount;
    }

    /** Accountant's proposed amount (falls back to the requested amount). */
    public function getProposedAmountValueAttribute(): float
    {
        if ($this->proposed_amount !== null && (float) $this->proposed_amount > 0) {
            return (float) $this->proposed_amount;
        }
        return $this->requested_amount_value;
    }

    /** Final approved amount (explicit column; falls back to proposed). */
    public function getApprovedAmountValueAttribute(): float
    {
        if ($this->approved_amount !== null && (float) $this->approved_amount > 0) {
            return (float) $this->approved_amount;
        }
        return (float) $this->proposed_amount_value;
    }

    /** Whether supporting documents were confirmed verified. */
    public function getDocumentsVerifiedFlagAttribute(): bool
    {
        $v = $this->documents_verified;
        if (is_array($v)) return in_array('verified', $v, true);
        return (bool) $v;
    }

    /** Fund record matching this plan's funding_source name (if any). */
    public function linkedFund(): ?Fund
    {
        if (empty($this->funding_source)) return null;
        return Fund::where('fund_name', $this->funding_source)->first();
    }

    /** Display lifecycle status derived from the real record state. */
    public static function lifecycleStatus(self $plan): string
    {
        $s = $plan->status;
        if (in_array($s, ['approved', 'active'], true)) {
            return ((float) $plan->utilized_amount > 0) ? 'Ongoing' : 'Approved';
        }
        return \App\Services\WorkflowService::label($s);
    }

    /**
     * Single-workspace dataset: summary cards, per-department lifecycle rows,
     * fund map, and recent activity — shared by the Admin and Accountant
     * workspace pages so both roles see the same underlying records.
     */
    public static function workspace(?string $search = null, ?string $status = null, ?string $year = null): array
    {
        $yearCol = static::yearColumn();

        $summary = [
            'requested' => (float) static::sum('requested_amount'),
            'proposed' => (float) static::whereNotNull('proposed_amount')->sum('proposed_amount'),
            'approved' => (float) static::whereIn('status', static::REQUEST_APPROVED)->sum('approved_amount'),
            'allocated' => (float) static::whereIn('status', static::REQUEST_APPROVED)->sum('allocated_amount'),
            'utilized' => (float) static::whereIn('status', static::REQUEST_APPROVED)->sum('utilized_amount'),
        ];
        // Fallbacks for legacy rows that predate the split columns.
        if ($summary['requested'] <= 0) $summary['requested'] = (float) static::sum('allocated_amount');
        if ($summary['approved'] <= 0) $summary['approved'] = (float) static::whereIn('status', static::REQUEST_APPROVED)->sum('allocated_amount');
        $summary['utilized'] = min($summary['utilized'], $summary['allocated']);
        $summary['remaining'] = max(0, $summary['allocated'] - $summary['utilized']);
        $summary['util_rate'] = $summary['allocated'] > 0 ? round(($summary['utilized'] / $summary['allocated']) * 100, 2) : 0.0;
        $summary['rem_rate'] = $summary['allocated'] > 0 ? round(($summary['remaining'] / $summary['allocated']) * 100, 2) : 0.0;
        $summary['dept_count'] = (int) static::whereNotNull('department')->where('department', '<>', '')->distinct('department')->count('department');
        $summary['approved_dept_count'] = (int) static::whereIn('status', static::REQUEST_APPROVED)->whereNotNull('department')->where('department', '<>', '')->distinct('department')->count('department');

        // Department rows (one row per department, same lifecycle record throughout).
        $deptQuery = static::whereNotNull('department')->where('department', '<>', '');
        if ($search) {
            $deptQuery->where(fn($w) => $w->where('department', 'ilike', "%{$search}%")->orWhere('budget_name', 'ilike', "%{$search}%"));
        }
        if ($year) $deptQuery->where($yearCol, $year);
        if ($status) $deptQuery->where('status', $status);
        $departments = $deptQuery->distinct()->orderBy('department')->pluck('department');

        $deptRows = [];
        foreach ($departments as $dept) {
            $plans = static::where('department', $dept)->orderByDesc('id')->get();
            if ($status) $plans = $plans->where('status', $status);
            if ($plans->isEmpty()) continue;
            $rep = $plans->first(); // latest record carries the lifecycle
            $rep->loadMissing(['creator', 'reviewer', 'approver', 'allocations']);
            $requested = (float) $plans->sum(fn($p) => $p->requested_amount_value);
            $proposed = (float) $plans->sum(fn($p) => (float) ($p->proposed_amount ?? 0));
            $appr = $plans->whereIn('status', static::REQUEST_APPROVED);
            $approved = (float) $appr->sum(fn($p) => (float) ($p->approved_amount ?? $p->allocated_amount));
            $allocated = (float) $appr->sum(fn($p) => (float) $p->allocated_amount);
            $utilized = min((float) $appr->sum(fn($p) => (float) $p->utilized_amount), $allocated);
            $remaining = max(0, $allocated - $utilized);
            $alloc = $rep->allocations->first();
            $deptRows[] = [
                'department' => $dept,
                'plan' => $rep,
                'requested' => $requested, 'proposed' => $proposed,
                'approved' => $approved, 'allocated' => $allocated,
                'utilized' => $utilized, 'remaining' => $remaining,
                'rate' => $allocated > 0 ? round(($utilized / $allocated) * 100, 2) : 0.0,
                'status' => static::lifecycleStatus($rep),
                'flow' => [
                    'request' => ['amount' => $rep->requested_amount_value, 'state' => $rep->submitted_at ? 'Submitted '.$rep->submitted_at->format('M d, Y') : ucfirst($rep->status)],
                    'review' => ['amount' => $rep->proposed_amount ? (float) $rep->proposed_amount : null, 'state' => $rep->reviewed_at ? 'Reviewed '.$rep->reviewed_at->format('M d, Y') : 'Pending'],
                    'approval' => ['amount' => $rep->approved_amount ? (float) $rep->approved_amount : null, 'state' => $rep->approved_at ? 'Approved '.$rep->approved_at->format('M d, Y') : ucfirst($rep->status)],
                    'allocation' => ['amount' => in_array($rep->status, static::REQUEST_APPROVED, true) ? (float) $rep->allocated_amount : null, 'state' => $alloc ? ('Allocated '.optional($alloc->allocation_date)->format('M d, Y')) : 'Pending', 'ref' => $alloc?->allocation_id],
                    'utilization' => ['amount' => (float) $rep->utilized_amount, 'state' => $rep->utilization_rate.'%'],
                    'remaining' => ['amount' => (float) $rep->remaining_amount, 'state' => in_array($rep->status, static::REQUEST_APPROVED, true) ? 'Available' : ucfirst($rep->status)],
                ],
            ];
        }

        // Recent activity from real audit records.
        $logs = AuditLog::with('user')->where('module', 'budget_plans')->latest('id')->take(10)->get();
        $planIds = $logs->pluck('record_id')->filter()->unique()->values();
        $planMap = static::whereIn('id', $planIds)->get()->keyBy('id');
        $activity = $logs->map(function ($log) use ($planMap) {
            $p = $planMap->get((int) $log->record_id);
            return [
                'date' => $log->created_at, 'department' => $p->department ?? '—',
                'request' => $p?->request_id ?? ('#'.$log->record_id),
                'action' => $log->action, 'description' => $log->description,
                'amount' => $p ? $p->requested_amount_value : null,
                'user' => $log->user->name ?? 'System',
            ];
        });

        try {
            $years = static::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }

        return compact('summary', 'deptRows', 'activity', 'years');
    }
}
