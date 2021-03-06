<?php

namespace Maestroprog\CreditCalculator;

class Calculator
{
    private $credit;
    private $percent;
    private $period;

    public function __construct(int $credit, float $percent, Period $period)
    {
        $this->credit = $credit;
        $this->percent = $percent;
        $this->period = $period;
    }

    /**
     * Рассчитать график платежей при неизменяющеся сумме платежа.
     *
     * @param int $monthlyPay
     *
     * @return PaymentPeriod
     */
    public function paymentSchedule(int $monthlyPay): PaymentPeriod
    {
        $period = new PaymentPeriod();
        $credit = $this->credit;
        foreach ($this->period->each() as $nextPeriod) {
            if ($credit <= 0) {
                break;
            }
            // итерируем пока не прошли 60 месяцев и пока кредит больше 0
            $percentYearAmount = $credit * $this->percent / 100; // проценты набежавшие за год
            $currentMonth = $nextPeriod->getFrom();
            $nextMonth = $nextPeriod->getTo();
            $percentMonthAmount = $percentYearAmount / 365 *
                ($nextMonth->getTimestamp() - $currentMonth->getTimestamp()) / 86400; // проценты набежавшие за месяц

            $payment = min($monthlyPay, $credit + $percentMonthAmount);

            $paymentObject = new Payment(
                $nextMonth,
                $payment,
                $percentMonthAmount,
                $payment - $percentMonthAmount
            );

            $credit -= ($payment - $percentMonthAmount); // вычитаем из суммы долга платеж без учета процентов

            $period->addPayment($paymentObject);
        }

        return $period;
    }

    /**
     * Рассчитать график платежей на определённый период.
     *
     * @param Period $paymentPeriod
     * @param int $monthlyPay
     *
     * @return PaymentPeriod
     */
    public function paymentScheduleShortPeriod(Period $paymentPeriod, int $monthlyPay): PaymentPeriod
    {
        $period = new PaymentPeriod();
        $credit = $this->credit;
        foreach ($paymentPeriod->each() as $nextPeriod) {
            if ($credit <= 0) {
                break;
            }
            // итерируем пока не прошли 60 месяцев и пока кредит больше 0
            $percentYearAmount = $credit * $this->percent / 100; // проценты набежавшие за год
            $currentMonth = $nextPeriod->getFrom();
            $nextMonth = $nextPeriod->getTo();
            $percentMonthAmount = $percentYearAmount / 365 *
                ($nextMonth->getTimestamp() - $currentMonth->getTimestamp()) / 86400; // проценты набежавшие за месяц

            $payment = min($monthlyPay, $credit + $percentMonthAmount);

            $paymentObject = new Payment(
                $nextMonth,
                $payment,
                $percentMonthAmount,
                $payment - $percentMonthAmount
            );

            $credit -= ($payment - $percentMonthAmount); // вычитаем из суммы долга платеж без учета процентов

            $period->addPayment($paymentObject);
        }

        return $period;
    }

    /**
     * Применяет период платежей к данному калькулятору для вычисления остатка задолженности.
     *
     * @param PaymentPeriod $period
     *
     * @return void
     */
    public function applyPaymentPeriod(PaymentPeriod $period)
    {
        foreach ($period->getPayments() as $payment) {
            $this->credit -= $payment->getDebtRepaymentAmount();
        }
        if (isset($payment)) {
            $this->period = new Period($payment->getDate(), $this->period->getTo());
        }
    }

    public function getCredit(): float
    {
        return $this->credit;
    }

    public function getMinimalPayment($credit, $period, $percent): float
    {
        $remainingCredit = $credit;
        $percentTotalAmount = 0;
        $amount = 0;
        for ($i = $period; $i > 0; $i--) {
            $percentYearAmount = $credit * $percent / 100; // процент который набежит за год
            $currentMonth = $this->period->getFrom()->modify(($period - $i) . ' month');
            $nextMonth = $currentMonth->modify('1 month');
            $percentMonthAmount = $percentYearAmount / 365 *
                ($nextMonth->getTimestamp() - $currentMonth->getTimestamp()) / 86400; // проценты набежавшие за месяц
            $percentTotalAmount += $percentMonthAmount;

            $result = ($credit + $percentTotalAmount) / $period; // кол-во денег, которое придется платить весь период
            $amount += $result;
        }

        $preResult = $amount / $period;

        $amount = 0;

        // корректировка с учётом получившегося промежуточного минимального платежа
        $percentTotalAmount = 0;
        for ($i = $period; $i > 0; $i--) {
            $percentYearAmount = $credit * $percent / 100; // процент который набежит за год
            $currentMonth = $this->period->getFrom()->modify(($period - $i) . ' month');
            $nextMonth = $currentMonth->modify('1 month');
            $percentMonthAmount = $percentYearAmount / 365 *
                ($nextMonth->getTimestamp() - $currentMonth->getTimestamp()) / 86400; // проценты набежавшие за месяц
            $percentTotalAmount += $percentMonthAmount;

            $perMonth = $preResult - $percentMonthAmount;
            $credit -= $perMonth;
            $amount += $preResult;
        }

        if ($credit > 0) {
            $result = ($amount + $credit) / $period;
        } else {
            $result = min($amount / $period, $remainingCredit);
        }

        if ($result > 10000) {
            $result = (floor($result / 1000) * 1000);
        } elseif ($result > 1000) {
            $result = (floor($result / 100) * 100);
        }

        return $result;
    }
}
