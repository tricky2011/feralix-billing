<?php

namespace App\Services\Mikrotik\Adapters;

/**
 * RouterOS v6 command adapter.
 *
 * Perbedaan utama dengan v7:
 * - DHCP lease menggunakan field 'server' (bukan 'active-server')
 * - Field name attribute umum lainnya sama
 */
final class RouterOsV6CommandAdapter implements \App\Contracts\Mikrotik\RouterOsCommandAdapter
{
    public function isRos7(): bool
    {
        return false;
    }

    public function pppoeActivePath(): string
    {
        return '/ppp/active';
    }

    public function pppoeActiveProperties(): array
    {
        return ['.id', 'name', 'service', 'caller-id', 'address', 'uptime', 'session-id'];
    }

    public function pppoeSecretPath(): string
    {
        return '/ppp/secret';
    }

    public function pppoeSecretProperties(): array
    {
        return ['.id', 'name', 'service', 'profile', 'local-address', 'remote-address', 'caller-id', 'disabled'];
    }

    public function dhcpLeasePath(): string
    {
        return '/ip/dhcp-server/lease';
    }

    public function dhcpLeaseProperties(): array
    {
        return ['.id', 'address', 'status', 'server', 'active-server', 'disabled'];
    }

    public function dhcpLeaseServerAttr(): string
    {
        return 'server';
    }

    public function systemResourcePath(): string
    {
        return '/system/resource';
    }

    public function systemResourceProperties(): array
    {
        return ['version', 'board-name', 'architecture-name', 'uptime'];
    }

    public function ipPoolPath(): string
    {
        return '/ip/pool';
    }

    public function interfaceVlanPath(): string
    {
        return '/interface/vlan';
    }

    public function dhcpServerPath(): string
    {
        return '/ip/dhcp-server';
    }

    public function ipAddressPath(): string
    {
        return '/ip/address';
    }

    public function dhcpServerNetworkPath(): string
    {
        return '/ip/dhcp-server/network';
    }
}
