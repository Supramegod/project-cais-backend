<?php

namespace App\DTO;

use App\Models\Quotation;

class QuotationCalculationResult
{
    public $quotation;
    public $calculation_summary;
    public $detail_calculations = [];
    
    public function __construct(Quotation $quotation)
    {
        $this->quotation = $quotation;
        $this->calculation_summary = new CalculationSummary();
    }
}