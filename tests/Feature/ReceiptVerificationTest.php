<?php

namespace Tests\Feature;

use App\Models\AccountLedger;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function studentUser(): User
    {
        return User::where('role', 'student')->whereHas('student')->firstOrFail();
    }

    protected function receiptImage(string $ref, bool $blur): string
    {
        $w = 800;
        $h = 500;
        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 20, 20, 20);
        $blue = imagecolorallocate($im, 26, 86, 219);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);
        imagestring($im, 5, 60, 60, 'GCash Payment Receipt', $blue);
        imagestring($im, 5, 60, 140, 'Reference No.:', $black);
        imagestring($im, 5, 60, 180, $ref, $black);
        imagestring($im, 5, 60, 260, 'Amount: P500.00', $black);
        imagestring($im, 5, 60, 300, 'Date: 2026-09-07', $black);
        imagestring($im, 5, 60, 380, 'Thank you for your payment.', $black);
        if ($blur) {
            $small = imagecreatetruecolor(100, 62);
            imagecopyresampled($small, $im, 0, 0, 0, 0, 100, 62, $w, $h);
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
            imagecopyresampled($im, $small, 0, 0, 0, 0, $w, $h, 100, 62);
            imagedestroy($small);
        }
        $big = imagecreatetruecolor($w * 2, $h * 2);
        imagecopyresampled($big, $im, 0, 0, 0, 0, $w * 2, $h * 2, $w, $h);
        $path = tempnam(sys_get_temp_dir(), 'sfms') . '.png';
        imagepng($big, $path);
        imagedestroy($im);
        imagedestroy($big);

        return $path;
    }

    protected function payload(string $ref, string $imagePath, float $amount = 500.00): array
    {
        return [
            'amount' => $amount,
            'payment_method' => 'gcash',
            'reference_number' => $ref,
            'payment_date' => date('Y-m-d'),
            'description' => 'Test payment',
            'proof_of_payment' => new UploadedFile($imagePath, 'receipt.png', 'image/png', null, true),
        ];
    }

    public function test_clear_receipt_matching_reference_is_automatically_verified(): void
    {
        $user = $this->studentUser();
        $account = StudentAccount::where('student_id', $user->student->id)->first();
        $paidBefore = $account ? (float) $account->total_paid : 0;

        $img = $this->receiptImage('AUTO1TEST111', false);

        $response = $this->actingAs($user)->post(
            route('student.payments.store'),
            $this->payload('AUTO1TEST111', $img)
        );

        $response->assertRedirect(route('student.payments.index'));
        $response->assertSessionHas('success', 'Payment verified successfully. Your payment has been automatically verified.');

        $payment = Payment::where('reference_number', 'AUTO1TEST111')->latest('id')->firstOrFail();
        $this->assertEquals('approved', $payment->status);
        $this->assertEquals('matched', $payment->reference_match_status);
        $this->assertEquals('AUTO1TEST111', $payment->reference_ocr_result);
        $this->assertNotNull($payment->verified_at);

        // Same side effects as cashier approval.
        $this->assertTrue(AccountLedger::where('payment_id', $payment->id)->exists());
        if ($account) {
            $this->assertEquals($paidBefore + 500.00, (float) $account->fresh()->total_paid);
        }
        $this->assertTrue(
            Notification::where('user_id', $user->id)->where('title', 'Payment Approved')->exists()
        );

        @unlink($img);
    }

    public function test_clear_receipt_wrong_reference_is_rejected(): void
    {
        $user = $this->studentUser();
        $img = $this->receiptImage('AUTO2TEST222', false);

        $response = $this->actingAs($user)->post(
            route('student.payments.store'),
            $this->payload('AUTO2TEST223', $img)
        );

        $response->assertSessionHasErrors('reference_number');
        $this->assertFalse(Payment::where('reference_number', 'AUTO2TEST223')->exists());

        @unlink($img);
    }

    public function test_blurry_receipt_goes_to_manual_review_without_approval(): void
    {
        $user = $this->studentUser();
        $img = $this->receiptImage('AUTO3TEST333', true);

        $response = $this->actingAs($user)->post(
            route('student.payments.store'),
            $this->payload('AUTO3TEST333', $img)
        );

        $response->assertRedirect(route('student.payments.index'));
        $response->assertSessionHas('success', 'Payment submitted for manual review. We could not reliably read the reference number from the receipt.');

        $payment = Payment::where('reference_number', 'AUTO3TEST333')->latest('id')->firstOrFail();
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('unreadable', $payment->reference_match_status);
        $this->assertFalse(AccountLedger::where('payment_id', $payment->id)->exists());

        @unlink($img);
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        $user = $this->studentUser();
        $img = $this->receiptImage('AUTO4TEST444', false);

        $this->actingAs($user)->post(
            route('student.payments.store'),
            $this->payload('AUTO4TEST444', $img)
        );

        $this->assertEquals('approved', Payment::where('reference_number', 'AUTO4TEST444')->latest('id')->firstOrFail()->status);

        $img2 = $this->receiptImage('AUTO4TEST444', false);
        $response = $this->actingAs($user)->post(
            route('student.payments.store'),
            $this->payload('AUTO4TEST444', $img2)
        );

        $response->assertSessionHasErrors('reference_number');
        $this->assertEquals(1, Payment::where('reference_number', 'AUTO4TEST444')->count());

        @unlink($img);
        @unlink($img2);
    }

    public function test_approved_payment_follows_existing_edit_rules(): void
    {
        $user = $this->studentUser();
        $payment = Payment::where('student_id', $user->student->id)
            ->where('status', 'approved')
            ->latest('id')
            ->first();

        if (! $payment) {
            $img = $this->receiptImage('AUTO5TEST555', false);
            $this->actingAs($user)->post(route('student.payments.store'), $this->payload('AUTO5TEST555', $img));
            @unlink($img);
            $payment = Payment::where('reference_number', 'AUTO5TEST555')->latest('id')->first();
        }

        // Deterministic fallback: live Tesseract OCR occasionally misreads
        // the generated receipt image (environment-dependent), in which case
        // the store request is legitimately rejected and no payment exists.
        // Create the approved payment directly so the edit-rule assertions
        // below still exercise their intended subject. OCR accuracy itself
        // is covered by the other tests in this class.
        if (! $payment) {
            $payment = Payment::create([
                'student_id' => $user->student->id,
                'amount' => 500.00,
                'payment_method' => 'gcash',
                'reference_number' => 'AUTO5TEST555',
                'payment_date' => date('Y-m-d'),
                'description' => 'Test payment',
                'status' => 'approved',
                'reference_match_status' => 'matched',
                'verified_at' => now(),
            ]);
        }

        // Existing rule: verified payments cannot be edited.
        $response = $this->actingAs($user)->put(route('student.payments.update', $payment->id), [
            'amount' => 999.00,
            'payment_method' => 'gcash',
            'reference_number' => $payment->reference_number,
            'payment_date' => date('Y-m-d'),
        ]);

        $response->assertRedirect(route('student.payments.index'));
        $response->assertSessionHas('error');
        $this->assertNotEquals(999.00, (float) $payment->fresh()->amount);
    }
}
