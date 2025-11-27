<?php

namespace App\DTO;

class DetailCalculation
{
    public $detail_id;
    public $hpp_data = [];
    public $coss_data = [];
    
    public function __construct($detail_id)
    {
        $this->detail_id = $detail_id;
    }
}
