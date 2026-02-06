<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    public $collects = CustomerResource::class;

    public function toArray($request)
    {
        return [
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $this->collection,
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'total' => $this->total(),
                'total_per_page' => $this->count(),
            ]
        ];
    }
      public function paginationInformation($request, $paginated, $default)
    {
        return [];
    }
}
