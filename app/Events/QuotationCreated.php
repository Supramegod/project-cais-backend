<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationCreated
{
    use Dispatchable, SerializesModels;

    public $quotation;
    public $request;
    public $tipeQuotation;
    public $quotationReferensi;
    public $user;

    public function __construct(Quotation $quotation, Request $request, string $tipeQuotation, $quotationReferensi, $user)
    {
        $this->quotation = $quotation;
        $this->request = $request;
        $this->tipeQuotation = $tipeQuotation;
        $this->quotationReferensi = $quotationReferensi;
        $this->user = $user;
    }
}