<?php

namespace App\Traits;

/**
 * Envelope response standar aplikasi.
 *
 * Bentuknya sengaja dijaga IDENTIK dengan pola manual yang sudah tersebar
 * di controller (`response()->json(['success' => ...])`) supaya adopsi trait
 * ini murni refactor — tidak menambah/menghapus field pada kontrak API:
 *   - sukses:   { success: true, [message], [data] }   (message/data hanya muncul jika diberikan)
 *   - error:    { success: false, message, [errors] }
 *   - paginasi: { success: true, [message], data, meta }
 */
trait ApiResponser
{
    /**
     * Success response. `message` dan `data` hanya disertakan jika non-null,
     * meniru variasi yang ada (list/view tanpa message, delete tanpa data).
     */
    protected function successResponse($data = null, $message = null, int $statusCode = 200)
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Success response berisi pesan saja (tanpa data) — mis. hasil delete.
     */
    protected function messageResponse(string $message, int $statusCode = 200)
    {
        return response()->json(['success' => true, 'message' => $message], $statusCode);
    }

    /**
     * Created (201) response.
     */
    protected function createdResponse($data = null, string $message = 'Data berhasil dibuat')
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Error response. `errors` hanya disertakan jika diberikan.
     */
    protected function errorResponse(string $message = 'Error', int $statusCode = 400, $errors = null)
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Not found (404) response.
     */
    protected function notFoundResponse(string $message = 'Data tidak ditemukan')
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Unauthorized (401) response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Server error (500) response.
     */
    protected function serverErrorResponse(string $message = 'Terjadi kesalahan server')
    {
        return $this->errorResponse($message, 500);
    }

    /**
     * Paginated response untuk LengthAwarePaginator.
     */
    protected function paginatedResponse($paginator, ?string $message = null)
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        $payload['data'] = $paginator->items();
        $payload['meta'] = [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];

        return response()->json($payload, 200);
    }
}
