<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmailConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DynamicMailerService
{
    /**
     * Setup mailer berdasarkan user
     */
    public function setupMailer(User $user): array
    {
        Log::info('Setting up mailer for user ID: ' . $user->id);
        
        // Cek konfigurasi email user
        $userConfig = UserEmailConfig::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
        
        if ($userConfig && $this->isConfigComplete($userConfig)) {
            Log::info('User has complete email configuration, creating custom mailer');
            return $this->createUserMailer($user, $userConfig);
        }
        
        // Coba gunakan email user sebagai default
        if (!empty($user->email)) {
            Log::info('No user config, trying to use user email as default');
            return $this->createDefaultUserMailer($user);
        }
        
        // Fallback ke sistem default
        Log::info('Falling back to system default mailer');
        return $this->getSystemDefaultMailer();
    }
    
    /**
     * Cek apakah konfigurasi lengkap
     */
    private function isConfigComplete(UserEmailConfig $config): bool
    {
        return $config->isComplete();
    }
    
    /**
     * Buat mailer khusus untuk user
     */
    private function createUserMailer(User $user, UserEmailConfig $config): array
    {
        $mailerName = 'user_' . $user->id . '_smtp';
        
        Log::info("Creating custom mailer '{$mailerName}' for user", [
            'user_id' => $user->id,
            'email' => $config->email_username,
            'host' => $config->email_host
        ]);
        
        try {
            // Gunakan method toMailConfig yang sudah menangani decrypt
            $mailConfig = $config->toMailConfig();
            
            // Debug - jangan log password lengkap
            Log::info('Mail config prepared', [
                'host' => $mailConfig['host'],
                'port' => $mailConfig['port'],
                'username' => $mailConfig['username'],
                'password_length' => strlen($mailConfig['password'] ?? '')
            ]);
            
            // Setup konfigurasi mailer
            Config::set('mail.mailers.' . $mailerName, $mailConfig);
            
            // Setup from address
            $fromConfig = $config->getFromConfig();
            
            Config::set('mail.from', $fromConfig);
            
            return [
                'name' => $mailerName,
                'config' => $fromConfig,
                'config_source' => 'user_custom'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create user mailer: ' . $e->getMessage());
            throw $e; // Biarkan error ditangkap oleh caller
        }
    }
    
    /**
     * Buat default mailer dari email user
     */
    private function createDefaultUserMailer(User $user): array
    {
        $mailerName = 'smtp'; // Gunakan default
        
        Log::info("Using default mailer with user email for user: {$user->id}");
        
        // Gunakan email user sebagai from address
        $fromAddress = $user->email;
        $fromName = $user->full_name ?: $user->name ?: 'User';
        
        // Update from address di config
        Config::set('mail.from', [
            'address' => $fromAddress,
            'name' => $fromName,
        ]);
        
        return [
            'name' => $mailerName,
            'config' => [
                'address' => $fromAddress,
                'name' => $fromName,
            ],
            'config_source' => 'user_default'
        ];
    }
    
    /**
     * Dapatkan mailer default sistem
     */
    private function getSystemDefaultMailer(): array
    {
        Log::info('Using system default mailer from .env');
        
        // Pastikan konfigurasi default ada
        if (empty(env('MAIL_HOST'))) {
            throw new \Exception('System mail configuration not found in .env');
        }
        
        return [
            'name' => 'smtp',
            'config' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
            'config_source' => 'system_default'
        ];
    }
    
    /**
     * Test koneksi SMTP
     */
    public function testConnection($userConfig = null): array
    {
        try {
            if ($userConfig) {
                $mailConfig = $userConfig->toMailConfig();
                
                // Test dengan transport langsung
                $transport = new \Swift_SmtpTransport(
                    $mailConfig['host'],
                    $mailConfig['port'],
                    $mailConfig['encryption']
                );
                
                $transport->setUsername($mailConfig['username']);
                $transport->setPassword($mailConfig['password']);
                $transport->setTimeout(10);
                
                $mailer = new \Swift_Mailer($transport);
                $mailer->getTransport()->start();
                
                return [
                    'success' => true,
                    'message' => 'SMTP connection successful'
                ];
            } else {
                // Test default
                $transport = new \Swift_SmtpTransport(
                    env('MAIL_HOST'),
                    env('MAIL_PORT'),
                    env('MAIL_ENCRYPTION')
                );
                
                $transport->setUsername(env('MAIL_USERNAME'));
                $transport->setPassword(env('MAIL_PASSWORD'));
                $transport->setTimeout(10);
                
                $mailer = new \Swift_Mailer($transport);
                $mailer->getTransport()->start();
                
                return [
                    'success' => true,
                    'message' => 'Default SMTP connection successful'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('SMTP test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage(),
                'error_details' => [
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
}