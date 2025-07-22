<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class CommunicationService
{
    private string $twilioSid;
    private string $twilioToken;
    private string $twilioFromNumber;
    private string $smtpFromEmail;
    private string $smtpFromName;

    public function __construct()
    {
        $this->twilioSid = config('services.twilio.sid');
        $this->twilioToken = config('services.twilio.token');
        $this->twilioFromNumber = config('services.twilio.from_number');
        $this->smtpFromEmail = config('mail.from.address');
        $this->smtpFromName = config('mail.from.name');
    }

    /**
     * Send SMS via Twilio
     */
    public function sendSMS(string $to, string $message, array $options = []): bool
    {
        try {
            if (!$this->twilioSid || !$this->twilioToken) {
                Log::warning('Twilio credentials not configured, SMS not sent', [
                    'to' => $to,
                    'message_preview' => substr($message, 0, 50)
                ]);
                return false;
            }

            $response = Http::withBasicAuth($this->twilioSid, $this->twilioToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json", [
                    'From' => $this->twilioFromNumber,
                    'To' => $this->formatPhoneNumber($to),
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('SMS sent successfully', [
                    'to' => $to,
                    'sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null
                ]);
                return true;
            } else {
                Log::error('Failed to send SMS', [
                    'to' => $to,
                    'status' => $response->status(),
                    'error' => $response->json()
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error('SMS sending exception', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send email
     */
    public function sendEmail(string $to, string $subject, string $content, array $options = []): bool
    {
        try {
            $attachments = $options['attachments'] ?? [];
            $isHtml = $options['html'] ?? false;
            
            // Send plain text email without template dependencies
            Mail::raw($content, function ($mail) use ($to, $subject, $attachments, $isHtml, $content) {
                $mail->from($this->smtpFromEmail, $this->smtpFromName)
                     ->to($to)
                     ->subject($subject);

                // If HTML content is provided, set it as HTML
                if ($isHtml) {
                    $mail->html($content);
                }

                foreach ($attachments as $attachment) {
                    $mail->attach($attachment['path'], [
                        'as' => $attachment['name'] ?? null,
                        'mime' => $attachment['mime'] ?? null
                    ]);
                }
            });

            Log::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'html' => $isHtml
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Email sending exception', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send verification code via SMS
     */
    public function sendVerificationSMS(string $phoneNumber, string $code, string $type = 'verification'): bool
    {
        $message = $this->getSMSTemplate($type, ['code' => $code]);
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send verification code via email
     */
    public function sendVerificationEmail(string $email, string $code, string $type = 'verification'): bool
    {
        $subject = $this->getEmailSubject($type);
        $content = $this->getEmailTemplate($type, ['code' => $code]);
        
        return $this->sendEmail($email, $subject, $content, [
            'template' => 'verification'
        ]);
    }

    /**
     * Send 2FA code via SMS
     */
    public function send2FASMS(string $phoneNumber, string $code): bool
    {
        $message = "Your SoulSync security code is: {$code}. This code will expire in 10 minutes. Don't share this code with anyone.";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send 2FA code via email
     */
    public function send2FAEmail(string $email, string $code): bool
    {
        $subject = 'SoulSync Security Code';
        $content = "Your security code is: {$code}. This code will expire in 10 minutes.";
        
        return $this->sendEmail($email, $subject, $content, [
            'template' => '2fa'
        ]);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $email, string $resetUrl): bool
    {
        $subject = 'Reset Your SoulSync Password';
        $content = "Click the following link to reset your password: {$resetUrl}";
        
        return $this->sendEmail($email, $subject, $content, [
            'template' => 'password_reset'
        ]);
    }

    /**
     * Send welcome email
     */
    public function sendWelcomeEmail(string $email, string $firstName): bool
    {
        $subject = 'Welcome to SoulSync!';
        $content = "Welcome to SoulSync, {$firstName}! We're excited to help you find your perfect match.";
        
        return $this->sendEmail($email, $subject, $content, [
            'template' => 'welcome'
        ]);
    }

    /**
     * Send match notification
     */
    public function sendMatchNotificationSMS(string $phoneNumber, string $matchName): bool
    {
        $message = "Great news! You have a new match on SoulSync with {$matchName}. Check your app to start chatting!";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send subscription confirmation
     */
    public function sendSubscriptionConfirmationEmail(string $email, array $subscriptionData): bool
    {
        $subject = 'SoulSync Subscription Confirmation';
        $content = "Your {$subscriptionData['plan']} subscription has been activated. Thank you for choosing SoulSync!";
        
        return $this->sendEmail($email, $subject, $content, [
            'template' => 'subscription_confirmation'
        ]);
    }

    /**
     * Format phone number for international use
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add country code if not present
        if (!str_starts_with($phoneNumber, '+')) {
            // Default to US country code if no country code present
            if (strlen($phoneNumber) === 10) {
                $phoneNumber = '+1' . $phoneNumber;
            } elseif (!str_starts_with($phoneNumber, '1') && strlen($phoneNumber) === 11) {
                $phoneNumber = '+' . $phoneNumber;
            } else {
                $phoneNumber = '+' . $phoneNumber;
            }
        }

        return $phoneNumber;
    }

    /**
     * Get SMS template for different types
     */
    private function getSMSTemplate(string $type, array $variables = []): string
    {
        $templates = [
            'verification' => 'Your SoulSync verification code is: {code}. This code will expire in 10 minutes.',
            '2fa' => 'Your SoulSync security code is: {code}. Don\'t share this code with anyone.',
            'password_reset' => 'Your SoulSync password reset code is: {code}. Use this code to reset your password.',
            'welcome' => 'Welcome to SoulSync! Your account has been created successfully. Start building your profile now.',
            'match' => 'You have a new match on SoulSync! Check your app to see who liked you back.',
        ];

        $template = $templates[$type] ?? $templates['verification'];
        
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * Get email subject for different types
     */
    private function getEmailSubject(string $type): string
    {
        $subjects = [
            'verification' => 'Verify Your SoulSync Account',
            '2fa' => 'SoulSync Security Code',
            'password_reset' => 'Reset Your SoulSync Password',
            'welcome' => 'Welcome to SoulSync!',
            'match' => 'You Have a New Match!',
            'subscription' => 'SoulSync Subscription Update',
        ];

        return $subjects[$type] ?? 'SoulSync Notification';
    }

    /**
     * Get email template content
     */
    private function getEmailTemplate(string $type, array $variables = []): string
    {
        $templates = [
            'verification' => 'Please use the following code to verify your account: {code}',
            '2fa' => 'Your two-factor authentication code is: {code}',
            'password_reset' => 'Use this code to reset your password: {code}',
        ];

        $template = $templates[$type] ?? $templates['verification'];
        
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * Check service configuration
     */
    public function isConfigured(): array
    {
        return [
            'sms_enabled' => !empty($this->twilioSid) && !empty($this->twilioToken),
            'email_enabled' => !empty($this->smtpFromEmail),
            'twilio_configured' => !empty($this->twilioSid),
            'smtp_configured' => config('mail.mailers.smtp.host') !== null,
        ];
    }

    /**
     * Send bulk SMS (for marketing campaigns)
     */
    public function sendBulkSMS(array $phoneNumbers, string $message): array
    {
        $results = [];
        
        foreach ($phoneNumbers as $phoneNumber) {
            $results[$phoneNumber] = $this->sendSMS($phoneNumber, $message);
            
            // Add delay to avoid rate limiting
            usleep(100000); // 0.1 second delay
        }

        return $results;
    }

    /**
     * Send bulk email (for newsletters)
     */
    public function sendBulkEmail(array $emails, string $subject, string $content, array $options = []): array
    {
        $results = [];
        
        foreach ($emails as $email) {
            $results[$email] = $this->sendEmail($email, $subject, $content, $options);
            
            // Add delay to avoid rate limiting
            usleep(50000); // 0.05 second delay
        }

        return $results;
    }
} 