<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\StudentAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        $students = $query->latest()->paginate(20);

        return view('admin.students.index', compact('students'));
    }

    public function create()
    {
        return view('admin.students.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'student_number' => 'required|string|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'program' => 'required|string|max:255',
            'year_level' => 'required|integer|min:1|max:6',
            'section' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'student_number' => $validated['student_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'program' => $validated['program'],
            'year_level' => $validated['year_level'],
            'section' => $validated['section'] ?? null,
        ]);

        StudentAccount::create([
            'student_id' => $student->id,
            'total_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 0,
            'clearance_status' => 'cleared',
        ]);

        AuditService::log('create', 'student', $student->id, null, $student->toArray());

        return redirect()->route('admin.students.index')
            ->with('success', 'Student created successfully.');
    }

    public function show($id)
    {
        $student = Student::with(['user', 'studentAccount', 'financialCharges', 'payments'])->findOrFail($id);

        return view('admin.students.show', compact('student'));
    }

    public function edit($id)
    {
        $student = Student::with('user')->findOrFail($id);

        return view('admin.students.edit', compact('student'));
    }

    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $student->user_id,
            'student_number' => 'required|string|unique:students,student_number,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'program' => 'required|string|max:255',
            'year_level' => 'required|integer|min:1|max:6',
            'section' => 'nullable|string|max:50',
        ]);

        $oldData = $student->toArray();

        $student->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $student->update([
            'student_number' => $validated['student_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'program' => $validated['program'],
            'year_level' => $validated['year_level'],
            'section' => $validated['section'] ?? null,
        ]);

        AuditService::log('update', 'student', $student->id, $oldData, $student->toArray());

        return redirect()->route('admin.students.index')
            ->with('success', 'Student updated successfully.');
    }
}
