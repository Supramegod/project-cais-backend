<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
        try {
            $user = Auth::user();
            $config = $user->emailConfig;

            if (!$config) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No email configuration found'
                ]);
            }

            // Hide encrypted password
            $config->makeHidden(['email_password']);

            return response()->json([
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting email config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get email configuration'
            ], 500);
        }
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
    // app/Http/Controllers/UserEmailConfigController.php

    public function saveConfig(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email_host' => 'required|string',
            'email_port' => 'required|integer|min:1|max:65535',
            'email_username' => 'required|email',
            'email_password' => 'required|string',
            'email_encryption' => 'nullable|string|in:tls,ssl',
            'email_from_address' => 'nullable|email',
            'email_from_name' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean'
        ], [
            'email_host.required' => 'SMTP host wajib diisi',
            'email_port.required' => 'SMTP port wajib diisi',
            'email_username.required' => 'Username/email wajib diisi',
            'email_username.email' => 'Format username/email tidak valid',
            'email_password.required' => 'Password SMTP wajib diisi'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Email configuration saved successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving email config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save email configuration: ' . $e->getMessage()
            ], 500);
        }
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
        try {
            $user = Auth::user();
            $config = $user->emailConfig;

            if (!$config || !$config->isComplete()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email configuration not found or incomplete'
                ], 400);
            }

            $result = $this->dynamicMailerService->testConnection($config);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error testing SMTP connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to test SMTP connection: ' . $e->getMessage()
            ], 500);
        }
    }


}