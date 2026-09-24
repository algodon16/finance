<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentRule;
use App\Models\FeeAssessment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentRuleController extends Controller
{
    public function index()
    {
        $rules = AssessmentRule::with('fees')->latest()->paginate(20);

        return view('admin.assessment-rules.index', compact('rules'));
    }

    public function create()
    {
        $fees = FeeAssessment::orderBy('fee_name')->get();

        return view('admin.assessment-rules.create', compact('fees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program' => [
                'required', 'string', 'max:255',
                Rule::unique('assessment_rules')->where(function ($query) use ($request) {
                    return $query->where('year_level', $request->input('year_level'))
                        ->where('semester', $request->input('semester'))
                        ->where('academic_year', $request->input('academic_year'));
                }),
            ],
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'fees' => 'required|array|min:1',
            'fees.*' => 'exists:fee_assessments,id',
        ], [
            'program.unique' => 'A fee assignment already exists for this program, year level, semester, and academic year.',
        ]);

        $rule = DB::transaction(function () use ($validated) {
            $rule = AssessmentRule::create([
                'program' => $validated['program'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'academic_year' => $validated['academic_year'],
            ]);

            $this->syncFees($rule, $validated['fees']);

            return $rule;
        });

        AuditService::log('create', 'assessment_rule', $rule->id, null, $rule->toArray());

        return redirect()->route('admin.assessmentRules.index')
            ->with('success', 'Assessment rule created successfully.');
    }

    public function edit($id)
    {
        $rule = AssessmentRule::with('fees')->findOrFail($id);
        $fees = FeeAssessment::orderBy('fee_name')->get();

        return view('admin.assessment-rules.edit', compact('rule', 'fees'));
    }

    public function update(Request $request, $id)
    {
        $rule = AssessmentRule::findOrFail($id);

        $validated = $request->validate([
            'program' => [
                'required', 'string', 'max:255',
                Rule::unique('assessment_rules')->where(function ($query) use ($request) {
                    return $query->where('year_level', $request->input('year_level'))
                        ->where('semester', $request->input('semester'))
                        ->where('academic_year', $request->input('academic_year'));
                })->ignore($rule->id),
            ],
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'fees' => 'required|array|min:1',
            'fees.*' => 'exists:fee_assessments,id',
        ], [
            'program.unique' => 'A fee assignment already exists for this program, year level, semester, and academic year.',
        ]);

        $oldData = $rule->toArray();

        DB::transaction(function () use ($rule, $validated) {
            $rule->update([
                'program' => $validated['program'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'academic_year' => $validated['academic_year'],
            ]);

            $this->syncFees($rule, $validated['fees']);
        });

        AuditService::log('update', 'assessment_rule', $rule->id, $oldData, $rule->fresh()->toArray());

        return redirect()->route('admin.assessmentRules.index')
            ->with('success', 'Assessment rule updated successfully.');
    }

    public function destroy($id)
    {
        $rule = AssessmentRule::findOrFail($id);

        AuditService::log('delete', 'assessment_rule', $rule->id, $rule->toArray(), null);

        $rule->delete();

        return redirect()->route('admin.assessmentRules.index')
            ->with('success', 'Assessment rule deleted successfully.');
    }

    /**
     * Attach the selected fees, snapshotting each fee's current
     * default amount so later catalog changes never rewrite history.
     */
    protected function syncFees(AssessmentRule $rule, array $feeIds): void
    {
        $fees = FeeAssessment::whereIn('id', $feeIds)->get();
        $sync = [];
        foreach ($fees as $fee) {
            $sync[$fee->id] = ['amount' => $fee->default_amount];
        }
        $rule->fees()->sync($sync);
    }
}
