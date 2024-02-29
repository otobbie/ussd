<?php

include_once 'apps/Easytax.php';

/**
 * Description of TaxCalculator
 *
 * @author Chuks
 */
class TaxCalculator extends Easytax {

	private $mintaxrate = 1.0;
	private $tax = 0.00;
	private $payable = 0;
	private $pensionpct = 7.5;
	private $nhfpct = 2.5;

	/**
	 * Calculate tax for both self and payee
	 *
	 * @param type $arr
	 * @return type
	 */
	public function calculateTax($arr) {
		switch ($arr['currentStep']) {
			case 1:
				return $this->result('continue', $this->render('calculator_options'));
			default:
				switch ($arr['userInputs'][1]) {
					case 1:
						return $this->calculateSelfAssessment($arr);
					case 2:
						return $this->calculatePayee($arr);
				}
		}
	}

	/**
	 * Self assessment tax
	 *
	 * @param type $arr
	 * @return type
	 */
	public function calculateSelfAssessment($arr) {
		switch ($arr['currentStep']) {
			case 2:
				$amount = is_numeric($arr['amount']) ? $arr['amount'] : 0.00;
				// Calculate tax
				$this->payable = $amount > 0 ? $amount * (($this->mintaxrate * 12) / 100) : $this->tax;

				return $this->result('end', $this->render('self_assessment') . "\n"
						. $this->render('lbl_monthly_income') . 'NGN' . number_format($amount > 0 ? $amount : 0.00, 2) . "\n"
						. $this->render('lbl_min_tax') .'NGN'. number_format($this->payable, 2) . "\n"
						. $this->render('lbl_min_tax_rate') . number_format($this->mintaxrate * 12, 2) . "%");
		}
	}

	/**
	 * P.A.Y.E
	 *
	 * @param type $arr
	 * @return type
	 */
	public function calculatePayee($arr) {
		$heading = $this->render('PAYE') ."\n";

		switch ($arr['currentStep']) {
			case 2:
				$paye = $this->computePaye($arr['amount']);
				// return $this->result('end', $this->render('lbl_estimated') . $heading . "Monthly Income: NGN" . number_format($arr['amount']) .
				// 	"\n" . $this->render('lbl_gross') . number_format($paye['gross'], 2) .
				// 	"\n" . $this->render('lbl_takehome') . number_format($paye['net'], 2) .
				// 	"\n" . $this->render('lbl_tax') . number_format($paye['tax'], 2) .
				// 	"\n" . $this->render('lbl_deductions') . number_format($paye['deductions'], 2) .
				// 	"\n" . $this->render('lbl_freepay') . number_format($paye['tax_free'], 2) .
				// 	"\n" . $this->render('lbl_pension') . number_format($paye['pension_pct'], 2) .
				// 	"\n" . $this->render('lbl_nhf') . number_format($paye['nhf_pct'], 2) .
				// 	"\n" . $this->render('lbl_tax_rate') . number_format($paye['tax_rate'], 2));

				return $this->result('end', $this->render('lbl_estimated') . $heading . "Income/mo:N" . number_format($arr['amount']) .
					"\n" . $this->render('lbl_gross') .'N'. number_format($paye['gross'], 2).
					"\n" . $this->render('lbl_takehome') .'N'. number_format($paye['net'], 2) .
					"\n" . $this->render('lbl_tax') .'N'. number_format($paye['tax'], 2) .
					"\n" . $this->render('lbl_deductions') .'N'. number_format($paye['deductions'], 2) .
					"\n" . $this->render('lbl_freepay') .'N'. number_format($paye['tax_free'], 2) .
					"\n" . $this->render('lbl_pension') . number_format($paye['pension_pct'], 2) .
					"\n" . $this->render('lbl_nhf') . number_format($paye['nhf_pct'], 2) .
					"\n" . $this->render('lbl_tax_rate') . number_format($paye['tax_rate'], 2)
				);
		}
	}

	private function computePaye($amount) {
		$monthly = is_numeric($amount) ? $amount : 0.00;
		$annual = $monthly * 12;
		$income = $annual;
		$excemption = 0;
		$taxable = 0;

		if ($annual <= 0) {
			return 0;
		}

		$relief = $this->taxRelief($annual);
		$pension = ($this->pensionpct * $annual) / 100;
		$nhf = ($this->nhfpct * $annual) / 100;

		$excemption += $pension + $nhf + $relief;

		if ($annual <= $excemption) {
            $excemption = $income;
        } else {
            $taxable = $income - $excemption;
        }

		$p1 = min(300000, $taxable) * (7 / 100);
        $p2 = ($taxable >= 300000) ? min(300000, $taxable - 300000) * (11 / 100) : 0;
        $p3 = ($taxable >= 600000) ? min(500000, $taxable - 600000) * (15 / 100) : 0;
        $p4 = ($taxable >= 1100000) ? min(500000, $taxable - 1100000) * (19 / 100) : 0;
        $p5 = ($taxable >= 1600000) ? min(1600000, $taxable - 1600000) * (21 / 100) : 0;
        $p6 = ($taxable >= 3200000) ? ($taxable * (24 / 100)) : 0;
        // $tax = $p1 + $p2 + $p3 + $p4 + $p5 + $p6;
		$tax = $p1 + $p2 + $p3 + $p4 + $p5 +$p6;

        $deduction = $pension + $nhf + $tax;
        $net = $income - $deduction;
        $tax_rate = 100 * ($tax / $income);

        return [
            'gross' => $monthly / 12,
            'net' => $net / 12,
            'tax_free' => $excemption / 12,
            'taxable' => $taxable / 12,
            'tax' => $tax / 12,
            'pension_pct' => $this->pensionpct,
            'nhf_pct' => $this->nhfpct,
            'tax_rate' => $tax_rate,
            'deductions' => $deduction / 12,
			'relief' => $relief / 12
        ];
	}

	private function taxRelief($annual) {
        return ($annual * 0.2) + 200000;
    }

}
