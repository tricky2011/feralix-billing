#!/usr/bin/env python3
"""
Fix: VID Pelanggan "undefined (undefined - undefined)"
Root cause: mismatch field antara response API suggest dan JS yang menggunakannya
"""

import re

# ============================================================
# FIX 1: admin.js
# ============================================================
with open('resources/js/admin.js', 'r') as f:
    js = f.read()

# FIX 1A: autoAssignProvisioningVid - gunakan field yang benar
# DARI: this.provisioning.assignedVidData.vid_number
# JADI: this.provisioning.assignedVidData.vlan_id ?? this.provisioning.assignedVidData.internet_vid
old_a = 'this.provisioning.form.internet_vid = this.provisioning.assignedVidData.vid_number;'
new_a = 'this.provisioning.form.internet_vid = this.provisioning.assignedVidData.vlan_id ?? this.provisioning.assignedVidData.internet_vid ?? null;'
if old_a in js:
    js = js.replace(old_a, new_a)
    print('FIX 1A OK: autoAssignProvisioningVid vid_number -> vlan_id')
else:
    print('FIX 1A SKIP: string not found')

# FIX 1B: provisioningAssignedVid - gunakan field yang ada di response
# DARI: `${assignedVidData.vid} (${assignedVidData.ip_start} - ${assignedVidData.ip_end})`
# JADI: `V-${assignedVidData.vlan_id} (${assignedVidData.primary_range ?? assignedVidData.pool_name ?? '-'})`
old_b = "return `${this.provisioning.assignedVidData.vid} (${this.provisioning.assignedVidData.ip_start} - ${this.provisioning.assignedVidData.ip_end})`;"
new_b = """const d = this.provisioning.assignedVidData;
            const range = d.primary_range ?? d.pool_name ?? '-';
            return `V-${d.vlan_id ?? d.internet_vid ?? '?'} (${range})`;"""
if old_b in js:
    js = js.replace(old_b, new_b)
    print('FIX 1B OK: provisioningAssignedVid field names fixed')
else:
    print('FIX 1B SKIP: string not found')

# FIX 1C: provisioningAssignedIpAddress - gunakan field yang ada
# DARI: return this.provisioning.assignedVidData.ip_start ?? '-';
# JADI: return this.provisioning.assignedVidData.ip_start ?? this.provisioning.assignedVidData.primary_range?.split('-')[0]?.trim() ?? '-';
old_c = "return this.provisioning.assignedVidData.ip_start ?? '-';"
new_c = """const d = this.provisioning.assignedVidData;
            return d.ip_start ?? d.primary_range?.split('-')[0]?.trim() ?? '-';"""
if old_c in js:
    js = js.replace(old_c, new_c)
    print('FIX 1C OK: provisioningAssignedIpAddress fixed')
else:
    print('FIX 1C SKIP: string not found')

with open('resources/js/admin.js', 'w') as f:
    f.write(js)

print('\nresources/js/admin.js saved')

# ============================================================
# FIX 2: IpPoolController.php - tambah ip_start, ip_end, primary_range ke response suggest
# ============================================================
controller_path = 'app/Http/Controllers/Api/V1/Admin/IpPoolController.php'
with open(controller_path, 'r') as f:
    php = f.read()

# Cari method suggest() dan update response-nya
old_suggest = """        return $this->successResponse('VID suggestion retrieved successfully.', [
            'vlan_id'      => $first['vlan_id'],
            'internet_vid' => $first['vlan_id'],
            'monitor_vid'  => null,
            'pool_name'    => $first['pool_name'],
            'free_ips'     => $first['free_ips'],
            'total_ips'    => $first['total_ips'],
            'used_ips'     => $first['used_ips'],
        ]);"""

new_suggest = """        // Parse ip_start and ip_end from primary_range (format: "x.x.x.x-x.x.x.x")
        $primaryRange = $first['pool_name'] ?? null;
        $ipStart = null;
        $ipEnd   = null;
        if (!empty($primaryRange) && str_contains($primaryRange, '-')) {
            $parts   = explode('-', $primaryRange, 2);
            $ipStart = trim($parts[0]);
            $ipEnd   = trim($parts[1]);
        }

        return $this->successResponse('VID suggestion retrieved successfully.', [
            'vlan_id'       => $first['vlan_id'],
            'vid'           => $first['vlan_id'],
            'vid_number'    => $first['vlan_id'],
            'internet_vid'  => $first['vlan_id'],
            'monitor_vid'   => null,
            'pool_name'     => $first['pool_name'],
            'primary_range' => $first['primary_range'] ?? $first['pool_name'] ?? null,
            'ip_start'      => $ipStart,
            'ip_end'        => $ipEnd,
            'free_ips'      => $first['free_ips'],
            'total_ips'     => $first['total_ips'],
            'used_ips'      => $first['used_ips'],
        ]);"""

if old_suggest in php:
    php = php.replace(old_suggest, new_suggest)
    print('\nFIX 2 OK: suggest() response updated with ip_start, ip_end, primary_range')
else:
    print('\nFIX 2 SKIP: suggest response string not found - may already be updated')
    # Fallback: add vid, vid_number, primary_range if vlan_id exists
    if "'vlan_id'      => $first['vlan_id']," in php and "'vid'" not in php:
        php = php.replace(
            "'vlan_id'      => $first['vlan_id'],",
            "'vlan_id'      => $first['vlan_id'],\n            'vid'           => $first['vlan_id'],\n            'vid_number'    => $first['vlan_id'],\n            'primary_range' => $first['primary_range'] ?? null,"
        )
        print('FIX 2 FALLBACK OK: added vid, vid_number, primary_range')

with open(controller_path, 'w') as f:
    f.write(php)

print(f'{controller_path} saved')
print('\nDone. Now run: npm run build && php artisan config:clear')