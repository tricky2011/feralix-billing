<?php

namespace Tests\Unit\Services\Billing;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DueDateClampingTest extends TestCase
{
    public function test_due_date_calculation_february_day_28(): void
    {
        // Simulasi: billing Feb 2025, invoice Feb 1, install day = 28
        $invoiceDate = Carbon::parse('2025-02-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 28;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2025-02-28', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_february_day_29_clamped(): void
    {
        // Simulasi: billing Feb 2025, invoice Feb 1, install day = 29 -> clamp to 28
        $invoiceDate = Carbon::parse('2025-02-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 29;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2025-02-28', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_february_day_31_clamped(): void
    {
        // Simulasi: billing Feb 2025, invoice Feb 1, install day = 31 -> clamp to 28
        $invoiceDate = Carbon::parse('2025-02-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 31;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2025-02-28', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_march_day_31_not_clamped(): void
    {
        // Simulasi: billing Maret 2026, invoice Mar 1, install day = 31 (March has 31)
        $invoiceDate = Carbon::parse('2026-03-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 31;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2026-03-31', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_april_day_31_clamped(): void
    {
        // Simulasi: billing April 2026, invoice Apr 1, install day = 31 -> clamp to 30
        $invoiceDate = Carbon::parse('2026-04-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 31;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2026-04-30', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_day_1_after_invoice_adds_month(): void
    {
        // Simulasi: billing Mei 2026, invoice May 14, install day = 1 (already passed)
        $invoiceDate = Carbon::parse('2026-05-14');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 1;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2026-06-01', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_calculation_day_15_within_month(): void
    {
        // Simulasi: billing Mei 2026, invoice May 1, install day = 15
        $invoiceDate = Carbon::parse('2026-05-01');
        $billingMonth = $invoiceDate->copy()->startOfMonth();
        $lastDayOfBillingMonth = $billingMonth->copy()->endOfMonth();
        $dayOfMonth = 15;

        $rawDueDate = $billingMonth->copy()->addDays($dayOfMonth - 1);

        $dueDate = $rawDueDate->gt($lastDayOfBillingMonth)
            ? $lastDayOfBillingMonth
            : $rawDueDate;

        if ($dueDate->lt($invoiceDate)) {
            $dueDate->addMonth();
        }

        $this->assertEquals('2026-05-15', $dueDate->format('Y-m-d'));
    }
}