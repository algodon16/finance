<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Central Accountant → Admin submission workflow.
 *
 * Handles submit/approve/reject with DB transactions, audit logs,
 * and notifications. Never allows partial financial updates.
 */
class WorkflowService
{
    public static function notifyAdmins(string $title, string $message): void
    {
        $adminIds = User::where('role', 'admin')->where('is_active', true)->pluck('id');
        foreach ($adminIds as $id) {
            try {
                NotificationService::create($id, $title, $message);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function notifyUser($userId, string $title, string $message): void
    {
        if (! $userId) return;
        try {
            NotificationService::create($userId, $title, $message);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Standard statuses: draft, submitted, approved, rejected, revision, cancelled.
     * Legacy aliases accepted (read): under_review (=submitted), for_revision (=revision).
     * Submit a draft/rejected/revision record for admin approval.
     * $statusField defaults to 'status', expenses/payables use 'approval_status'.
     */
    public const PENDING = ['submitted', 'under_review'];
    public const RETURNED = ['rejected', 'for_revision', 'revision'];

    /**
     * @param string[] $from  Allowed source statuses (e.g. payments use pending as draft).
     * @param string   $to    Target submitted status (e.g. payments use under_review).
     */
    public static function submit($model, string $module, string $label, string $statusField = 'status', array $from = ['draft', 'rejected', 'for_revision', 'revision', 'cancelled'], string $to = 'submitted'): void
    {
        $current = $model->{$statusField};
        abort_if(! in_array($current, $from, true), 422, 'Only draft or rejected/revision records can be submitted.');

        $ref = self::referenceNo($model);
        DB::transaction(function () use ($model, $statusField, $to) {
            $patch = [
                $statusField => $to,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ];
            if (self::hasColumn($model, 'submitted_by')) $patch['submitted_by'] = auth()->id();
            $model->update($patch);
        });

        AuditService::log('submit', $module, (string) $model->id, ['status' => $current], ['status' => $to], "Accountant ".auth()->user()->name." submitted {$label} {$ref} for approval.");
        self::notifyAdmins("Accountant submitted {$label} {$ref} for approval.", "{$label} {$ref} submitted by ".auth()->user()->name.". Review it in the corresponding module.");
        // Notify preparer of resubmission lifecycle.
        if (in_array($current, ['rejected', 'for_revision', 'revision'], true)) {
            AuditService::log('resubmit', $module, (string) $model->id, ['status' => $current], ['status' => $to], "Accountant ".auth()->user()->name." resubmitted {$label} {$ref} for approval.");
        }
    }

    public static function approve($model, string $module, string $label, string $statusField, array $extra = []): void
    {
        // Backend RBAC: only admin may approve.
        abort_unless(auth()->user() && auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        $current = $model->{$statusField};
        abort_if(in_array($current, ['approved', 'completed', 'paid', 'fully_paid'], true), 422, 'Record is already approved/closed.');
        abort_if(! in_array($current, ['submitted', 'under_review'], true), 422, 'Only submitted records can be approved.');

        $old = $model->toArray();
        DB::transaction(function () use ($model, $statusField, $extra) {
            $patch = array_merge([
                $statusField => 'approved',
                'reviewed_at' => now(),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_remarks' => request('admin_remarks') ?: $model->admin_remarks,
            ], $extra);
            if (self::hasColumn($model, 'reviewed_by')) $patch['reviewed_by'] = auth()->id();
            $model->update($patch);
        });

        AuditService::log('approve', $module, (string) $model->id, $old, $model->fresh()->toArray(), "Admin ".auth()->user()->name." approved {$label} ".self::referenceNo($model).".");
        $preparer = $model->created_by ?? $model->prepared_by ?? null;
        self::notifyUser($preparer, self::referenceNo($model)." has been approved.", "{$label} ".self::referenceNo($model)." was approved by admin and is now available in the module overview.");
    }

    public static function reject($model, string $module, string $label, string $statusField, ?string $reason): void
    {
        abort_unless(auth()->user() && auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        abort_if(empty($reason), 422, 'Rejection reason is required.');
        $current = $model->{$statusField};
        abort_if(in_array($current, ['approved', 'completed', 'paid'], true), 422, 'Approved records cannot be rejected.');
        abort_if(! in_array($current, ['submitted', 'under_review'], true), 422, 'Only submitted records can be rejected.');

        $old = $model->toArray();
        $rev = (int) ($model->revision_number ?? 0);
        DB::transaction(function () use ($model, $statusField, $reason, $rev) {
            $patch = [
                $statusField => 'rejected',
                'rejection_reason' => $reason,
                'admin_remarks' => request('admin_remarks') ?: $model->admin_remarks,
                'reviewed_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
            ];
            if (self::hasColumn($model, 'reviewed_by')) $patch['reviewed_by'] = auth()->id();
            if (self::hasColumn($model, 'revision_number')) $patch['revision_number'] = $rev + 1;
            $model->update($patch);
        });

        AuditService::log('reject', $module, (string) $model->id, $old, $model->fresh()->toArray(), "Admin ".auth()->user()->name." rejected {$label} ".self::referenceNo($model).": {$reason}");
        $preparer = $model->created_by ?? $model->prepared_by ?? null;
        self::notifyUser($preparer, self::referenceNo($model)." was rejected. Reason: {$reason}", "{$label} ".self::referenceNo($model)." returned for revision. Edit the same record and resubmit.");
    }

    public static function cancelSubmission($model, string $module, string $label, string $statusField = 'status'): void
    {
        abort_unless(auth()->user() && auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($model->{$statusField}, ['submitted', 'under_review'], true), 422, 'Only pending submissions can be withdrawn.');
        $old = $model->{$statusField};
        $patch = [$statusField => 'draft', 'submitted_at' => null];
        if (self::hasColumn($model, 'submitted_by')) $patch['submitted_by'] = null;
        $model->update($patch);
        AuditService::log('cancel', $module, (string) $model->id, ['status' => $old], ['status' => 'draft'], "Accountant ".auth()->user()->name." withdrew {$label} #{$model->id}.");
    }

    protected static function hasColumn($model, string $col): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Reference number for notifications/audit — same record, no duplicate. */
    public static function referenceNo($model): string
    {
        if (! empty($model->request_number)) return (string) $model->request_number;
        if (! empty($model->reference_number)) return (string) $model->reference_number;
        if (! empty($model->invoice_number)) return (string) $model->invoice_number;
        if ($model instanceof \App\Models\BudgetPlan) return 'BP-2026-'.str_pad((string) $model->id, 4, '0', STR_PAD_LEFT);
        return '#'.$model->id;
    }

    /** User-friendly status label (Pending Approval / For Revision / ...). */
    public static function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft', 'submitted', 'under_review' => 'Pending Approval',
            'approved', 'active' => 'Approved', 'rejected' => 'Rejected',
            'for_revision', 'revision' => 'For Revision', 'cancelled' => 'Cancelled',
            'for_disbursement' => 'For Disbursement', 'partially_paid' => 'Partially Paid',
            'fully_paid', 'paid' => 'Paid', 'pending' => 'Pending',
            'overdue' => 'Overdue', 'due_soon' => 'Due Soon',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
