<?php

namespace App\Services\CustomerActivity;

use App\Mail\CustomerActivityEmail;
use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Services\DynamicMailerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ActivityEmailService
{
    use ActivityHelperTrait;

    /**
     * Process sending email with attachments, creating activity records.
     *
     * @return array
     */
    public function processSendEmail(Request $request, DynamicMailerService $dynamicMailerService): array
    {
        $data = $request->all();

        foreach (['recipients', 'cc', 'bcc'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_filter($data[$field], function ($value) {
                    return !is_null($value) && trim($value) !== '';
                });

                if (empty($data[$field])) {
                    $data[$field] = null;
                }
            }
        }

        $validator = Validator::make($data, [
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'leads_id' => 'required|exists:sl_leads,id',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ], [
            'recipients.required' => 'Minimal harus ada 1 penerima email.',
            'recipients.*.email' => 'Format email penerima tidak valid.',
            'cc.*.email' => 'Format email CC tidak valid.',
            'bcc.*.email' => 'Format email BCC tidak valid.',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'validation_failed',
                'validation_errors' => $validator->errors(),
            ];
        }

        $user = Auth::user();

        $mailerSetup = $dynamicMailerService->setupMailer($user);
        $mailerName = $mailerSetup['name'];
        $fromConfig = $mailerSetup['config'];
        $configSource = $mailerSetup['config_source'];

        $leads = Leads::find($request->leads_id);
        $nomor = $this->generateNomor($request->leads_id);
        $current_date_time = Carbon::now();

        $recipientsList = implode(', ', $data['recipients'] ?? []);
        $notes = "Email dikirim ke: {$recipientsList}\nSubject: {$request->subject}\n\n{$request->body}";

        if (!empty($data['cc'])) {
            $notes .= "\nCC: " . implode(', ', $data['cc']);
        }
        if (!empty($data['bcc'])) {
            $notes .= "\nBCC: " . implode(', ', $data['bcc']);
        }

        if ($request->hasFile('attachments')) {
            $attachmentCount = count($request->file('attachments'));
            $notes .= "\nAttachments: {$attachmentCount} file(s)";
        }

        $activityData = [
            'nomor' => $nomor,
            'leads_id' => $request->leads_id,
            'tgl_activity' => $current_date_time->toDateString(),
            'tipe' => 'Email',
            'notes' => $notes,
            'branch_id' => $leads->branch_id,
            'user_id' => $user->id,
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
            'created_at' => $current_date_time
        ];

        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $activity = $this->createSalesActivity($request->leads_id, $notes);
        } else {
            $activity = CustomerActivity::create($activityData);
        }

        $attachmentFiles = [];
        $attachmentNames = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                try {
                    $fileName = $this->storeActivityFile($activity->id, $file);
                    $attachmentFiles[] = $file;
                    $attachmentNames[] = $file->getClientOriginalName();

                    Log::info('File attached for email:', [
                        'activity_id' => $activity->id,
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize()
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to process attachment:', [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $fullBody = $request->body;

        $sentCount = 0;
        $failedRecipients = [];
        $successRecipients = [];
        $totalRecipients = count($data['recipients'] ?? []);

        foreach (($data['recipients'] ?? []) as $index => $recipient) {
            $attempt = $index + 1;

            try {
                $email = new CustomerActivityEmail(
                    $request->subject,
                    $fullBody,
                    $fromConfig['address'],
                    $fromConfig['name'],
                    $attachmentFiles
                );

                if (!empty($data['cc'])) {
                    $email->cc($data['cc']);
                }

                if (!empty($data['bcc'])) {
                    $email->bcc($data['bcc']);
                }

                Mail::mailer($mailerName)->to($recipient)->send($email);

                $sentCount++;
                $successRecipients[] = $recipient;

                Log::info('Email sent successfully:', [
                    'recipient' => $recipient,
                    'attempt' => $attempt,
                    'attachments_count' => count($attachmentFiles)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to send email:', [
                    'recipient' => $recipient,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                $failedRecipients[] = [
                    'email' => $recipient,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ];
            }

            if ($attempt < $totalRecipients) {
                usleep(50000);
            }
        }

        return [
            'status' => $sentCount === $totalRecipients ? 'all_success' : ($sentCount > 0 ? 'partial_success' : 'all_failed'),
            'mailer_name' => $mailerName,
            'from_config' => $fromConfig,
            'config_source' => $configSource,
            'sent_count' => $sentCount,
            'total_recipients' => $totalRecipients,
            'success_recipients' => $successRecipients,
            'failed_recipients' => $failedRecipients,
            'attachment_files' => $attachmentFiles,
            'attachment_names' => $attachmentNames,
            'activity' => $activity,
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
        ];
    }

    /**
     * Test mailer connection.
     */
    public function testMailerConnection($mailerName, $fromConfig): array
    {
        try {
            Mail::mailer($mailerName)->raw('Test connection', function ($message) use ($fromConfig) {
                $message->to($fromConfig['address'])
                    ->subject('Test Connection - ' . date('Y-m-d H:i:s'))
                    ->from($fromConfig['address'], $fromConfig['name']);
            });

            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
