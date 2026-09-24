<?php

namespace App\Services;

use App\Models\AiPaymentRecommendation;
use App\Models\StudentAccount;

class BudgetPlannerService
{
    /**
     * Simple percentage-based spending allocation for personal planning.
     * Pure calculation: no database writes, no changes to official records.
     */
    public const ALLOCATION_RATES = [
        'school_expenses' => 0.40,
        'daily_expenses' => 0.30,
        'transportation' => 0.15,
        'savings' => 0.10,
        'emergency_fund' => 0.05,
    ];

    public static function generateAllocation(float $amount): array
    {
        $amount = round($amount, 2);
        $items = [];
        $runningTotal = 0;

        $rates = self::ALLOCATION_RATES;
        $lastKey = array_key_last($rates);

        foreach ($rates as $key => $rate) {
            if ($key === $lastKey) {
                // Assign the remainder so the total always equals the budget exactly.
                $value = round($amount - $runningTotal, 2);
            } else {
                $value = round($amount * $rate, 2);
                $runningTotal += $value;
            }
            $items[$key] = $value;
        }

        return [
            'items' => $items,
            'rates' => $rates,
            'total' => $amount,
        ];
    }

    public static function generateRecommendation($student, $data)
    {
        $allowanceFrequency = $data['allowance_frequency'];
        $allowanceAmount = $data['allowance_amount'];
        $outstandingBalance = $data['outstanding_balance'];
        $weeksBeforeExams = $data['weeks_before_exams'] ?? 8;
        $declaredExpenses = $data['declared_expenses'] ?? 0;
        
        // Calculate weekly allowance equivalent
        $weeklyAllowance = self::calculateWeeklyAllowance($allowanceFrequency, $allowanceAmount);
        
        // Available amount per week after declared expenses
        $availablePerWeek = $weeklyAllowance - $declaredExpenses;
        if ($availablePerWeek <= 0) {
            $availablePerWeek = $weeklyAllowance * 0.5;
        }
        
        // Ensure minimum allocation
        $weeksNeeded = ceil($outstandingBalance / max($availablePerWeek, 1));
        $weeksToUse = max($weeksNeeded, $weeksBeforeExams);
        
        // Calculate recommended amount per payment
        $recommendedAmount = round($outstandingBalance / $weeksToUse, 2);
        
        // Ensure recommended amount doesn't exceed what student can afford
        if ($recommendedAmount > $availablePerWeek) {
            $recommendedAmount = round($availablePerWeek, 2);
            $weeksToUse = ceil($outstandingBalance / $recommendedAmount);
        }
        
        // Minimum payment threshold
        if ($recommendedAmount < 100) {
            $recommendedAmount = 100;
            $weeksToUse = ceil($outstandingBalance / $recommendedAmount);
        }
        
        $projectedDate = now()->addWeeks($weeksToUse);
        
        $remainingBalance = $outstandingBalance - ($recommendedAmount * $weeksToUse);
        if ($remainingBalance < 0) {
            $remainingBalance = 0;
        }
        
        // Build weekly schedule
        $schedule = [];
        $runningBalance = $outstandingBalance;
        for ($week = 1; $week <= $weeksToUse; $week++) {
            $paymentAmount = min($recommendedAmount, $runningBalance);
            $runningBalance -= $paymentAmount;
            $schedule[] = [
                'week' => $week,
                'amount' => round($paymentAmount, 2),
                'date' => now()->addWeeks($week)->format('Y-m-d'),
                'remaining' => max(round($runningBalance, 2), 0),
            ];
            if ($runningBalance <= 0) break;
        }
        
        $explanation = "Based on your " . str_replace('_', ' ', $allowanceFrequency) . " allowance of ₱" . number_format($allowanceAmount, 2) . ", ";
        $explanation .= "we recommend paying ₱" . number_format($recommendedAmount, 2) . " per week ";
        $explanation .= "over $weeksToUse weeks to clear your outstanding balance of ₱" . number_format($outstandingBalance, 2) . ". ";
        if ($declaredExpenses > 0) {
            $explanation .= "After accounting for your declared expenses of ₱" . number_format($declaredExpenses, 2) . ", ";
        }
        $explanation .= "This plan will achieve financial clearance by " . $projectedDate->format('F d, Y') . ".";
        
        // Save recommendation
        $recommendation = AiPaymentRecommendation::create([
            'student_id' => $student->id,
            'allowance_frequency' => $allowanceFrequency,
            'allowance_amount' => $allowanceAmount,
            'recommended_amount' => $recommendedAmount,
            'recommended_date' => $projectedDate,
            'explanation' => $explanation,
        ]);
        
        return [
            'recommendation' => $recommendation,
            'schedule' => $schedule,
            'recommended_amount' => $recommendedAmount,
            'weeks' => $weeksToUse,
            'remaining_balance' => $remainingBalance,
            'projected_date' => $projectedDate,
            'explanation' => $explanation,
        ];
    }
    
    private static function calculateWeeklyAllowance($frequency, $amount)
    {
        return match($frequency) {
            'daily' => $amount * 7,
            'weekly' => $amount,
            'bi_weekly' => $amount / 2,
            'monthly' => $amount / 4,
            default => $amount,
        };
    }
}
