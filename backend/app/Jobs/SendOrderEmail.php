<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public Order $order)
    {
        $this->onQueue('emails');
    }

    public function handle(NotificationService $notificationService): void
    {
        if (! $this->order->email_to) {
            Log::warning("Order {$this->order->id} has no email_to, skipping email send");
            return;
        }

        $printer = $this->order->printer;
        $consumable = $this->order->consumable;

        $subject = "Pedido de Consumible - {$printer->name}";
        $body = $this->buildEmailBody($printer, $consumable);
        $htmlBody = $this->buildHtmlEmailBody($printer, $consumable);

        $sent = $notificationService->sendEmail(
            $this->order->email_to,
            $subject,
            $body,
            $htmlBody
        );

        if ($sent) {
            $this->order->update([
                'email_sent_at' => now(),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            Log::info("Order email sent for order {$this->order->id} to {$this->order->email_to}");
        } else {
            Log::error("Failed to send order email for order {$this->order->id}");
            throw new \Exception('Failed to send order email');
        }
    }

    protected function buildEmailBody($printer, $consumable): string
    {
        $lines = [
            "PEDIDO DE CONSUMIBLE",
            "",
            "Impresora: {$printer->name}",
            "Ubicación: {$printer->site?->name} - {$printer->department?->name}",
            "IP: {$printer->ip_address}",
            "",
        ];

        if ($consumable) {
            $lines[] = "Consumible: {$consumable->name}";
            $lines[] = "SKU: {$consumable->sku}";
        }

        $lines[] = "";
        $lines[] = "Por favor, enviar el consumible solicitado a la dirección indicada.";
        $lines[] = "";
        $lines[] = "Fecha: " . now()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }

    protected function buildHtmlEmailBody($printer, $consumable): string
    {
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #00A859; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>PEDIDO DE CONSUMIBLE</h1>
        </div>
        <div class='content'>
            <div class='info-row'><span class='label'>Impresora:</span> {$printer->name}</div>
            <div class='info-row'><span class='label'>Ubicación:</span> {$printer->site?->name} - {$printer->department?->name}</div>
            <div class='info-row'><span class='label'>IP:</span> {$printer->ip_address}</div>";

        if ($consumable) {
            $html .= "
            <div class='info-row'><span class='label'>Consumible:</span> {$consumable->name}</div>
            <div class='info-row'><span class='label'>SKU:</span> {$consumable->sku}</div>";
        }

        $html .= "
            <hr style='margin: 20px 0;'>
            <p>Por favor, enviar el consumible solicitado a la dirección indicada.</p>
            <p><small>Fecha: " . now()->format('d/m/Y H:i') . "</small></p>
        </div>
    </div>
</body>
</html>";

        return $html;
    }
}
