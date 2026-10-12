<?php

namespace App\Services\WhatsApp;

/**
 * MVP implementation (dev-prd §31): returns a controlled wa.me deep link the
 * admin can open to send the message. No report data in query params —
 * only a signed, expiring report link inside the message body.
 */
class WaMeShareLink implements WhatsAppShareInterface
{
    public function reportShare(string $phone, string $patientName, string $bookingNumber, string $testSummary, string $reportUrl): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // Default to India country code when a 10-digit number is given.
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        $message = "📄 *Report Ready — Book My Diagnose*\n"
            ."\n"
            ."Hello {$patientName},\n"
            ."Your diagnostic report is ready.\n"
            ."\n"
            ."🧬 *Tests:* {$testSummary}\n"
            ."🧾 *Booking:* {$bookingNumber}\n"
            ."\n"
            ."📥 *View / Download (link valid 24 hours):*\n"
            ."{$reportUrl}\n"
            ."\n"
            .'— Book My Diagnose';

        return [
            'phone' => '+'.$digits,
            'message' => $message,
            'url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
        ];
    }

    public function staffAssignment(string $staffPhone, array $job): array
    {
        $digits = preg_replace('/\D+/', '', $staffPhone);
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        $lines = [
            '🙏 Namaste '.($job['staff_name'] ?? 'Collector').', a new pickup has been assigned.',
            '',
            '🧪 *Booking:* '.($job['booking_number'] ?? '—'),
            '',
            '👤 *Patient:* '.($job['patient_label'] ?? '—'),
            '🧬 *Tests:* '.($job['tests'] ?? '—'),
        ];
        if (! empty($job['patient_phone'])) {
            $lines[] = '📞 *Patient Contact:* '.$job['patient_phone'];
        }
        $lines[] = '';
        $lines[] = '📍 *Pickup Address:*';
        $lines[] = ($job['address'] ?? '—');
        $lines[] = '*Landmark:* '.($job['landmark'] ?? '—');
        $lines[] = '';
        $lines[] = '🗺️ *Location:*';
        if (! empty($job['maps_url'])) {
            // Standalone line so WhatsApp unfurls a map preview like a native share.
            $lines[] = $job['maps_url'];
        }
        if (! empty($job['latitude']) && ! empty($job['longitude'])) {
            // Raw coordinates paste straight into any map app.
            $lines[] = '📌 *Pin:* '.$job['latitude'].', '.$job['longitude'];
        } else {
            $lines[] = '⚠️ GPS pin is missing for this booking. Please ask the patient for their live location on WhatsApp.';
        }
        if (! empty($job['scheduled_at'])) {
            $lines[] = '';
            $lines[] = '⏰ *Scheduled:* '.$job['scheduled_at'];
        }
        $lines[] = '';
        $lines[] = '✅ Please confirm once collected.';
        $lines[] = '— Book My Diagnose';

        $message = implode("\n", $lines);

        return [
            'phone' => '+'.$digits,
            'message' => $message,
            'url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
        ];
    }

    public function statusShare(string $phone, string $patientName, string $bookingNumber, string $testSummary, string $statusLabel, string $statusHint, string $trackingUrl, ?string $reportUrl = null): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // Default to India country code when a 10-digit number is given.
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        $lines = [
            '🔔 *Booking Update — Book My Diagnose*',
            '',
            "Hello {$patientName},",
            "Your booking {$bookingNumber} status has changed.",
            '',
            "📌 *Status:* {$statusLabel}",
        ];
        if ($statusHint !== '') {
            $lines[] = $statusHint;
        }
        $lines[] = '';
        $lines[] = "🧬 *Tests:* {$testSummary}";
        $lines[] = '';
        $lines[] = '🔍 *Track live status:*';
        // Standalone line so WhatsApp unfurls a link preview.
        $lines[] = $trackingUrl;
        if ($reportUrl !== null && $reportUrl !== '') {
            // Delivered reports only — never for ready-but-unsent ones.
            $lines[] = '';
            $lines[] = '📥 *Download Report:*';
            $lines[] = $reportUrl;
        }
        $lines[] = '';
        $lines[] = '— Book My Diagnose';

        $message = implode("\n", $lines);

        return [
            'phone' => '+'.$digits,
            'message' => $message,
            'url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
        ];
    }
}
