<?php

namespace App\Events;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $quotation;
    public $requestData;  
    public $tipeQuotation;
    public $quotationReferensi;
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Quotation $quotation,
        array $requestData,  
        string $tipeQuotation,
        $quotationReferensi = null,
        User $user = null
    ) {
        $this->quotation = $quotation;
        $this->requestData = $requestData;
        $this->tipeQuotation = $tipeQuotation;
        $this->quotationReferensi = $quotationReferensi;
        $this->user = $user;
    }
}