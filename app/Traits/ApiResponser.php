<?php

namespace App\Traits;

trait ApiResponser
{
    /**
     * Server error response format
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function serverErrorResponse($message = 'Terjadi kesalahan server')
    {
        return $this->errorResponse($message, [], 500);
    }

    /**
     * Created response format
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createdResponse($data = [], $message = 'Data berhasil dibuat')
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Updated response format
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updatedResponse($data = [], $message = 'Data berhasil diupdate')
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Deleted response format
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deletedResponse($message = 'Data berhasil dihapus')
    {
        return $this->successResponse([], $message, 200);
    }

    /**
     * Paginated response format
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedResponse($data, $message = 'Data berhasil diambil')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
            'timestamp' => now()->toISOString()
        ], 200);
    }
    /**  Success response format
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = [], $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $statusCode);
    }

    /**
     * Error response format
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($message = 'Error', $errors = [], $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ], $statusCode);
    }

    /**
     * Validation error response format
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse($validator)
    {
        return $this->errorResponse(
            'Validasi gagal',
            $validator->errors(),
            422
        );
    }

    /**
     * Not found response format
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse($message = 'Data tidak ditemukan')
    {
        return $this->errorResponse($message, [], 404);
    }

    /**
     * Unauthorized response format
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse($message = 'Unauthorized')
    {
        return $this->errorResponse($message, [], 401);
    }}