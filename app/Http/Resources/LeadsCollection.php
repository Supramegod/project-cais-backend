<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LeadsCollection extends ResourceCollection
{
    public $collects = LeadsResource::class;

    public function toArray($request)
    {
        return [
            'success' => true,
            'message' => 'Data leads berhasil diambil',
            'data' => $this->collection,
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'total' => $this->total(),
                'total_per_page' => $this->count(),
            ]
        ];
    }
}
