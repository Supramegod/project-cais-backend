<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;


class KaporlapController extends Controller
{
    private $jenisKaporlap = [1, 2, 3, 4, 5];

    /**
     * @OA\Get(
     *     path="/api/kaporlap/list",
     *     summary="Get semua data barang Kaporlap",
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
                ->whereIn('jenis_barang_id', $this->jenisKaporlap)
                ->whereNull('deleted_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}