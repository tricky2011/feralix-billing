<?php

namespace App\Contracts\Mikrotik;

/**
 * Adapter untuk command API RouterOS.
 * ROSv6 dan ROSv7 memiliki perbedaan command/attribute name yang kecil.
 * Adapter ini menstandardisasi path dan attribute name agar logic bisnis
 * tetap sama untuk kedua versi.
 */
interface RouterOsCommandAdapter
{
    /**
     * Apakah router ini menjalankan ROS versi 7+.
     */
    public function isRos7(): bool;

    /**
     * Path untuk mengambil secret PPPoE aktif.
     * ROSv6 & v7: /ppp/active (sama)
     */
    public function pppoeActivePath(): string;

    /**
     * Properties yang di-fetch dari PPPoE active.
     * ROSv7 mengubah beberapa nama field.
     *
     * @return list<string>
     */
    public function pppoeActiveProperties(): array;

    /**
     * Path untuk mengambil PPPoE secret (user database).
     */
    public function pppoeSecretPath(): string;

    /**
     * Properties yang di-fetch dari PPPoE secret.
     *
     * @return list<string>
     */
    public function pppoeSecretProperties(): array;

    /**
     * Path untuk mengambil DHCP server leases.
     */
    public function dhcpLeasePath(): string;

    /**
     * Properties yang di-fetch dari DHCP leases.
     * ROSv7 menggunakan 'active-server', ROSv6 menggunakan 'server'.
     *
     * @return list<string>
     */
    public function dhcpLeaseProperties(): array;

    /**
     * Nama attribute server pada DHCP lease.
     * ROSv6: 'server' | ROSv7: 'active-server'
     */
    public function dhcpLeaseServerAttr(): string;

    /**
     * Path untuk mengambil resource router (versi OS, uptime, dll).
     */
    public function systemResourcePath(): string;

    /**
     * Properties yang di-fetch dari system resource (untuk deteksi versi).
     *
     * @return list<string>
     */
    public function systemResourceProperties(): array;

    /**
     * Path untuk mengambil IP pool.
     */
    public function ipPoolPath(): string;

    /**
     * Path untuk mengambil interface VLAN.
     */
    public function interfaceVlanPath(): string;

    /**
     * Path untuk mengambil DHCP server.
     */
    public function dhcpServerPath(): string;

    /**
     * Path untuk mengambil IP address.
     */
    public function ipAddressPath(): string;

    /**
     * Path untuk mengambil DHCP server network.
     */
    public function dhcpServerNetworkPath(): string;
}
