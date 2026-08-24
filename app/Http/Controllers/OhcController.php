<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;


class OhcController extends Controller
{
    private $jenisOhc = [6, 7, 8];

    /**
     * @OA\Get(
     *     path="/api/ohc/list",
     *     summary="Get semua data barang OHC",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function list()
    {
        try {
            $data = Barang::with('jenisBarang')
                ->whereIn('jenis_barang_id', $this->jenisOhc)
                ->whereNull('deleted_at')
                ->get();

            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Error: ' . $e->getMessage());
        }
    }
}