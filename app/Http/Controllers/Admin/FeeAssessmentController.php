<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FeeAssessmentController extends Controller
{
    public function index()
    {
        $fees = FeeAssessment::latest()->paginate(20);

        return view('admin.fee-assessment.index', compact('fees'));
    }

    public function create()
    {
        return view('admin.fee-assessment.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fee_name' => 'required|string|max:255|unique:fee_assessments,fee_name',
            'description' => 'nullable|string|max:1000',
            'default_amount' => 'required|numeric|min:0',
        ]);

        $fee = FeeAssessment::create($validated);

        AuditService::log('create', 'fee_assessment', $fee->id, null, $fee->toArray());

        return redirect()->route('admin.feeAssessment.index')
            ->with('success', 'Fee created successfully.');
    }

    public function edit($id)
    {
        $fee = FeeAssessment::findOrFail($id);

        return view('admin.fee-assessment.edit', compact('fee'));
    }

    public function update(Request $request, $id)
    {
        $fee = FeeAssessment::findOrFail($id);

        $validated = $request->validate([
            'fee_name' => 'required|string|max:255|unique:fee_assessments,fee_name,' . $id,
            'description' => 'nullable|string|max:1000',
            'default_amount' => 'required|numeric|min:0',
        ]);

        $oldData = $fee->toArray();

        $fee->update($validated);

        AuditService::log('update', 'fee_assessment', $fee->id, $oldData, $fee->fresh()->toArray());

        return redirect()->route('admin.feeAssessment.index')
            ->with('success', 'Fee updated successfully.');
    }

    public function destroy($id)
    {
        $fee = FeeAssessment::findOrFail($id);

        if ($fee->assessmentRules()->exists()) {
            return back()->with('error', 'This fee cannot be deleted because it is used in one or more fee assignments. Remove it from those assignments first.');
        }

        AuditService::log('delete', 'fee_assessment', $fee->id, $fee->toArray(), null);

        $fee->delete();

        return redirect()->route('admin.feeAssessment.index')
            ->with('success', 'Fee deleted successfully.');
    }
}
