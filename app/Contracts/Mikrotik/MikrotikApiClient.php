<?php

namespace App\Contracts\Mikrotik;

interface MikrotikApiClient
{
    /**
     * Fetch list of items dari Mikrotik RouterOS.
     * Setara: /path/print di RouterOS CLI.
     * Semua value di return array adalah STRING — caller wajib casting sendiri.
     *
     * @param  array<string>              $properties  Field yang ingin diambil (projection)
     * @param  array<string, string>      $where       Filter kondisi (key => value)
     * @return list<array<string, string>>
     */
    public function print(string $menuPath, array $properties = [], array $where = []): array;

    /**
     * Buat item baru di Mikrotik.
     * Setara: /path/add key=value di RouterOS CLI.
     * Gunakan ->equal() di Query layer.
     *
     * @param  array<string, string|int>  $attributes
     */
    public function add(string $menuPath, array $attributes = []): void;

    /**
     * Update item yang sudah ada by .id Mikrotik.
     * Setara: /path/set .id=X key=value di RouterOS CLI.
     *
     * @param  string                     $id         Nilai .id dari Mikrotik (misal: '*1')
     * @param  array<string, string|int>  $attributes Field yang diupdate
     */
    public function set(string $menuPath, string $id, array $attributes): void;

    /**
     * Update item by kondisi (tanpa perlu .id).
     * Setara: /path/set [find key=value] newkey=newvalue di RouterOS CLI.
     * Contoh: setWhere('/ip/pool', ['name' => 'V-1047'], ['ranges' => '10.101.47.1-10.101.47.3'])
     *
     * @param  array<string, string>      $where       Kondisi filter
     * @param  array<string, string|int>  $attributes  Field yang diupdate
     */
    public function setWhere(string $menuPath, array $where, array $attributes): void;

    /**
     * Hapus item by .id Mikrotik.
     * Gunakan jika .id sudah diketahui dari hasil print().
     */
    public function remove(string $menuPath, string $id): void;

    /**
     * Hapus item by kondisi — tanpa perlu tahu .id.
     * Setara: /path/remove [find key=value] di RouterOS CLI.
     *
     * Contoh:
     *   removeWhere('/ppp/secret', ['name' => 'BUDI-UNGARAN-OLT01'])
     *   removeWhere('/ip/firewall/address-list', ['list' => 'ISOLIR', 'address' => '10.101.47.0/24'])
     *   removeWhere('/interface/vlan', ['name' => 'V-1047'])
     *
     * @param  array<string, string>  $where
     */
    public function removeWhere(string $menuPath, array $where): void;

    /**
     * Execute command tanpa return value — untuk RPC dan aksi satu arah.
     * Contoh: reboot, hotspot active add/remove, factory reset via trigger
     *
     * @param  array<string, string|int>  $attributes
     */
    public function command(string $menuPath, array $attributes = []): void;

    public function disconnect(): void;
}
