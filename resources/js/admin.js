import { api } from './services/api';
import { tokenStore } from './services/token';

const statusGroups = {
    green: ['active', 'paid', 'completed', 'resolved', 'closed', 'applied', 'online', 'assigned'],
    red: ['suspended', 'isolated', 'overdue', 'failed', 'canceled', 'terminated', 'expired', 'down', 'inactive'],
    yellow: ['pending', 'issued', 'unpaid', 'partially_paid', 'provisioning', 'open', 'in_progress', 'reserved', 'unknown'],
};

const select = (values) => values.map((value) => ({ value, label: human(value) }));
const human = (value) => String(value ?? '-').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const baseSelects = {
    customer_type: select(['residential', 'business', 'internal']),
    customer_status: select(['active', 'inactive']),
    service_billing: select(['pending', 'paid', 'overdue', 'suspended', 'closed']),
    service_network: select(['provisioning', 'active', 'isolated', 'down', 'inactive']),
    service_overall: select(['provisioning', 'active', 'down', 'suspended', 'isolated', 'inactive', 'terminated']),
    service_access: select(['vlan', 'pppoe', 'static']),
    isolation_method: select(['address_list', 'firewall_filter', 'ppp_profile', 'queue']),
    isolation_target: select(['subnet', 'pppoe', 'static']),
    invoice_status: select(['draft', 'issued', 'unpaid', 'overdue', 'partially_paid', 'paid', 'canceled']),
    vid_status: select(['available', 'assigned', 'reserved', 'unknown', 'conflict', 'incomplete', 'disabled']),
    vid_type: select(['monitoring', 'customer_internet']),
    ticket_priority: select(['low', 'medium', 'high', 'urgent']),
    work_order_type: select(['new_install', 'relocation', 'termination', 'ont_replacement', 'other']),
    work_order_status: select(['open', 'assigned', 'in_progress', 'completed', 'canceled']),
    hotspot_validity: select(['days_after_first_login', 'unlimited']),
    hotspot_lock: select(['mac', 'none']),
    hotspot_expired: select(['time_or_data', 'time', 'data', 'none']),
    reseller_status: select(['active', 'inactive', 'suspended']),
    password_mode: select(['random_secure', 'same_as_username']),
    username_mode: select(['voucher_code', 'prefix_random']),
    bool: [
        { value: true, label: 'Ya' },
        { value: false, label: 'Tidak' },
    ],
};

const modules = {
    customers: {
        title: 'Customers',
        section: 'CRM',
        description: 'Kelola data pelanggan, status, lokasi, dan relasi teknisi.',
        endpoint: '/api/v1/admin/customers',
        columns: [
            { key: 'customer_code', label: 'Kode' },
            { key: 'full_name', label: 'Nama' },
            { key: 'phone', label: 'Telepon' },
            { key: 'latest_active_service.service_code', label: 'Service' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'customer_code', label: 'Kode customer', placeholder: 'CUST-001' },
            { name: 'full_name', label: 'Nama lengkap' },
            { name: 'phone', label: 'Telepon' },
            { name: 'customer_type', label: 'Tipe', type: 'select', options: baseSelects.customer_type },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.customer_status },
            { name: 'location_id', label: 'Lokasi', type: 'select', optionsRef: 'locations' },
            { name: 'preferred_olt_id', label: 'Preferred OLT', type: 'select', optionsRef: 'olts' },
            { name: 'assigned_technician_id', label: 'Teknisi', type: 'select', optionsRef: 'technicians' },
            { name: 'address', label: 'Alamat', type: 'textarea' },
        ],
        deletable: true,
    },
    services: {
        title: 'Services + VID',
        section: 'Provisioning',
        description: 'Service pelanggan dengan dedicated VID, IP, ONT, PPPoE monitor, dan aksi isolir.',
        endpoint: '/api/v1/admin/services',
        columns: [
            { key: 'service_code', label: 'Service' },
            { key: 'customer.full_name', label: 'Customer' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'vid.vid', label: 'VID' },
            { key: 'subnet_cidr', label: 'Subnet' },
            { key: 'overall_status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'customer_id', label: 'Customer', type: 'select', optionsRef: 'customers' },
            { name: 'package_id', label: 'Package', type: 'select', optionsRef: 'packages' },
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'olt_id', label: 'OLT', type: 'select', optionsRef: 'olts' },
            { name: 'ont_id', label: 'ONT', type: 'select', optionsRef: 'onts' },
            { name: 'vid_id', label: 'Dedicated internet VID', type: 'select', optionsRef: 'available_vids' },
            { name: 'service_code', label: 'Kode service' },
            { name: 'monitor_vid', label: 'Monitor VID', type: 'number' },
            { name: 'monitor_pppoe_username', label: 'PPPoE monitor username' },
            { name: 'monitor_pppoe_password', label: 'PPPoE monitor password', type: 'password' },
            { name: 'internet_vid', label: 'Internet VID', type: 'number' },
            { name: 'access_mode', label: 'Access mode', type: 'select', options: baseSelects.service_access },
            { name: 'subnet_cidr', label: 'Subnet CIDR', placeholder: '10.10.10.0/29' },
            { name: 'gateway_ip', label: 'Gateway IP' },
            { name: 'dhcp_pool_start', label: 'DHCP pool start' },
            { name: 'dhcp_pool_end', label: 'DHCP pool end' },
            { name: 'isolation_method', label: 'Metode isolir', type: 'select', options: baseSelects.isolation_method },
            { name: 'address_list_name', label: 'Address-list name', placeholder: 'isolir' },
            { name: 'billing_status', label: 'Billing status', type: 'select', options: baseSelects.service_billing },
            { name: 'network_status', label: 'Network status', type: 'select', options: baseSelects.service_network },
            { name: 'overall_status', label: 'Overall status', type: 'select', options: baseSelects.service_overall },
            { name: 'activation_date', label: 'Tanggal aktif', type: 'date' },
            { name: 'notes', label: 'Catatan', type: 'textarea' },
        ],
        deletable: true,
    },
    billing: {
        title: 'Billing',
        section: 'Finance ops',
        description: 'Invoice, status pembayaran, dan pencatatan payment.',
        endpoint: '/api/v1/admin/invoices',
        columns: [
            { key: 'invoice_number', label: 'Invoice' },
            { key: 'customer.full_name', label: 'Customer' },
            { key: 'billing_period', label: 'Periode' },
            { key: 'total_amount', label: 'Total', type: 'money' },
            { key: 'remaining_amount', label: 'Sisa', type: 'money' },
            { key: 'payment_status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'customer_id', label: 'Customer', type: 'select', optionsRef: 'customers' },
            { name: 'service_id', label: 'Service', type: 'select', optionsRef: 'services' },
            { name: 'invoice_number', label: 'Nomor invoice', placeholder: 'Kosongkan untuk auto' },
            { name: 'billing_period', label: 'Periode', placeholder: '2026-04' },
            { name: 'invoice_date', label: 'Tanggal invoice', type: 'date' },
            { name: 'due_date', label: 'Jatuh tempo', type: 'date' },
            { name: 'subtotal', label: 'Subtotal', type: 'number' },
            { name: 'penalty_amount', label: 'Denda', type: 'number' },
            { name: 'issue_now', label: 'Terbitkan sekarang', type: 'select', options: baseSelects.bool },
        ],
        deletable: true,
    },
    isolations: {
        title: 'Isolations',
        section: 'Mikrotik guardrail',
        description: 'History isolir service, release aktif, dan status target router.',
        endpoint: '/api/v1/admin/service-isolations',
        columns: [
            { key: 'service.service_code', label: 'Service' },
            { key: 'service.customer.full_name', label: 'Customer' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'target_type', label: 'Target' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'router_operation_status.isolation_detected_via', label: 'Detected via' },
        ],
        fields: [
            { name: 'service_id', label: 'Service', type: 'select', optionsRef: 'services' },
            { name: 'isolation_type', label: 'Tipe', type: 'select', options: select(['manual']) },
            { name: 'target_type', label: 'Target type', type: 'select', options: baseSelects.isolation_target },
            { name: 'address_list_name', label: 'Address-list name', placeholder: 'isolir' },
            { name: 'target_subnet', label: 'Target subnet' },
            { name: 'target_identifier', label: 'Target identifier' },
            { name: 'redirect_url', label: 'Redirect URL' },
            { name: 'notes', label: 'Catatan', type: 'textarea' },
        ],
        createEndpoint: '/api/v1/admin/service-isolations',
        noEdit: true,
    },
    tickets: {
        title: 'Tickets',
        section: 'Helpdesk',
        description: 'Tiket gangguan, auto-assign teknisi, dan detail notifikasi.',
        endpoint: '/api/v1/admin/tickets',
        columns: [
            { key: 'ticket_number', label: 'Ticket' },
            { key: 'customer.full_name', label: 'Customer' },
            { key: 'category', label: 'Kategori' },
            { key: 'priority', label: 'Prioritas', type: 'status' },
            { key: 'assigned_technician.full_name', label: 'Teknisi' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'customer_id', label: 'Customer', type: 'select', optionsRef: 'customers' },
            { name: 'service_id', label: 'Service', type: 'select', optionsRef: 'services' },
            { name: 'category', label: 'Kategori', placeholder: 'LOS / Lambat / Putus-putus' },
            { name: 'priority', label: 'Prioritas', type: 'select', options: baseSelects.ticket_priority },
            { name: 'description', label: 'Deskripsi', type: 'textarea' },
        ],
        noEdit: true,
    },
    'work-orders': {
        title: 'Work Orders',
        section: 'Field ops',
        description: 'WO instalasi, relokasi, terminasi, assignment, dan completion.',
        endpoint: '/api/v1/admin/work-orders',
        columns: [
            { key: 'wo_number', label: 'WO' },
            { key: 'customer.full_name', label: 'Customer' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'wo_type', label: 'Tipe' },
            { key: 'assigned_technician.full_name', label: 'Teknisi' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'customer_id', label: 'Customer', type: 'select', optionsRef: 'customers' },
            { name: 'service_id', label: 'Service', type: 'select', optionsRef: 'services' },
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'olt_id', label: 'OLT', type: 'select', optionsRef: 'olts' },
            { name: 'ont_id', label: 'ONT', type: 'select', optionsRef: 'onts' },
            { name: 'wo_type', label: 'Tipe WO', type: 'select', options: baseSelects.work_order_type },
            { name: 'assigned_technician_id', label: 'Teknisi', type: 'select', optionsRef: 'technicians' },
            { name: 'planned_monitor_vid', label: 'Planned monitor VID', type: 'number' },
            { name: 'planned_internet_vid', label: 'Planned internet VID', type: 'number' },
            { name: 'planned_subnet_cidr', label: 'Planned subnet' },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.work_order_status },
            { name: 'scheduled_at', label: 'Jadwal', type: 'datetime-local' },
            { name: 'work_notes', label: 'Catatan pekerjaan', type: 'textarea' },
        ],
        deletable: true,
    },
};

const networkTabs = {
    routers: {
        label: 'Routers',
        endpoint: '/api/v1/admin/routers',
        columns: [
            { key: 'router_code', label: 'Kode' },
            { key: 'router_name', label: 'Nama' },
            { key: 'router_role', label: 'Role' },
            { key: 'mgmt_ip', label: 'IP' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'router_code', label: 'Kode router' },
            { name: 'router_name', label: 'Nama router' },
            { name: 'router_role', label: 'Role' },
            { name: 'mgmt_ip', label: 'Management IP' },
            { name: 'api_port', label: 'API port', type: 'number' },
            { name: 'api_username', label: 'API username' },
            { name: 'api_password', label: 'API password', type: 'password' },
            { name: 'location_name', label: 'Lokasi' },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
        ],
        deletable: true,
    },
    'router-scopes': {
        label: 'Router scopes',
        endpoint: '/api/v1/admin/router-scopes',
        columns: [
            { key: 'scope_name', label: 'Scope' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'monitor_vid', label: 'Monitor VID' },
            { key: 'vid_start', label: 'VID start' },
            { key: 'vid_end', label: 'VID end' },
        ],
        fields: [
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'scope_name', label: 'Nama scope' },
            { name: 'monitor_vid', label: 'Monitor VID', type: 'number' },
            { name: 'vid_start', label: 'VID start', type: 'number' },
            { name: 'vid_end', label: 'VID end', type: 'number' },
            { name: 'is_special', label: 'Special', type: 'select', options: baseSelects.bool },
            { name: 'notes', label: 'Catatan', type: 'textarea' },
        ],
        deletable: true,
    },
    vids: {
        label: 'VIDs',
        endpoint: '/api/v1/admin/vids',
        columns: [
            { key: 'vid', label: 'VID' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'vid_type', label: 'Tipe' },
            { key: 'subnet_cidr', label: 'Subnet' },
            { key: 'service_id', label: 'Service ID' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'scope_id', label: 'Scope ID', type: 'number' },
            { name: 'vid', label: 'VID', type: 'number' },
            { name: 'vid_type', label: 'Tipe', type: 'select', options: baseSelects.vid_type },
            { name: 'subnet_cidr', label: 'Subnet CIDR' },
            { name: 'gateway_ip', label: 'Gateway IP' },
            { name: 'pool_start_ip', label: 'Pool start' },
            { name: 'pool_end_ip', label: 'Pool end' },
            { name: 'pool_ip_count', label: 'Pool count', type: 'number' },
            { name: 'rate_limit_mbps', label: 'Rate limit Mbps', type: 'number' },
            { name: 'sync_source', label: 'Sync source' },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.vid_status },
        ],
        deletable: true,
    },
    olts: {
        label: 'OLTs',
        endpoint: '/api/v1/admin/olts',
        columns: [
            { key: 'olt_code', label: 'Kode' },
            { key: 'olt_name', label: 'Nama' },
            { key: 'mgmt_ip', label: 'IP' },
            { key: 'vendor_name', label: 'Vendor' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'olt_code', label: 'Kode OLT' },
            { name: 'olt_name', label: 'Nama OLT' },
            { name: 'mgmt_ip', label: 'Management IP' },
            { name: 'vendor_name', label: 'Vendor' },
            { name: 'location_id', label: 'Lokasi', type: 'select', optionsRef: 'locations' },
            { name: 'location_name', label: 'Nama lokasi' },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
        ],
        deletable: true,
    },
    onts: {
        label: 'ONTs',
        endpoint: '/api/v1/admin/onts',
        columns: [
            { key: 'ont_sn', label: 'SN' },
            { key: 'ont_name', label: 'Nama' },
            { key: 'olt.olt_name', label: 'OLT' },
            { key: 'optical_status', label: 'Optic' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'olt_id', label: 'OLT', type: 'select', optionsRef: 'olts' },
            { name: 'ont_sn', label: 'SN ONT' },
            { name: 'ont_name', label: 'Nama ONT' },
            { name: 'pon_port', label: 'PON port' },
            { name: 'onu_id', label: 'ONU ID', type: 'number' },
            { name: 'ssid_name', label: 'SSID' },
            { name: 'wifi_password', label: 'WiFi password', type: 'password' },
            { name: 'optical_status', label: 'Optical status' },
            { name: 'optical_info', label: 'Optical info' },
            { name: 'genieacs_device_id', label: 'GenieACS device ID' },
            { name: 'status', label: 'Status' },
        ],
        deletable: true,
    },
    packages: {
        label: 'Packages',
        endpoint: '/api/v1/admin/packages',
        columns: [
            { key: 'package_name', label: 'Package' },
            { key: 'monthly_price', label: 'Harga', type: 'money' },
            { key: 'rate_limit_mbps', label: 'Mbps' },
            { key: 'ip_pool_count', label: 'Pool' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'package_name', label: 'Nama paket' },
            { name: 'monthly_price', label: 'Harga bulanan', type: 'number' },
            { name: 'ip_pool_count', label: 'IP pool count', type: 'number' },
            { name: 'rate_limit_mbps', label: 'Rate limit Mbps', type: 'number' },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
        ],
        deletable: true,
    },
    locations: {
        label: 'Locations',
        endpoint: '/api/v1/admin/locations',
        columns: [
            { key: 'location_code', label: 'Kode' },
            { key: 'location_name', label: 'Lokasi' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'location_code', label: 'Kode lokasi' },
            { name: 'location_name', label: 'Nama lokasi' },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
        ],
        deletable: true,
    },
};

const hotspotTabs = {
    profiles: {
        label: 'Profiles',
        endpoint: '/api/v1/admin/hotspot-profiles',
        columns: [
            { key: 'profile_name', label: 'Profile' },
            { key: 'validity_mode', label: 'Validity' },
            { key: 'data_limit_bytes', label: 'Data limit' },
            { key: 'selling_price', label: 'Jual', type: 'money' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'profile_name', label: 'Nama profile' },
            { name: 'validity_mode', label: 'Validity mode', type: 'select', options: baseSelects.hotspot_validity },
            { name: 'validity_days', label: 'Validity days', type: 'number' },
            { name: 'data_limit_bytes', label: 'Data limit bytes', type: 'number' },
            { name: 'price', label: 'Harga reseller', type: 'number' },
            { name: 'selling_price', label: 'Harga jual', type: 'number' },
            { name: 'user_lock', label: 'User lock', type: 'select', options: baseSelects.hotspot_lock },
            { name: 'expired_mode', label: 'Expired mode', type: 'select', options: baseSelects.hotspot_expired },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
        ],
        noDelete: true,
    },
    resellers: {
        label: 'Resellers',
        endpoint: '/api/v1/admin/resellers',
        columns: [
            { key: 'reseller_code', label: 'Kode' },
            { key: 'full_name', label: 'Nama' },
            { key: 'phone', label: 'Telepon' },
            { key: 'balance', label: 'Saldo', type: 'money' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'reseller_code', label: 'Kode reseller' },
            { name: 'full_name', label: 'Nama lengkap' },
            { name: 'phone', label: 'Telepon' },
            { name: 'balance', label: 'Saldo', type: 'number' },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.reseller_status },
        ],
    },
    batches: {
        label: 'Voucher batches',
        endpoint: '/api/v1/admin/voucher-batches',
        columns: [
            { key: 'batch_code', label: 'Batch' },
            { key: 'reseller.full_name', label: 'Reseller' },
            { key: 'hotspot_profile.profile_name', label: 'Profile' },
            { key: 'total_vouchers', label: 'Qty' },
            { key: 'total_cost', label: 'Cost', type: 'money' },
        ],
        fields: [
            { name: 'reseller_id', label: 'Reseller', type: 'select', optionsRef: 'resellers' },
            { name: 'hotspot_profile_id', label: 'Profile', type: 'select', optionsRef: 'hotspot_profiles' },
            { name: 'total_vouchers', label: 'Total voucher (max 500)', type: 'number', min: 1, max: 500 },
            { name: 'username_mode', label: 'Username mode', type: 'select', options: baseSelects.username_mode },
            { name: 'username_prefix', label: 'Username prefix' },
            { name: 'username_length', label: 'Username length', type: 'number' },
            { name: 'voucher_code_length', label: 'Voucher code length', type: 'number' },
            { name: 'password_mode', label: 'Password mode', type: 'select', options: baseSelects.password_mode },
            { name: 'password_length', label: 'Password length', type: 'number' },
        ],
        noEdit: true,
    },
    vouchers: {
        label: 'Vouchers',
        endpoint: '/api/v1/admin/hotspot-vouchers',
        columns: [
            { key: 'username', label: 'Username' },
            { key: 'password_masked', label: 'Password' },
            { key: 'reseller.full_name', label: 'Reseller' },
            { key: 'hotspot_profile.profile_name', label: 'Profile' },
            { key: 'locked_mac', label: 'MAC' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        noCreate: true,
        noEdit: true,
    },
};

const menu = [
    { key: 'dashboard', label: 'Dashboard', icon: 'DB' },
    { key: 'customers', label: 'Customers', icon: 'CU' },
    { key: 'services', label: 'Services', icon: 'SV' },
    { key: 'billing', label: 'Billing', icon: 'BI', adminOnly: true },
    { key: 'isolations', label: 'Isolations', icon: 'IS', adminOnly: true },
    { key: 'network', label: 'Network', icon: 'NW', adminOnly: true },
    { key: 'tickets', label: 'Tickets', icon: 'TK' },
    { key: 'work-orders', label: 'Work Orders', icon: 'WO' },
    { key: 'hotspot', label: 'Hotspot', icon: 'HS', adminOnly: true },
];

export function adminPanel({ page }) {
    return {
        page,
        activeTab: '',
        user: tokenStore.user(),
        routerSwitcher: { enabled: false, active_router_id: '', available_routers: [] },
        dashboard: null,
        items: [],
        pagination: {},
        filters: { search: '', page: 1, per_page: 15 },
        references: {
            customers: [],
            services: [],
            invoices: [],
            routers: [],
            olts: [],
            onts: [],
            packages: [],
            locations: [],
            technicians: [],
            available_vids: [],
            hotspot_profiles: [],
            resellers: [],
        },
        loading: false,
        saving: false,
        toasts: [],
        modal: { open: false, mode: 'create', title: '', fields: [], form: {}, errors: {}, message: '', endpoint: '', method: 'POST' },
        confirm: { open: false, title: '', message: '', action: null },
        detail: { open: false, title: '', row: null, status: '', reply: '' },
        ticketStatusOptions: select(['open', 'in_progress', 'resolved', 'closed']),

        async init() {
            const token = tokenStore.get();

            if (!token) {
                window.location.assign('/login');
                return;
            }

            api.setToken(token);
            this.activeTab = new URLSearchParams(window.location.search).get('tab') || this.defaultTab();

            try {
                const response = await api.get('/api/v1/auth/me');
                this.user = response.data;
                tokenStore.setUser(this.user);

                if (this.isTechnician() && this.forbiddenForTechnician(this.page)) {
                    window.location.assign('/admin/dashboard');
                    return;
                }

                await this.loadReferences();
                await this.loadPage();
            } catch (error) {
                this.toast('error', 'Session gagal', error.message);
            }
        },

        isTechnician() {
            return this.user?.role === 'technician';
        },

        forbiddenForTechnician(key) {
            return ['billing', 'isolations', 'network', 'hotspot'].includes(key);
        },

        visibleMenu() {
            return menu.filter((item) => !item.adminOnly || !this.isTechnician());
        },

        adminUrl(key) {
            return `/admin/${key}`;
        },

        currentConfig() {
            if (this.page === 'network') {
                return networkTabs[this.activeTab] ?? networkTabs.routers;
            }

            if (this.page === 'hotspot') {
                return hotspotTabs[this.activeTab] ?? hotspotTabs.profiles;
            }

            return modules[this.page] ?? modules.customers;
        },

        defaultTab() {
            if (this.page === 'network') return 'routers';
            if (this.page === 'hotspot') return 'profiles';
            return '';
        },

        hasTabs() {
            return ['network', 'hotspot'].includes(this.page);
        },

        currentTabs() {
            const tabs = this.page === 'network' ? networkTabs : hotspotTabs;
            return Object.entries(tabs).map(([key, value]) => ({ key, label: value.label }));
        },

        selectTab(key) {
            this.activeTab = key;
            this.filters.page = 1;
            window.history.replaceState({}, '', `${window.location.pathname}?tab=${key}`);
            this.loadPage();
        },

        currentTitle() {
            if (this.page === 'dashboard') return this.isTechnician() ? 'Technician Dashboard' : 'Dashboard';
            return this.currentConfig().title ?? this.currentConfig().label;
        },

        currentSectionLabel() {
            if (this.page === 'dashboard') return 'Operations';
            return this.currentConfig().section ?? human(this.page);
        },

        currentDescription() {
            return this.currentConfig().description ?? 'Kelola data operasional ISP dari API v1.';
        },

        currentColumns() {
            return this.currentConfig().columns ?? [];
        },

        canCreateCurrent() {
            const config = this.currentConfig();
            return !this.isTechnician() && !config.noCreate && Array.isArray(config.fields);
        },

        canEditCurrent() {
            const config = this.currentConfig();
            return !this.isTechnician() && !config.noEdit && Array.isArray(config.fields);
        },

        async loadReferences() {
            if (this.isTechnician()) return;

            const refs = await Promise.allSettled([
                api.get('/api/v1/admin/customer-references', { only_active: 1 }),
                api.get('/api/v1/admin/customers', { per_page: 100 }),
                api.get('/api/v1/admin/services', { per_page: 100 }),
                api.get('/api/v1/admin/invoices', { per_page: 100 }),
                api.get('/api/v1/admin/hotspot-profiles', { per_page: 100 }),
                api.get('/api/v1/admin/resellers', { per_page: 100 }),
            ]);

            if (refs[0].status === 'fulfilled') {
                Object.assign(this.references, refs[0].value.data);
            }

            const pagedKeys = ['customers', 'services', 'invoices', 'hotspot_profiles', 'resellers'];
            refs.slice(1).forEach((result, index) => {
                if (result.status === 'fulfilled') {
                    this.references[pagedKeys[index]] = result.value.data ?? [];
                }
            });

            if (this.user?.role === 'superadmin' && this.references.routers.length > 0 && !this.routerSwitcher.enabled) {
                this.routerSwitcher = {
                    enabled: true,
                    active_router_id: this.user.dashboard_active_router_id ?? '',
                    available_routers: [
                        { id: null, router_code: 'ALL', router_name: 'Semua Router', is_active: true },
                        ...this.references.routers,
                    ],
                };
            }
        },

        async loadPage() {
            if (this.page === 'dashboard') {
                await this.loadDashboard();
                return;
            }

            const config = this.currentConfig();
            this.loading = true;

            try {
                const params = { ...this.filters };
                if (config.collection) {
                    params.limit = this.filters.per_page;
                }

                const response = await api.get(config.endpoint, params);
                const rows = Array.isArray(response.data) ? response.data : [];

                this.items = rows.map((row) => ({
                    id: row.id ?? row[config.idKey ?? 'id'],
                    ...row,
                }));
                this.pagination = response.meta?.pagination ?? {
                    current_page: 1,
                    last_page: 1,
                    per_page: this.items.length,
                    total: this.items.length,
                    from: this.items.length > 0 ? 1 : 0,
                    to: this.items.length,
                };
            } catch (error) {
                this.items = [];
                this.toast('error', 'Gagal memuat data', error.message);
            } finally {
                this.loading = false;
            }
        },

        async loadDashboard() {
            this.loading = true;

            try {
                const endpoint = this.isTechnician() ? '/api/v1/technician/dashboard' : '/api/v1/admin/dashboard';
                const response = await api.get(endpoint);
                this.dashboard = response.data;

                const switcher = this.dashboard?.scope?.router_switcher;
                if (switcher) {
                    this.routerSwitcher = {
                        enabled: Boolean(switcher.enabled),
                        active_router_id: switcher.active_router_id ?? '',
                        available_routers: switcher.available_routers ?? [],
                    };
                }
            } catch (error) {
                this.toast('error', 'Dashboard gagal dimuat', error.message);
            } finally {
                this.loading = false;
            }
        },

        async switchRouter() {
            try {
                const routerId = this.routerSwitcher.active_router_id === '' ? null : Number(this.routerSwitcher.active_router_id);
                const response = await api.patch('/api/v1/admin/dashboard/router-switch', { router_id: routerId });
                this.dashboard = response.data;
                this.toast('success', 'Router dashboard diganti', 'Scope dashboard sudah diperbarui.');
            } catch (error) {
                this.toast('error', 'Router switch gagal', error.message);
            }
        },

        changePage(page) {
            if (page < 1 || page > (this.pagination.last_page ?? 1)) return;
            this.filters.page = page;
            this.loadPage();
        },

        openCreate() {
            const config = this.currentConfig();
            this.modal = {
                open: true,
                mode: 'create',
                title: `Tambah ${config.title ?? config.label}`,
                fields: this.resolveFields(config.fields),
                form: this.defaultsFor(config.fields),
                errors: {},
                message: '',
                endpoint: config.createEndpoint ?? config.endpoint,
                method: 'POST',
            };
        },

        openEdit(row) {
            const config = this.currentConfig();
            this.modal = {
                open: true,
                mode: 'edit',
                title: `Edit ${config.title ?? config.label}`,
                fields: this.resolveFields(config.fields),
                form: this.formFromRow(config.fields, row),
                errors: {},
                message: '',
                endpoint: `${config.endpoint}/${row.id}`,
                method: 'PATCH',
            };
        },

        async submitModal() {
            this.saving = true;
            this.modal.errors = {};
            this.modal.message = '';

            try {
                const payload = this.normalizePayload(this.modal.form, this.modal.fields);

                if (payload.total_vouchers && Number(payload.total_vouchers) > 500) {
                    throw { message: 'Total voucher maksimal 500 untuk UI Phase 1.', errors: { total_vouchers: ['Maximum 500 vouchers.'] } };
                }

                const response = this.modal.method === 'PATCH'
                    ? await api.patch(this.modal.endpoint, payload)
                    : await api.post(this.modal.endpoint, payload);

                this.toast('success', 'Berhasil', response.message ?? 'Data tersimpan.');
                this.closeModals();
                await this.loadReferences();
                await this.loadPage();
            } catch (error) {
                this.modal.message = error.message || 'Gagal menyimpan data.';
                this.modal.errors = error.errors || {};
            } finally {
                this.saving = false;
            }
        },

        async openDetail(row) {
            const config = this.currentConfig();
            let detailRow = row;

            if (!config.collection && row.id) {
                try {
                    const response = await api.get(`${config.endpoint}/${row.id}`);
                    detailRow = response.data;
                } catch {
                    detailRow = row;
                }
            }

            this.detail = {
                open: true,
                title: this.primaryLabel(detailRow),
                row: detailRow,
                status: detailRow.status ?? 'open',
                reply: '',
            };
        },

        async updateTicketStatus() {
            if (this.page !== 'tickets' || !this.detail.row?.id) return;

            try {
                const response = await api.patch(`/api/v1/admin/tickets/${this.detail.row.id}/status`, {
                    status: this.detail.status,
                });

                this.detail.row = response.data;
                this.detail.status = response.data.status;
                this.toast('success', 'Status ticket diperbarui', response.message);
                await this.loadPage();
            } catch (error) {
                this.toast('error', 'Update status gagal', error.message);
            }
        },

        async createTicketReply() {
            if (this.page !== 'tickets' || !this.detail.row?.id) return;

            try {
                const response = await api.post(`/api/v1/admin/tickets/${this.detail.row.id}/replies`, {
                    body: this.detail.reply,
                });

                const replies = [...(this.detail.row.replies ?? []), response.data];
                this.detail.row = { ...this.detail.row, replies };
                this.detail.reply = '';
                this.toast('success', 'Reply terkirim', response.message);
            } catch (error) {
                this.toast('error', 'Reply gagal', error.message);
            }
        },

        rowActions(row) {
            if (this.isTechnician()) return [];

            if (this.page === 'services') {
                const isolated = ['isolated', 'suspended'].includes(row.overall_status);
                return isolated
                    ? [{ key: 'release-service', label: 'Release', class: 'bg-emerald-50 text-emerald-800', handler: () => this.releaseService(row) }]
                    : [{ key: 'isolate-service', label: 'Isolate', class: 'bg-red-50 text-red-700', handler: () => this.isolateService(row) }];
            }

            if (this.page === 'billing') {
                const paid = row.payment_status === 'paid';
                return [
                    ...(!paid ? [{ key: 'pay-invoice', label: 'Bayar', class: 'bg-emerald-50 text-emerald-800', handler: () => this.openPayment(row) }] : []),
                    ...(!paid ? [{ key: 'mark-overdue', label: 'Overdue', class: 'bg-amber-50 text-amber-800', handler: () => this.markInvoiceOverdue(row) }] : []),
                ];
            }

            if (this.page === 'isolations') {
                if (['released', 'failed'].includes(row.status)) return [];

                return [
                    { key: 'release-isolation', label: 'Release', class: 'bg-emerald-50 text-emerald-800', handler: () => this.releaseIsolation(row.id) },
                    ...(row.status === 'pending'
                        ? [{ key: 'mark-applied', label: 'Applied', class: 'bg-amber-50 text-amber-800', handler: () => this.markIsolationApplied(row.id) }]
                        : []),
                ];
            }

            if (this.page === 'work-orders' && row.status !== 'completed') {
                return [{ key: 'complete-wo', label: 'Done', class: 'bg-emerald-50 text-emerald-800', handler: () => this.completeWorkOrder(row) }];
            }

            if (this.page === 'hotspot' && this.activeTab === 'vouchers' && row.status === 'generated') {
                return [{ key: 'activate-voucher', label: 'Activate', class: 'bg-amber-50 text-amber-800', handler: () => this.openVoucherActivation(row) }];
            }

            const config = this.currentConfig();
            if (config.deletable && !config.noDelete) {
                return [{ key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) }];
            }

            return [];
        },

        runRowAction(action) {
            action.handler();
        },

        isolateService(row) {
            this.askConfirm('Isolate service?', `Service ${row.service_code} akan dibuatkan record isolir manual.`, async () => {
                await api.post('/api/v1/admin/service-isolations', {
                    service_id: row.id,
                    isolation_type: 'manual',
                    notes: 'Manual isolate from admin UI.',
                });
                this.toast('success', 'Isolir dibuat', 'Job router akan diproses via queue.');
                await this.loadPage();
            });
        },

        async releaseService(row) {
            this.askConfirm('Release service?', `Mencari isolir aktif untuk ${row.service_code}, lalu release jika ada.`, async () => {
                const response = await api.get('/api/v1/admin/service-isolations/suggestions', { service_id: row.id, limit: 1 });
                const isolationId = response.data?.[0]?.open_isolation?.id;

                if (!isolationId) {
                    this.toast('error', 'Tidak ada isolir aktif', 'Release dilewati karena API tidak menemukan open isolation.');
                    return;
                }

                await api.patch(`/api/v1/admin/service-isolations/${isolationId}/release`, {
                    released_at: new Date().toISOString(),
                    notes: 'Manual release from admin UI.',
                });
                this.toast('success', 'Release dikirim', 'Release router akan diproses via queue.');
                await this.loadPage();
            });
        },

        releaseIsolation(id) {
            this.askConfirm('Release isolir?', 'Record isolir aktif akan dilepas.', async () => {
                await api.patch(`/api/v1/admin/service-isolations/${id}/release`, {
                    released_at: new Date().toISOString(),
                    notes: 'Manual release from admin UI.',
                });
                this.toast('success', 'Release dikirim', 'Release router akan diproses via queue.');
                await this.loadPage();
            });
        },

        markIsolationApplied(id) {
            this.askConfirm('Tandai applied?', 'Status isolir akan ditandai applied.', async () => {
                await api.patch(`/api/v1/admin/service-isolations/${id}/applied`, {
                    isolated_at: new Date().toISOString(),
                    notes: 'Marked applied from admin UI.',
                });
                this.toast('success', 'Status diperbarui', 'Isolir ditandai applied.');
                await this.loadPage();
            });
        },

        openPayment(invoice) {
            this.modal = {
                open: true,
                mode: 'payment',
                title: `Bayar ${invoice.invoice_number}`,
                fields: [
                    { name: 'invoice_id', label: 'Invoice ID', type: 'number' },
                    { name: 'amount_paid', label: 'Nominal dibayar', type: 'number' },
                    { name: 'payment_method', label: 'Metode pembayaran' },
                    { name: 'paid_at', label: 'Tanggal bayar', type: 'datetime-local' },
                    { name: 'reference_no', label: 'Referensi' },
                    { name: 'notes', label: 'Catatan', type: 'textarea' },
                ],
                form: {
                    invoice_id: invoice.id,
                    amount_paid: invoice.remaining_amount ?? invoice.total_amount,
                    payment_method: 'cash',
                    paid_at: this.localDateTime(),
                    reference_no: '',
                    notes: '',
                },
                errors: {},
                message: '',
                endpoint: '/api/v1/admin/payments',
                method: 'POST',
            };
        },

        markInvoiceOverdue(invoice) {
            this.askConfirm('Mark overdue?', `Invoice ${invoice.invoice_number} akan diubah overdue dan dapat trigger isolir otomatis.`, async () => {
                await api.patch(`/api/v1/admin/invoices/${invoice.id}/mark-overdue`, {});
                this.toast('success', 'Invoice overdue', 'Billing hook akan menangani isolir via queue.');
                await this.loadPage();
            });
        },

        completeWorkOrder(row) {
            this.askConfirm('Selesaikan WO?', `WO ${row.wo_number} akan ditandai completed.`, async () => {
                const payload = this.workOrderPayload(row);
                payload.status = 'completed';
                payload.completed_at = new Date().toISOString();

                await api.patch(`/api/v1/admin/work-orders/${row.id}`, payload);
                this.toast('success', 'WO selesai', 'Work order sudah ditandai completed.');
                await this.loadPage();
            });
        },

        openVoucherActivation(row) {
            this.modal = {
                open: true,
                mode: 'activate-voucher',
                title: `Aktivasi voucher ${row.username}`,
                fields: [
                    { name: 'mac_address', label: 'MAC address', placeholder: 'AA:BB:CC:DD:EE:FF' },
                    { name: 'login_at', label: 'Login at', type: 'datetime-local' },
                ],
                form: { mac_address: '', login_at: this.localDateTime() },
                errors: {},
                message: '',
                endpoint: `/api/v1/admin/hotspot-vouchers/${row.id}/activate`,
                method: 'POST',
            };
        },

        confirmDelete(row) {
            const config = this.currentConfig();
            this.askConfirm('Hapus/arsipkan data?', `${this.primaryLabel(row)} akan diproses sebagai destructive action.`, async () => {
                await api.delete(`${config.endpoint}/${row.id}`);
                this.toast('success', 'Data diproses', 'Data berhasil dihapus/diarsipkan.');
                await this.loadPage();
            });
        },

        askConfirm(title, message, action) {
            this.confirm = { open: true, title, message, action };
        },

        async runConfirm() {
            const action = this.confirm.action;
            this.closeModals();

            if (!action) return;

            try {
                await action();
            } catch (error) {
                this.toast('error', 'Aksi gagal', error.message);
            }
        },

        closeModals() {
            this.modal.open = false;
            this.confirm.open = false;
            this.detail.open = false;
        },

        async logout() {
            try {
                await api.post('/api/v1/auth/logout', {});
            } catch {
                // Local logout should still work when token is already invalid.
            }

            tokenStore.forget();
            window.location.assign('/login');
        },

        resolveFields(fields = []) {
            return fields.map((field) => {
                if (!field.optionsRef) return field;

                return {
                    ...field,
                    options: this.optionList(field.optionsRef),
                };
            });
        },

        optionList(ref) {
            const rows = this.references[ref] ?? [];
            const options = rows.map((row) => ({
                value: row.id,
                label: this.referenceLabel(ref, row),
            }));

            return [{ value: '', label: 'Pilih...' }, ...options];
        },

        referenceLabel(ref, row) {
            if (ref === 'customers') return `${row.customer_code ?? row.id} - ${row.full_name}`;
            if (ref === 'services') return `${row.service_code} - ${row.customer?.full_name ?? row.customer_id ?? ''}`;
            if (ref === 'routers') return `${row.router_code ?? row.id} - ${row.router_name}`;
            if (ref === 'olts') return `${row.olt_code ?? row.id} - ${row.olt_name}`;
            if (ref === 'onts') return `${row.ont_sn} - ${row.ont_name ?? row.status ?? ''}`;
            if (ref === 'packages') return `${row.package_name} - ${this.money(row.monthly_price)}`;
            if (ref === 'locations') return `${row.location_code ?? row.id} - ${row.location_name}`;
            if (ref === 'technicians') return `${row.technician_code ?? row.id} - ${row.full_name}`;
            if (ref === 'available_vids') return `${row.router?.router_name ?? row.router_id} / VID ${row.vid} / ${row.subnet_cidr ?? 'no subnet'}`;
            if (ref === 'hotspot_profiles') return `${row.profile_name} - ${this.money(row.selling_price)}`;
            if (ref === 'resellers') return `${row.reseller_code ?? row.id} - ${row.full_name}`;
            return row.name ?? row.label ?? row.id;
        },

        defaultsFor(fields = []) {
            return Object.fromEntries(fields.map((field) => {
                if (field.name === 'isolation_type') return [field.name, 'manual'];
                if (field.name === 'issue_now') return [field.name, true];
                if (field.name === 'is_active') return [field.name, true];
                if (field.name === 'username_mode') return [field.name, 'voucher_code'];
                if (field.name === 'password_mode') return [field.name, 'random_secure'];
                if (field.name === 'validity_mode') return [field.name, 'days_after_first_login'];
                if (field.name === 'user_lock') return [field.name, 'mac'];
                if (field.name === 'expired_mode') return [field.name, 'time_or_data'];
                if (field.name === 'billing_status') return [field.name, 'paid'];
                if (field.name === 'network_status') return [field.name, 'active'];
                if (field.name === 'overall_status') return [field.name, 'active'];
                if (field.name === 'access_mode') return [field.name, 'vlan'];
                if (field.name === 'isolation_method') return [field.name, 'address_list'];
                if (field.name === 'status') return [field.name, 'active'];
                if (field.type === 'select') return [field.name, field.options?.[0]?.value ?? ''];
                return [field.name, ''];
            }));
        },

        formFromRow(fields = [], row = {}) {
            const form = {};
            fields.forEach((field) => {
                const value = this.valueFor(row, field);
                form[field.name] = field.type === 'password' ? '' : (value ?? '');
            });
            return form;
        },

        normalizePayload(form, fields = []) {
            const payload = {};
            const fieldMap = Object.fromEntries(fields.map((field) => [field.name, field]));

            Object.entries(form).forEach(([key, value]) => {
                const field = fieldMap[key] ?? {};

                if (value === '') {
                    payload[key] = null;
                    return;
                }

                if (field.type === 'number') {
                    payload[key] = Number(value);
                    return;
                }

                if (field.options === baseSelects.bool) {
                    payload[key] = value === true || value === 'true' || value === 1 || value === '1';
                    return;
                }

                payload[key] = value;
            });

            return payload;
        },

        valueFor(row, column) {
            return String(column.key ?? column.name)
                .split('.')
                .reduce((value, segment) => value?.[segment], row);
        },

        formatValue(value, column = {}) {
            if (value === null || value === undefined || value === '') return '-';
            if (column.type === 'money') return this.money(value);
            if (typeof value === 'boolean') return value ? 'Aktif' : 'Tidak';
            return human(value);
        },

        statusClass(value) {
            const status = String(value ?? '').toLowerCase();
            if (statusGroups.green.includes(status) || value === true) return 'bg-emerald-100 text-emerald-800';
            if (statusGroups.red.includes(status) || value === false) return 'bg-red-100 text-red-800';
            if (statusGroups.yellow.includes(status)) return 'bg-amber-100 text-amber-800';
            return 'bg-slate-100 text-slate-700';
        },

        detailRows() {
            if (!this.detail.row) return [];

            const rows = [];
            const flatten = (value, prefix = '') => {
                Object.entries(value ?? {}).forEach(([key, item]) => {
                    if (item === null || item === undefined || Array.isArray(item)) return;
                    const label = prefix ? `${prefix}.${key}` : key;
                    if (typeof item === 'object') {
                        flatten(item, label);
                    } else {
                        rows.push({ label: human(label), value: this.formatValue(item) });
                    }
                });
            };

            flatten(this.detail.row);
            return rows.slice(0, 48);
        },

        ticketReplies() {
            return this.detail.row?.replies ?? [];
        },

        firstError(field) {
            const error = this.modal.errors?.[field];
            return Array.isArray(error) ? error[0] : error;
        },

        primaryLabel(row) {
            return row.full_name
                ?? row.service_code
                ?? row.invoice_number
                ?? row.ticket_number
                ?? row.wo_number
                ?? row.router_name
                ?? row.batch_code
                ?? row.username
                ?? `#${row.id}`;
        },

        dashboardCards() {
            const kpis = this.dashboard?.kpis ?? {};
            return Object.entries(kpis).slice(0, 5).map(([key, item]) => ({
                key,
                label: item.label ?? human(key),
                value: item.value ?? 0,
                meta: item.meta ? Object.values(item.meta).join(' / ') : '',
            }));
        },

        revenuePoints() {
            return this.dashboard?.charts?.monthly_revenue?.points ?? [];
        },

        pppPoints() {
            return this.dashboard?.charts?.ppp_active_trend?.points ?? [];
        },

        revenueMax() {
            return Math.max(1, ...this.revenuePoints().map((point) => Number(point.revenue ?? 0)));
        },

        pppMax() {
            return Math.max(1, ...this.pppPoints().map((point) => Number(point.active ?? 0)));
        },

        barHeight(value, max) {
            return Math.max(8, Math.round((Number(value ?? 0) / Math.max(1, max)) * 100));
        },

        technicianRanking() {
            const ranking = this.dashboard?.technician_ranking ?? [];
            return Array.isArray(ranking) ? ranking : Object.values(ranking);
        },

        money(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value ?? 0));
        },

        localDateTime() {
            return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        },

        workOrderPayload(row) {
            return {
                customer_id: row.customer_id,
                service_id: row.service_id,
                router_id: row.router_id,
                olt_id: row.olt_id,
                ont_id: row.ont_id,
                wo_type: row.wo_type,
                assigned_technician_id: row.assigned_technician_id,
                planned_monitor_vid: row.planned_monitor_vid,
                planned_internet_vid: row.planned_internet_vid,
                planned_subnet_cidr: row.planned_subnet_cidr,
                status: row.status,
                work_notes: row.work_notes,
                scheduled_at: row.scheduled_at,
                completed_at: row.completed_at,
            };
        },

        userInitials() {
            return String(this.user?.name ?? 'FX')
                .split(' ')
                .map((part) => part[0])
                .join('')
                .slice(0, 2)
                .toUpperCase();
        },

        toast(type, title, message) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, title, message });
            window.setTimeout(() => {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            }, 4200);
        },
    };
}
