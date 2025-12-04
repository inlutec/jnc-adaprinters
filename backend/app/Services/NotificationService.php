<?php

namespace App\Services;

use App\Models\NotificationConfig;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected ?NotificationConfig $config = null;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Carga la configuración activa de notificaciones
     */
    protected function loadConfig(): void
    {
        $this->config = NotificationConfig::where('type', 'email')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Envía un email usando la configuración personalizada
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        if (! $this->config) {
            Log::warning('No active notification config found, using default Laravel mail config');
            return $this->sendWithDefaultConfig($to, $subject, $body, $htmlBody);
        }

        try {
            // Configurar mailer dinámico
            $this->configureMailer();

            Mail::raw($body, function ($message) use ($to, $subject, $htmlBody) {
                $message->to($to)
                    ->subject($subject);

                if ($htmlBody) {
                    $message->html($htmlBody);
                }
            });

            Log::info("Email sent to {$to} using custom SMTP config");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email to {$to}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Envía un email usando la configuración por defecto de Laravel
     */
    protected function sendWithDefaultConfig(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject, $htmlBody) {
                $message->to($to)
                    ->subject($subject);

                if ($htmlBody) {
                    $message->html($htmlBody);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email with default config to {$to}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Configura el mailer con los valores de la configuración personalizada
     */
    protected function configureMailer(): void
    {
        if (! $this->config) {
            return;
        }

        Config::set('mail.mailers.custom', [
            'transport' => 'smtp',
            'host' => $this->config->smtp_host,
            'port' => $this->config->smtp_port,
            'encryption' => $this->config->smtp_encryption,
            'username' => $this->config->smtp_username,
            'password' => $this->config->smtp_password,
            'timeout' => null,
            'local_domain' => null,
        ]);

        Config::set('mail.from', [
            'address' => $this->config->from_address,
            'name' => $this->config->from_name ?? 'JNC-AdaPrinters',
        ]);

        Config::set('mail.default', 'custom');
    }

    /**
     * Prueba la conexión SMTP
     */
    public function testConnection(?NotificationConfig $config = null): array
    {
        $testConfig = $config ?? $this->config;

        if (! $testConfig) {
            return [
                'success' => false,
                'message' => 'No notification config provided',
            ];
        }

        try {
            // Guardar configuración actual
            $originalConfig = $this->config;
            $this->config = $testConfig;

            // Intentar configurar
            $this->configureMailer();

            // Intentar enviar un email de prueba
            $testEmail = $testConfig->from_address;
            $result = $this->sendEmail(
                $testEmail,
                'Test Connection - JNC-AdaPrinters',
                'This is a test email to verify SMTP configuration.',
                '<p>This is a test email to verify SMTP configuration.</p>'
            );

            // Restaurar configuración
            $this->config = $originalConfig;

            return [
                'success' => $result,
                'message' => $result
                    ? 'SMTP connection successful'
                    : 'SMTP connection failed',
            ];
        } catch (\Exception $e) {
            // Restaurar configuración
            $this->config = $originalConfig ?? null;

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtiene la configuración activa
     */
    public function getConfig(): ?NotificationConfig
    {
        return $this->config;
    }
}

