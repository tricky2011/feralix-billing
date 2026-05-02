<?php

namespace App\Services\Provisioning;

use App\Models\Service;
use App\Models\Area;
use App\Models\Olt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PppoeCredentialService
{
    /**
     * Generate PPPoE username dalam format: NAMA-AREA-OLT
     *
     * Contoh: BUDI-UNGARAN-OLT01
     *
     * Logic:
     * - Ambil first name dari customer full_name
     * - Tambah kode area
     * - Tambah kode OLT
     */
    public function generateUsername(Service $service): string
    {
        $customer = $service->customer;
        $area = $service->area ?? $this->resolveArea($service);
        $olt = $service->olt ?? $this->resolveOlt($service);

        // Get first name dari customer full_name
        $firstName = $this->extractFirstName($customer?->full_name ?? 'UNKNOWN');

        // Get area code
        $areaCode = $area?->code ?? 'UNKNOWN';

        // Get OLT code
        $oltCode = $olt?->code ?? 'OLT01';

        // Format: NAMA-AREA-OLT (uppercase)
        $username = strtoupper("{$firstName}-{$areaCode}-{$oltCode}");

        // Handle duplikat: jika username sudah ada, tambah angka
        return $this->handleDuplicates($username);
    }

    /**
     * Generate PPPoE password dalam format DDMMYYYY
     *
     * Contoh: 29042026
     */
    public function generatePassword(Carbon|string $installDate): string
    {
        if (is_string($installDate)) {
            $installDate = Carbon::parse($installDate);
        }

        return $installDate->format('dmY');
    }

    /**
     * Generate credentials dan set ke service
     *
     * @return array{username: string, password: string, password_plain: string}
     */
    public function generateAndSetCredentials(Service $service, Carbon|string $installDate): array
    {
        $username = $this->generateUsername($service);
        $passwordPlain = $this->generatePassword($installDate);

        $service->update([
            'pppoe_username' => $username,
            'pppoe_password' => $passwordPlain, // Already encrypted via model's cast if needed
        ]);

        return [
            'username' => $username,
            'password' => $passwordPlain,
            'password_plain' => $passwordPlain,
        ];
    }

    /**
     * Generate username tanpa handle duplikat (untuk preview)
     */
    public function generateUsernamePreview(Service $service): string
    {
        $customer = $service->customer;
        $area = $service->area ?? $this->resolveArea($service);
        $olt = $service->olt ?? $this->resolveOlt($service);

        $firstName = $this->extractFirstName($customer?->full_name ?? 'UNKNOWN');
        $areaCode = $area?->code ?? '?';
        $oltCode = $olt?->code ?? '?';

        return strtoupper("{$firstName}-{$areaCode}-{$oltCode}");
    }

    /**
     * Extract first name dari full name
     */
    private function extractFirstName(?string $fullName): string
    {
        if ($fullName === null || $fullName === '') {
            return 'CUST';
        }

        // Remove extra spaces and split
        $parts = preg_split('/\s+/', trim($fullName), 2);

        $firstName = $parts[0];

        // Remove non-alphanumeric characters except hyphen
        $firstName = preg_replace('/[^a-zA-Z0-9\-]/', '', $firstName);

        // Limit length to 20 characters
        return Str::limit($firstName, 20, '');
    }

    /**
     * Handle duplicate username dengan menambah angka
     *
     * Contoh: BUDI-UNGARAN-OLT01 sudah ada → BUDI2-UNGARAN-OLT01
     */
    private function handleDuplicates(string $baseUsername): string
    {
        $counter = 1;
        $username = $baseUsername;

        while (Service::where('pppoe_username', $username)->exists()) {
            $counter++;
            // Replace last number atau tambah angka
            if (preg_match('/-(\d+)$/', $baseUsername, $matches)) {
                // Base sudah punya angka di akhir: BUDI-UNGARAN-OLT01
                $username = preg_replace('/-\d+$/', "-{$counter}", $baseUsername);
            } else {
                // Base tidak punya angka: BUDI-UNGARAN-OLT01
                $username = "{$baseUsername}{$counter}";
            }

            // Safety limit
            if ($counter > 100) {
                throw new \RuntimeException('Unable to generate unique PPPoE username after 100 attempts.');
            }
        }

        return $username;
    }

    /**
     * Resolve area dari service
     */
    private function resolveArea(Service $service): ?Area
    {
        // Try through router
        if ($service->router?->area) {
            return $service->router->area;
        }

        // Try through VID
        if ($service->vid?->router?->area) {
            return $service->vid->router->area;
        }

        return null;
    }

    /**
     * Resolve OLT dari service
     */
    private function resolveOlt(Service $service): ?Olt
    {
        return $service->olt;
    }
}
