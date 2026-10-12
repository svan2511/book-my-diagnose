<?php

namespace App\Services\WhatsApp;

/**
 * Provider abstraction (dev-prd §31). The business flow depends on this
 * interface — swap the wa.me implementation for a real WhatsApp Business
 * API provider later without touching controllers or services.
 */
interface WhatsAppShareInterface
{
    /**
     * Build everything the admin needs to share a report on WhatsApp.
     *
     * @return array{phone: string, message: string, url: string}
     */
    public function reportShare(string $phone, string $patientName, string $bookingNumber, string $testSummary, string $reportUrl): array;

    /**
     * Build the pickup-job message for a collection staff member.
     *
     * $job keys: staff_name, booking_number, patient_label, tests,
     * address, landmark, maps_url (nullable), scheduled_at (nullable),
     * patient_phone (nullable).
     *
     * @return array{phone: string, message: string, url: string}
     */
    public function staffAssignment(string $staffPhone, array $job): array;

    /**
     * Build the live-status message for the patient: current status plus
     * the tracking link they can reopen after every status change.
     * $reportUrl is included ONLY after the report is delivered —
     * a ready-but-unsent report must never leak a download link.
     *
     * @return array{phone: string, message: string, url: string}
     */
    public function statusShare(string $phone, string $patientName, string $bookingNumber, string $testSummary, string $statusLabel, string $statusHint, string $trackingUrl, ?string $reportUrl = null): array;
}
