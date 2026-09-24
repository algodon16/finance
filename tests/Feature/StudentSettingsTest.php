<?php

namespace Tests\Feature;

use App\Models\FinancialCharge;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\PaymentReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function studentUser(): User
    {
        return User::where('role', 'student')->whereHas('student')->firstOrFail();
    }

    public function test_settings_page_shows_read_only_info_with_only_email_and_password_editable(): void
    {
        $user = $this->studentUser();
        $student = $user->student;

        $response = $this->actingAs($user)->get(route('student.settings.index'));
        $response->assertOk();
        $response->assertSee($student->student_number);
        $response->assertSee($student->first_name);
        $response->assertSee($student->program);

        // Only these inputs may exist: email + 3 password fields (+ hidden _token/_method).
        preg_match_all('/<input[^>]+name="([^"]+)"/', $response->getContent(), $m);
        $names = array_unique($m[1]);
        sort($names);
        $this->assertEquals(
            ['_token', 'current_password', 'email', 'password', 'password_confirmation'],
            array_values(array_filter($names, fn ($n) => $n !== '_method'))
        );
        $this->assertStringNotContainsString('<textarea', $response->getContent());
    }

    public function test_extra_fields_cannot_mass_update_read_only_info(): void
    {
        $user = $this->studentUser();
        $originalFirst = $user->student->first_name;

        $this->actingAs($user)->put(route('student.settings.email.update'), [
            'email' => $user->email,
            'first_name' => 'Hacked',
            'program' => 'Hacked',
        ]);

        $this->assertEquals($originalFirst, $user->student->fresh()->first_name);
    }

    public function test_email_update_validates_and_saves(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->put(route('student.settings.email.update'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $other = User::where('id', '!=', $user->id)->firstOrFail();
        $this->actingAs($user)->put(route('student.settings.email.update'), ['email' => $other->email])
            ->assertSessionHasErrors('email');

        $response = $this->actingAs($user)->put(
            route('student.settings.email.update'),
            ['email' => 'newstudentmail@example.com']
        );
        $response->assertSessionHas('success');
        $this->assertEquals('newstudentmail@example.com', $user->fresh()->email);
    }

    public function test_password_change_requires_current_and_confirmation_and_hashes(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->put(route('student.settings.password.update'), [
            'current_password' => 'definitely-wrong-password',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('student.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'DifferentPass123',
        ])->assertSessionHasErrors('password');

        // Seed passwords are unknown; set a known one directly, then change via the form.
        $user->update(['password' => Hash::make('OldPass1234')]);

        $response = $this->actingAs($user)->put(route('student.settings.password.update'), [
            'current_password' => 'OldPass1234',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ]);
        $response->assertSessionHas('success', 'Password updated successfully.');
        $this->assertTrue(Hash::check('NewSecurePass123', $user->fresh()->password));
        $this->assertStringStartsWith('$2y$', $user->fresh()->password); // bcrypt hash, never plain text

        // Login works with the new password (session still valid = not logged out).
        $this->assertTrue(Auth::attempt(['email' => $user->email, 'password' => 'NewSecurePass123']));
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_payment_reminder_uses_updated_email(): void
    {
        config(['mail.default' => 'log']);
        // Isolate from other seed charges: only the 1-day window under test.
        NotificationSetting::set('payment_reminders.overdue', '0');
        // Pick whichever student currently carries an outstanding balance.
        $user = User::where('role', 'student')
            ->whereHas('student.studentAccount', fn ($q) => $q->where('outstanding_balance', '>', 0))
            ->firstOrFail();
        $student = $user->student;
        $user->update(['email' => 'reminder-target@example.com']);

        $charge = FinancialCharge::create([
            'student_id' => $student->id,
            'financial_category_id' => null,
            'description' => 'TEST Settings Reminder Charge',
            'amount' => 4900,
            'due_date' => Carbon::today()->addDay()->toDateString(),
            'status' => 'active',
        ]);

        $stats = (new PaymentReminderService())->run(Carbon::today());

        $this->assertEquals(1, $stats['sent']);
        $log = NotificationLog::where('financial_charge_id', $charge->id)->firstOrFail();
        $this->assertEquals('reminder-target@example.com', $log->recipient_email);
        $this->assertTrue(
            Notification::where('user_id', $user->id)->where('title', 'Payment Reminder')->exists()
        );
    }
}
