<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UserEmailConfig\UserEmailConfigSaveRequest;
use App\Models\UserEmailConfig;
use App\Services\DynamicMailerService;

class UserEmailConfigController extends Controller
{
    protected $dynamicMailerService;

    public function __construct(DynamicMailerService $dynamicMailerService)
    {
        $this->dynamicMailerService = $dynamicMailerService;

    }

    /**
     * @OA\Get(
     *     path="/api/user/list",
     *     summary="Get user email configuration",
     *     tags={"User Email Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="email_host", type="string"),
     *                 @OA\Property(property="email_port", type="integer"),
     *                 @OA\Property(property="email_username", type="string"),
     *                 @OA\Property(property="email_encryption", type="string"),
     *                 @OA\Property(property="email_from_address", type="string"),
     *                 @OA\Property(property="email_from_name", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string"),
     *                 @OA\Property(property="updated_at", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getConfig(): JsonResponse
    {
        $user = Auth::user();
        $config = $user->emailConfig;

        if (!$config) {
            // Preserve legacy shape: data key present and explicitly null.
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No email configuration found',
            ]);
        }

        // Hide encrypted password
        $config->makeHidden(['email_password']);

        return $this->successResponse($config);
    }

    /**
     * @OA\Post(
     *     path="/api/user/add",
     *     summary="Save or update user email configuration",
     *     tags={"User Email Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email_host", "email_port", "email_username", "email_password"},
     *             @OA\Property(property="email_host", type="string", example="smtp.gmail.com"),
     *             @OA\Property(property="email_port", type="integer", example=587),
     *             @OA\Property(property="email_username", type="string", example="user@gmail.com"),
     *             @OA\Property(property="email_password", type="string", example="yourpassword"),
     *             @OA\Property(property="email_encryption", type="string", example="tls"),
     *             @OA\Property(property="email_from_address", type="string", example="user@gmail.com"),
     *             @OA\Property(property="email_from_name", type="string", example="John Doe"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email configuration saved successfully")
     *         )
     *     )
     * )
     */
    public function saveConfig(UserEmailConfigSaveRequest $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->only([
            'email_host',
            'email_port',
            'email_username',
            'email_password',
            'email_encryption',
            'email_from_address',
            'email_from_name',
            'is_active'
        ]);

        $data['user_id'] = $user->id;
        $data['email_encryption'] = $data['email_encryption'] ?? 'tls';
        $data['is_active'] = $data['is_active'] ?? true;

        if (empty($data['email_from_address'])) {
            $data['email_from_address'] = $data['email_username'];
        }

        if (empty($data['email_from_name'])) {
            $data['email_from_name'] = $user->full_name ?? $user->name;
        }

        // Cek apakah sudah ada konfigurasi
        $existingConfig = UserEmailConfig::where('user_id', $user->id)->first();

        if ($existingConfig) {
            // Set satu per satu agar mutator bekerja
            $existingConfig->email_host = $data['email_host'];
            $existingConfig->email_port = $data['email_port'];
            $existingConfig->email_username = $data['email_username'];
            $existingConfig->email_password = $data['email_password']; // mutator encrypt di sini
            $existingConfig->email_encryption = $data['email_encryption'];
            $existingConfig->email_from_address = $data['email_from_address'];
            $existingConfig->email_from_name = $data['email_from_name'];
            $existingConfig->is_active = $data['is_active'];
            $existingConfig->save();
            $config = $existingConfig;
        } else {
            $config = UserEmailConfig::create($data);
        }

        Log::info('Email configuration saved for user', [
            'user_id' => $user->id,
            'config_id' => $config->id
        ]);

        // Clear password dari response
        $responseData = $config->toArray();
        unset($responseData['email_password']);

        return $this->successResponse($responseData, 'Email configuration saved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/user/test",
     *     summary="Test SMTP connection",
     *     tags={"User Email Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SMTP connection successful")
     *         )
     *     )
     * )
     */
    public function testConnection(): JsonResponse
    {
        $user = Auth::user();
        $config = $user->emailConfig;

        if (!$config || !$config->isComplete()) {
            return $this->errorResponse('Email configuration not found or incomplete', 400);
        }

        $result = $this->dynamicMailerService->testConnection($config);

        return response()->json($result);
    }


}
