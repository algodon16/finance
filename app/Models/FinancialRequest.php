<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialRequest extends Model
{
    protected $fillable = [
        'request_number', 'request_type', 'reference_type', 'reference_id',
        'budget_plan_id',
        'source_system', 'source_request_id',
        'description', 'amount', 'department', 'request_date', 'prepared_by', 'student_id',
        'metadata', 'supporting_document', 'status', 'admin_decision',
        'admin_remarks', 'decided_by', 'decided_at', 'submitted_at',
        'submitted_by', 'reviewed_by', 'revision_number', 'cancelled_at',
        'received_at', 'reviewed_at', 'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'request_date' => 'date',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Originating subsystems (internal = created inside Financial Management). */
    public const SOURCES = [
        'internal' => 'Financial Management (Internal)',
        'property_custodian' => 'Property Custodian',
        'academic_hr' => 'Academic HR',
        'crad' => 'CRAD / Research',
        'procurement' => 'Procurement System',
        'school_events' => 'School Events / Co-curricular',
        'other' => 'Other Subsystem',
    ];

    /** Committed = not yet completed/posted, still encumbering the budget. */
    public const COMMITTED = ['submitted', 'under_review', 'approved'];

    /**
     * Shared intake for simulated/demo receptions (used by both roles).
     * Runs the same gates as the API: unique source identity + budget cover.
     * Demo records are real rows flagged in metadata — never hidden mocks.
     * If no source_request_id is given, one is auto-generated per source.
     */
    public static function generateSourceRequestId(string $source): string
    {
        $prefixes = [
            'property_custodian' => 'PC',
            'academic_hr' => 'HR',
            'crad' => 'CRAD',
            'procurement' => 'PROC',
            'school_events' => 'SE',
            'other' => 'RID',
        ];
        $prefix = $prefixes[$source] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $source) ?: 'REQ', 0, 4));
        do {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
            $exists = static::where('source_system', $source)
                ->where('source_request_id', $candidate)->exists();
        } while ($exists);
        return $candidate;
    }

    public static function receiveSimulated(array $data, int $userId): self
    {
        if (empty($data['source_request_id'])) {
            $data['source_request_id'] = static::generateSourceRequestId($data['source_system']);
        }
        $dup = static::where('source_system', $data['source_system'])
            ->where('source_request_id', $data['source_request_id'])->exists();
        abort_if($dup, 422, 'This source request was already received.');
        $budget = ! empty($data['budget_plan_id']) ? BudgetPlan::find($data['budget_plan_id']) : null;
        abort_if($budget && ! in_array($budget->status, ['approved', 'active'], true), 422, 'Linked budget is not approved/active.');
        if ($budget) {
            $v = static::budgetValidation($budget, (float) $data['amount']);
            abort_if(! $v['valid'], 422, 'Budget insufficient: requested P'.number_format((float) $data['amount'], 2).' exceeds available P'.number_format($v['available'], 2).'.');
        }
        $record = static::create([
            'request_number' => 'FR-'.now()->format('Ymd').'-'.strtoupper(uniqid()),
            'source_system' => $data['source_system'], 'source_request_id' => $data['source_request_id'],
            'department' => $data['department'], 'request_type' => $data['request_type'] ?? 'other',
            'budget_plan_id' => $data['budget_plan_id'] ?? null,
            'description' => $data['purpose'], 'amount' => $data['amount'],
            'request_date' => today(), 'metadata' => ['demo' => true, 'purpose' => $data['purpose']],
            'status' => 'submitted', 'prepared_by' => $userId,
            'submitted_at' => now(), 'received_at' => now(),
        ]);
        \App\Services\AuditService::log('receive', 'financial_requests', (string) $record->id, null,
            ['source' => $data['source_system'].':'.$data['source_request_id'], 'status' => 'submitted', 'demo' => true],
            'Demo intake: received '.$data['source_system'].' request '.$data['source_request_id'].' as '.$record->request_number.'.');
        return $record;
    }

    public function getSourceLabelAttribute(): string
    {
        return static::SOURCES[$this->source_system] ?? ucfirst(str_replace('_', ' ', (string) $this->source_system));
    }

    /** Original request ID stays the primary reference (no duplicate IDs). */
    public function getDisplayRefAttribute(): string
    {
        return $this->source_request_id ?: $this->request_number;
    }

    /** Sum encumbering a budget (excludes self, completed, rejected, cancelled). */
    public static function committedForBudget(int $budgetId, ?int $excludeId = null): float
    {
        $q = static::where('budget_plan_id', $budgetId)->whereIn('status', static::COMMITTED);
        if ($excludeId) $q->where('id', '!=', $excludeId);
        return (float) $q->sum('amount');
    }

    /**
     * Budget validation snapshot for a linked request:
     * Available = Allocated − Committed − Utilized.
     */
    public static function budgetValidation(?BudgetPlan $budget, float $amount = 0, ?int $excludeId = null): ?array
    {
        if (! $budget || ! in_array($budget->status, ['approved', 'active'], true)) return null;
        $allocated = (float) $budget->allocated_amount;
        $utilized = (float) $budget->utilized_amount;
        $committed = static::committedForBudget($budget->id, $excludeId);
        $available = $allocated - $committed - $utilized;
        return [
            'budget' => $budget,
            'approved' => (float) ($budget->approved_amount ?? $allocated),
            'allocated' => $allocated, 'committed' => $committed,
            'utilized' => $utilized, 'available' => $available,
            'fund' => $budget->linkedFund(),
            'valid' => $amount <= $available + 0.009,
        ];
    }

    public const TYPES = [
        'budget', 'fund_allocation', 'expense', 'disbursement',
        'payable', 'assessment_adjustment', 'reconciliation', 'other',
    ];

    public const STATUSES = [
        'draft', 'submitted', 'under_review', 'approved',
        'rejected', 'for_revision', 'completed',
    ];

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /** Auto-created fulfillment payable (at most one — created on admin approval). */
    public function payable()
    {
        return $this->hasOne(AccountsPayable::class, 'financial_request_id');
    }

    /** Authorization ceiling set at approval (recommended amount or requested amount). */
    public function getApprovedAmountAttribute(): ?float
    {
        $meta = is_array($this->metadata) ? $this->metadata : [];
        if (isset($meta['approved_amount']) && (float) $meta['approved_amount'] > 0) {
            return (float) $meta['approved_amount'];
        }
        if (isset($meta['recommended_amount']) && (float) $meta['recommended_amount'] > 0) {
            return (float) $meta['recommended_amount'];
        }
        return null;
    }

    /** Actual transacted amount recorded by the accountant (null until recorded). */
    public function getActualAmountAttribute(): ?float
    {
        $meta = is_array($this->metadata) ? $this->metadata : [];
        if (isset($meta['actual_amount']) && (float) $meta['actual_amount'] > 0) {
            return (float) $meta['actual_amount'];
        }
        return null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }
}
