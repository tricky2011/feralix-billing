import { api } from './services/api';
import { tokenStore } from './services/token';

const statusGroups = {
    green: ['active', 'paid', 'completed', 'resolved', 'closed', 'applied', 'online', 'assigned'],
    red: ['suspend', 'suspended', 'isolir', 'isolated', 'overdue', 'failed', 'canceled', 'terminated', 'expired', 'down', 'inactive'],
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
    user_role: select(['superadmin', 'admin', 'technician', 'reseller']),
    telegram_status: select(['active', 'inactive']),
    telegram_group_type: select(['teknisi', 'admin', 'owner', 'alert']),
    password_mode: select(['random_secure', 'same_as_username']),
    username_mode: select(['voucher_code', 'prefix_random']),
    cashflow_type: select(['income', 'expense']),
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
    cashflow: {
        title: 'Cashflow',
        section: 'Operations',
        description: 'Pencatatan pemasukan dan pengeluaran operasional, termasuk income otomatis dari payment.',
        endpoint: '/api/v1/admin/cashflows',
        columns: [
            { key: 'created_at', label: 'Tanggal', type: 'datetime' },
            { key: 'type', label: 'Type', type: 'status' },
            { key: 'amount', label: 'Amount', type: 'money' },
            { key: 'category.name', label: 'Category' },
            { key: 'description', label: 'Description' },
            { key: 'source', label: 'Source' },
        ],
        fields: [
            { name: 'type', label: 'Type', type: 'select', options: baseSelects.cashflow_type },
            { name: 'amount', label: 'Amount', type: 'number' },
            { name: 'category_id', label: 'Category', type: 'select', optionsRef: 'cashflow_categories' },
            { name: 'description', label: 'Description', type: 'textarea' },
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
    'router-sync': {
        title: 'Router Sync',
        section: 'Operations',
        description: 'Sinkronisasi router manual dari panel admin.',
        endpoint: '/api/v1/admin/router-sync',
        noCreate: true,
        noEdit: true,
        noDelete: true,
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
    'technician-dashboard': {
        title: 'Dashboard Teknisi',
        section: 'Support',
        description: 'KPI teknisi, target instalasi/ticket, ranking, dan ringkasan work order/ticket per periode.',
        endpoint: '/api/v1/admin/technician-dashboard',
        noCreate: true,
        noEdit: true,
        noDelete: true,
    },
    'user-management': {
        title: 'User Management',
        section: 'System',
        description: 'Kelola user internal, role, status akses, dan router assignment.',
        endpoint: '/api/v1/admin/users',
        columns: [
            { key: 'name', label: 'Nama' },
            { key: 'username', label: 'Username' },
            { key: 'email', label: 'Email' },
            { key: 'role', label: 'Role', type: 'status' },
            { key: 'is_active', label: 'Aktif', type: 'status' },
        ],
        fields: [
            { name: 'name', label: 'Nama lengkap' },
            { name: 'username', label: 'Username' },
            { name: 'email', label: 'Email' },
            { name: 'role', label: 'Role', type: 'select', options: baseSelects.user_role },
            { name: 'is_active', label: 'Aktif', type: 'select', options: baseSelects.bool },
            { name: 'router_ids', label: 'Router assignment', type: 'multiselect', optionsRef: 'routers' },
            { name: 'password', label: 'Password', type: 'password' },
            { name: 'password_confirmation', label: 'Konfirmasi password', type: 'password' },
        ],
        deletable: true,
    },
    'user-logs': {
        title: 'User Logs',
        section: 'System',
        description: 'Audit aktivitas login, logout, dan CRUD penting.',
        endpoint: '/api/v1/admin/activity-logs',
        columns: [
            { key: 'created_at', label: 'Waktu' },
            { key: 'user.username', label: 'User' },
            { key: 'action', label: 'Action', type: 'status' },
            { key: 'module', label: 'Module' },
            { key: 'ip', label: 'IP' },
            { key: 'description', label: 'Deskripsi' },
        ],
        noCreate: true,
        noEdit: true,
    },
    'settings-database': {
        title: 'Database Settings',
        section: 'Settings',
        description: 'Kelola koneksi database tersimpan tanpa overwrite file .env.',
        endpoint: '/api/v1/admin/database-settings',
        columns: [
            { key: 'host', label: 'Host' },
            { key: 'port', label: 'Port' },
            { key: 'database', label: 'Database' },
            { key: 'username', label: 'Username' },
            { key: 'has_password', label: 'Password', type: 'status' },
        ],
        fields: [
            { name: 'host', label: 'Host' },
            { name: 'port', label: 'Port', type: 'number' },
            { name: 'database', label: 'Nama database' },
            { name: 'username', label: 'Username' },
            { name: 'password', label: 'Password baru', type: 'password' },
        ],
        noCreate: true,
        noDelete: true,
    },
    'config-acs': {
        title: 'Config ACS',
        section: 'Router Management',
        description: 'Atur endpoint ACS per router, lakukan test ACS, dan jalankan sinkron ONT placeholder.',
        endpoint: '/api/v1/admin/routers',
        columns: [
            { key: 'name', label: 'Router' },
            { key: 'host', label: 'Host' },
            { key: 'acs_nbi_url', label: 'ACS NBI URL' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'has_acs_password', label: 'ACS Password', type: 'status' },
        ],
        noCreate: true,
        noEdit: true,
        noDelete: true,
    },
};

const networkTabs = {
    routers: {
        label: 'Router List',
        endpoint: '/api/v1/admin/routers',
        columns: [
            { key: 'name', label: 'Nama Router' },
            { key: 'host', label: 'Host' },
            { key: 'api_port', label: 'API Port' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'has_api_password', label: 'API Password', type: 'status' },
            { key: 'has_acs_password', label: 'ACS Password', type: 'status' },
        ],
        modalTabs: [
            { key: 'router-connection', label: 'Koneksi Router' },
            { key: 'invoice-branding', label: 'Branding Invoice placeholder' },
        ],
        modalTabNotes: {
            'invoice-branding': 'Placeholder: pengaturan branding invoice akan diaktifkan di fase berikutnya.',
        },
        fields: [
            { name: 'name', label: 'Nama router', tab: 'router-connection' },
            { name: 'host', label: 'Host / IP router', tab: 'router-connection' },
            { name: 'api_port', label: 'API port', type: 'number', tab: 'router-connection' },
            { name: 'timeout', label: 'Timeout (detik)', type: 'number', tab: 'router-connection' },
            { name: 'use_ssl', label: 'Gunakan SSL', type: 'select', options: baseSelects.bool, tab: 'router-connection' },
            { name: 'status', label: 'Status', type: 'select', options: select(['active', 'inactive']), tab: 'router-connection' },
            { name: 'api_username', label: 'API username', tab: 'router-connection' },
            { name: 'api_password', label: 'API password', type: 'password', passwordBadgeKey: 'has_api_password', tab: 'router-connection' },
            { name: 'acs_inform_url', label: 'ACS Inform URL', tab: 'router-connection' },
            { name: 'acs_nbi_url', label: 'ACS NBI URL', tab: 'router-connection' },
            { name: 'acs_username', label: 'ACS username', tab: 'router-connection' },
            { name: 'acs_password', label: 'ACS password', type: 'password', passwordBadgeKey: 'has_acs_password', tab: 'router-connection' },
            { name: 'description', label: 'Deskripsi', type: 'textarea', tab: 'router-connection' },
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

const telegramTabs = {
    bots: {
        label: 'Bots',
        title: 'Telegram Bots',
        section: 'Settings',
        description: 'Multi bot Telegram per router dengan token terenkripsi.',
        endpoint: '/api/v1/admin/telegram-bots',
        columns: [
            { key: 'bot_name', label: 'Nama bot' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'has_token', label: 'Token', type: 'status' },
            { key: 'groups_count', label: 'Groups' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'bot_name', label: 'Nama bot' },
            { name: 'token', label: 'Token bot', type: 'password' },
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.telegram_status },
        ],
        deletable: true,
    },
    groups: {
        label: 'Groups',
        title: 'Telegram Groups',
        section: 'Settings',
        description: 'Mapping chat group Telegram per bot, router, tipe, dan status.',
        endpoint: '/api/v1/admin/telegram-groups',
        columns: [
            { key: 'group_name', label: 'Nama group' },
            { key: 'bot.bot_name', label: 'Bot' },
            { key: 'router.router_name', label: 'Router' },
            { key: 'chat_id', label: 'Chat ID' },
            { key: 'group_type', label: 'Type', type: 'status' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
        fields: [
            { name: 'group_name', label: 'Nama group' },
            { name: 'telegram_bot_id', label: 'Bot', type: 'select', optionsRef: 'telegram_bots' },
            { name: 'router_id', label: 'Router', type: 'select', optionsRef: 'routers' },
            { name: 'chat_id', label: 'Chat ID' },
            { name: 'group_type', label: 'Type', type: 'select', options: baseSelects.telegram_group_type },
            { name: 'status', label: 'Status', type: 'select', options: baseSelects.telegram_status },
        ],
        deletable: true,
    },
};

const placeholderPages = {
    'upgrade-paket': {
        title: 'Upgrade Paket',
        section: 'Access',
        description: 'Alur upgrade paket pelanggan akan disiapkan tanpa mengganggu service aktif.',
        backendNeeds: [
            'Endpoint simulasi prorate dan perubahan paket service.',
            'Endpoint approval upgrade dan audit perubahan bandwidth.',
            'Integrasi invoice adjustment jika upgrade dilakukan di tengah periode.',
        ],
    },
    'service-plan': {
        title: 'Service Plan',
        section: 'Access',
        description: 'Katalog paket layanan ISP yang akan menjadi sumber pilihan paket pelanggan.',
        backendNeeds: [
            'Endpoint CRUD service plan publik untuk admin UI.',
            'Mapping plan ke package, rate limit, pool, dan kebijakan billing.',
            'Validasi plan aktif agar tidak dipakai oleh service baru jika dinonaktifkan.',
        ],
    },
    'ip-pools': {
        title: 'IP Pools',
        section: 'Access',
        description: 'Manajemen pool IP pelanggan untuk DHCP dan dedicated VID.',
        backendNeeds: [
            'Endpoint list/create/update IP pool per router dan VID.',
            'Deteksi overlap subnet dan range IP.',
            'Audit pemakaian pool oleh service aktif.',
        ],
    },
    'fiber-network-map': {
        title: 'Fiber Network Map',
        section: 'Network',
        description: 'Peta topologi fiber untuk backbone, ODC, ODP, dan pelanggan.',
        backendNeeds: [
            'Endpoint node/link topology fiber.',
            'Koordinat lokasi OLT, ODC, ODP, dan customer.',
            'Status kapasitas port dan occupancy per node.',
        ],
    },
    'odp-odc': {
        title: 'Manajemen ODP/ODC',
        section: 'Network',
        description: 'Inventori distribusi pasif fiber untuk ODP dan ODC.',
        backendNeeds: [
            'Endpoint CRUD ODP/ODC dan port capacity.',
            'Relasi ODP/ODC ke lokasi, OLT, customer, dan service.',
            'Audit port terpakai, kosong, dan rusak.',
        ],
    },
    monitoring: {
        title: 'Monitoring',
        section: 'Support',
        description: 'Pantauan operasional jaringan dan customer-facing incidents.',
        backendNeeds: [
            'Endpoint health monitoring router, OLT, ONT, dan PPP.',
            'Endpoint incident aktif dan alarm summary.',
            'Sumber data polling atau webhook monitoring.',
        ],
    },
    'ont-online': {
        title: 'ONT Online',
        section: 'ONT Monitoring',
        description: 'Daftar ONT online berdasarkan status monitoring terakhir.',
        backendNeeds: [
            'Endpoint ONT dengan filter status online.',
            'Timestamp last seen dan optical power terbaru.',
            'Filter OLT, lokasi, dan pelanggan.',
        ],
    },
    'ont-offline': {
        title: 'ONT Offline',
        section: 'ONT Monitoring',
        description: 'Daftar ONT offline untuk prioritas gangguan lapangan.',
        backendNeeds: [
            'Endpoint ONT dengan filter status offline/down.',
            'Korelasi ticket dan work order aktif.',
            'Filter durasi offline dan lokasi.',
        ],
    },
    'user-management': {
        title: 'User Management',
        section: 'System',
        description: 'Manajemen user internal, role, dan status akses panel.',
        backendNeeds: [
            'Endpoint CRUD user internal dan role assignment.',
            'Policy role superadmin/admin/technician.',
            'Reset password aman tanpa expose credential.',
        ],
    },
    'user-logs': {
        title: 'User Logs',
        section: 'System',
        description: 'Audit aktivitas user panel untuk operasi penting.',
        backendNeeds: [
            'Endpoint audit log dengan filter user, action, dan periode.',
            'Logging event login, logout, create, update, delete, dan router action.',
            'Export log untuk kebutuhan audit.',
        ],
    },
    'settings-telegram': {
        title: 'Telegram',
        section: 'Settings',
        description: 'Pengaturan bot Telegram untuk notifikasi billing, ticket, dan WO.',
        backendNeeds: [
            'Endpoint settings Telegram dengan masking token.',
            'Endpoint test send notification.',
            'Mapping channel/chat per event.',
        ],
    },
    'settings-database': {
        title: 'Database',
        section: 'Settings',
        description: 'Status koneksi database dan preferensi maintenance yang aman.',
        backendNeeds: [
            'Endpoint health database tanpa expose credential.',
            'Endpoint status backup/retention.',
            'Audit setting maintenance yang boleh diubah via UI.',
        ],
    },
};

Object.values(placeholderPages).forEach((page) => {
    page.placeholder = true;
    page.actionLabel = page.actionLabel ?? 'Menunggu endpoint backend';
});

const sidebarGroups = [
    {
        key: 'access',
        label: 'Access',
        items: [
            { page: 'customers', label: 'Customers', icon: 'CU' },
            { page: 'upgrade-paket', label: 'Upgrade Paket', icon: 'UP', adminOnly: true },
            { page: 'service-plan', label: 'Service Plan', icon: 'SP', adminOnly: true },
            { page: 'ip-pools', label: 'IP Pools', icon: 'IP', adminOnly: true },
        ],
    },
    {
        key: 'operations',
        label: 'Operations',
        items: [
            { page: 'billing', label: 'Invoice', icon: 'IN', badgeKey: 'invoices', adminOnly: true },
            { page: 'isolations', label: 'System Isolir Manual', icon: 'IS', badgeKey: 'isolations', adminOnly: true },
            { page: 'router-sync', label: 'Router Sync', icon: 'RS', adminOnly: true },
            { page: 'cashflow', label: 'Cashflow', icon: 'CF', adminOnly: true },
        ],
    },
    {
        key: 'network',
        label: 'Network',
        items: [
            { page: 'fiber-network-map', label: 'Fiber Network Map', icon: 'FM', adminOnly: true },
            { page: 'odp-odc', label: 'Manajemen ODP/ODC', icon: 'OD', adminOnly: true },
            { page: 'network', tab: 'locations', label: 'Master Lokasi', icon: 'LO', adminOnly: true },
            { page: 'network', tab: 'olts', label: 'Master OLT', icon: 'OL', adminOnly: true },
        ],
    },
    {
        key: 'support',
        label: 'Support',
        items: [
            { page: 'work-orders', label: 'Work Order', icon: 'WO', badgeKey: 'workOrders' },
            { page: 'technician-dashboard', label: 'Dashboard Teknisi', icon: 'DT' },
            { page: 'tickets', label: 'Helpdesk Tickets', icon: 'HT', badgeKey: 'tickets' },
            { page: 'monitoring', label: 'Monitoring', icon: 'MO' },
        ],
    },
    {
        key: 'ont-monitoring',
        label: 'ONT Monitoring',
        items: [
            { page: 'network', tab: 'onts', label: 'ONT List', icon: 'ON', adminOnly: true },
            { page: 'ont-online', label: 'ONT Online', icon: 'OO', adminOnly: true },
            { page: 'ont-offline', label: 'ONT Offline', icon: 'OF', adminOnly: true },
        ],
    },
    {
        key: 'router-management',
        label: 'Router Management',
        items: [
            { page: 'network', tab: 'routers', label: 'Router List', icon: 'RL', adminOnly: true },
            { page: 'config-acs', label: 'Config ACS', icon: 'AC', adminOnly: true },
        ],
    },
    {
        key: 'system',
        label: 'System',
        items: [
            { page: 'user-management', label: 'User Management', icon: 'UM', adminOnly: true },
            { page: 'user-logs', label: 'User Logs', icon: 'UL', adminOnly: true },
        ],
    },
    {
        key: 'settings',
        label: 'Settings',
        items: [
            { page: 'settings-telegram', label: 'Telegram', icon: 'TG', adminOnly: true },
            { page: 'settings-database', label: 'Database', icon: 'DB', adminOnly: true },
        ],
    },
    {
        key: 'hotspot',
        label: 'Hotspot',
        items: [
            { page: 'hotspot', tab: 'profiles', label: 'Hotspot Profiles', icon: 'HP', adminOnly: true },
            { page: 'hotspot', tab: 'resellers', label: 'Resellers', icon: 'RE', adminOnly: true },
            { page: 'hotspot', tab: 'batches', label: 'Voucher Batches', icon: 'VB', adminOnly: true },
            { page: 'hotspot', tab: 'vouchers', label: 'Vouchers', icon: 'VC', adminOnly: true },
        ],
    },
];

const uiText = {
    en: {
        dashboard: 'Dashboard',
        operations: 'Operations',
        searchPlaceholder: 'Search unpaid invoices',
        refresh: 'Refresh',
        notifications: 'Notifications',
        adminView: 'Admin view',
        technicianSafe: 'Technician-safe',
        language: 'Language',
        light: 'Light',
        dark: 'Dark',
        revenueToday: 'Revenue Today',
        billingToday: 'Billing Today',
        activeUsers: 'Active Users',
        tickets: 'Tickets',
        currentPeriod: 'Current period',
        openInvoices: 'Open invoices',
        monitored: 'Monitored',
        openTickets: 'Open tickets',
        revenueChart: 'Revenue, 30 days',
        invoiceTable: 'Recent invoices',
        networkStatus: 'Network status',
        helpdeskSummary: 'Helpdesk summary',
        recentActivity: 'Recent activity',
        noInvoices: 'No invoice data yet.',
        paid: 'Paid',
        overdue: 'Overdue',
        pending: 'Pending',
        footerTitle: 'About / System Info',
        footerBody: 'Feralix ISP Cloud, API v1, Sanctum session active.',
    },
    id: {
        dashboard: 'Dashboard',
        operations: 'Operasional',
        searchPlaceholder: 'Cari invoice belum lunas',
        refresh: 'Muat ulang',
        notifications: 'Notifikasi',
        adminView: 'Mode Admin',
        technicianSafe: 'Aman Teknisi',
        language: 'Bahasa',
        light: 'Terang',
        dark: 'Gelap',
        revenueToday: 'Pendapatan Hari Ini',
        billingToday: 'Billing Hari Ini',
        activeUsers: 'User Aktif',
        tickets: 'Tiket',
        currentPeriod: 'Periode berjalan',
        openInvoices: 'Invoice terbuka',
        monitored: 'Termonitor',
        openTickets: 'Tiket terbuka',
        revenueChart: 'Pendapatan, 30 hari',
        invoiceTable: 'Invoice terbaru',
        networkStatus: 'Status jaringan',
        helpdeskSummary: 'Ringkasan helpdesk',
        recentActivity: 'Aktivitas terbaru',
        noInvoices: 'Belum ada data invoice.',
        paid: 'Lunas',
        overdue: 'Overdue',
        pending: 'Pending',
        footerTitle: 'Tentang / Info Sistem',
        footerBody: 'Feralix Billing, API v1, sesi Sanctum aktif.',
    },
};

const navText = {
    en: {
        groups: {
            access: 'Access',
            operations: 'Operations',
            network: 'Network',
            support: 'Support',
            'ont-monitoring': 'ONT Monitoring',
            'router-management': 'Router Management',
            system: 'System',
            settings: 'Settings',
            hotspot: 'Hotspot',
        },
        items: {
            customers: 'Customers',
            'upgrade-paket': 'Upgrade Package',
            'service-plan': 'Service Plan',
            'ip-pools': 'IP Pools',
            billing: 'Invoice',
            isolations: 'System Manual',
            'router-sync': 'Router Sync',
            cashflow: 'Cashflow',
            'fiber-network-map': 'Fiber Network Map',
            'odp-odc': 'ODP/ODC Management',
            'network:locations': 'Master Location',
            'network:olts': 'Master OLT',
            'work-orders': 'Work Orders',
            'technician-dashboard': 'Technician Dashboard',
            tickets: 'Helpdesk Tickets',
            monitoring: 'Monitoring',
            'network:onts': 'ONT List',
            'ont-online': 'ONT Online',
            'ont-offline': 'ONT Offline',
            'network:routers': 'Router List',
            'config-acs': 'Config ACS',
            'user-management': 'User Management',
            'user-logs': 'User Logs',
            'settings-telegram': 'Telegram Integration',
            'settings-database': 'Database',
            'hotspot:profiles': 'Hotspot Profiles',
            'hotspot:resellers': 'Resellers',
            'hotspot:batches': 'Voucher Batches',
            'hotspot:vouchers': 'Vouchers',
        },
    },
    id: {
        groups: {
            access: 'Akses',
            operations: 'Operasional',
            network: 'Jaringan',
            support: 'Dukungan',
            'ont-monitoring': 'Monitoring ONT',
            'router-management': 'Manajemen Router',
            system: 'Sistem',
            settings: 'Pengaturan',
            hotspot: 'Hotspot',
        },
        items: {
            customers: 'Pelanggan',
            'upgrade-paket': 'Upgrade Paket',
            'service-plan': 'Paket Layanan',
            'ip-pools': 'IP Pools',
            billing: 'Invoice',
            isolations: 'Sistem Manual',
            'router-sync': 'Sinkron Router',
            cashflow: 'Cashflow',
            'fiber-network-map': 'Peta Fiber',
            'odp-odc': 'Manajemen ODP/ODC',
            'network:locations': 'Master Lokasi',
            'network:olts': 'Master OLT',
            'work-orders': 'Work Order',
            'technician-dashboard': 'Dashboard Teknisi',
            tickets: 'Tiket Helpdesk',
            monitoring: 'Monitoring',
            'network:onts': 'Daftar ONT',
            'ont-online': 'ONT Online',
            'ont-offline': 'ONT Offline',
            'network:routers': 'Daftar Router',
            'config-acs': 'Config ACS',
            'user-management': 'Manajemen User',
            'user-logs': 'Log User',
            'settings-telegram': 'Integrasi Telegram',
            'settings-database': 'Database',
            'hotspot:profiles': 'Hotspot Profiles',
            'hotspot:resellers': 'Resellers',
            'hotspot:batches': 'Voucher Batches',
            'hotspot:vouchers': 'Vouchers',
        },
    },
};

export function adminPanel({ page }) {
    return {
        page,
        activeTab: '',
        user: tokenStore.user(),
        theme: localStorage.getItem('feralix.theme') ?? 'light',
        language: localStorage.getItem('feralix.language') ?? 'id',
        globalSearch: '',
        sidebarOpen: false,
        userMenuOpen: false,
        collapsedGroups: {},
        badgeCounts: { isolations: 0, invoices: 0, workOrders: 0, tickets: 0 },
        routerSwitcher: { enabled: false, active_router_id: '', available_routers: [] },
        dashboard: null,
        items: [],
        pagination: {},
        filters: { search: '', page: 1, per_page: 15 },
        cashflow: {
            filters: {
                type: '',
                category_id: '',
                date_from: '',
                date_to: '',
            },
            summary: {
                total_income: '0.00',
                total_expense: '0.00',
                net_balance: '0.00',
                monthly: [],
            },
        },
        technicianDashboard: {
            loading: false,
            exporting: false,
            filters: {
                technician_id: '',
                period: 'month',
                date_from: '',
                date_to: '',
            },
            data: {
                filters: {
                    technician_id: null,
                    period: 'month',
                    date_from: '',
                    date_to: '',
                    technicians: [],
                },
                summary: {
                    total_installations: 0,
                    completed_installations: 0,
                    open_tickets: 0,
                    closed_tickets: 0,
                    total_points: 0,
                },
                targets: {
                    installation_target: 20,
                    ticket_target: 50,
                },
                ranking: [],
                work_orders: [],
                tickets: [],
                point_rules: [],
            },
        },
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
            tickets: [],
            work_orders: [],
            telegram_bots: [],
            cashflow_categories: [],
        },
        loading: false,
        saving: false,
        toasts: [],
        modal: {
            open: false,
            mode: 'create',
            title: '',
            fields: [],
            form: {},
            errors: {},
            message: '',
            endpoint: '',
            method: 'POST',
            tabs: [],
            activeTab: '',
            tabNotes: {},
            passwordState: {},
        },
        confirm: { open: false, title: '', message: '', action: null },
        detail: { open: false, title: '', row: null, status: '', reply: '' },
        acsConfig: {
            router_id: '',
            router_name: '',
            host: '',
            status: '',
            acs_inform_url: '',
            acs_nbi_url: '',
            acs_username: '',
            acs_password: '',
            has_acs_password: false,
            loading: false,
            saving: false,
            result: null,
        },
        manualIsolir: {
            router_id: '',
            isolir: {
                search: '',
                loading: false,
                options: [],
                user_id: '',
            },
            release: {
                search: '',
                loading: false,
                options: [],
                user_id: '',
            },
            result: null,
        },
        routerSync: {
            router_id: '',
            loading: false,
            result: null,
            recentLogs: [],
            loadingLogs: false,
        },
        ticketStatusOptions: select(['open', 'in_progress', 'resolved', 'closed']),

        async init() {
            this.loadSidebarState();
            this.applyTheme();

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

                if (this.isTechnician() && this.page === 'dashboard') {
                    window.location.assign('/admin/technician-dashboard');
                    return;
                }

                if (this.isTechnician() && this.forbiddenForTechnician(this.page)) {
                    window.location.assign('/admin/dashboard');
                    return;
                }

                await this.loadReferences();
                await this.loadPage();
                await this.loadSidebarBadges();
            } catch (error) {
                this.toast('error', 'Session gagal', error.message);
            }
        },

        isTechnician() {
            return this.user?.role === 'technician';
        },

        forbiddenForTechnician(key) {
            return [
                'billing',
                'isolations',
                'network',
                'hotspot',
                'upgrade-paket',
                'service-plan',
                'ip-pools',
                'router-sync',
                'cashflow',
                'fiber-network-map',
                'odp-odc',
                'ont-online',
                'ont-offline',
                'config-acs',
                'user-management',
                'user-logs',
                'settings-telegram',
                'settings-database',
            ].includes(key);
        },

        visibleSidebarGroups() {
            return sidebarGroups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => !item.adminOnly || !this.isTechnician()),
                }))
                .filter((group) => group.items.length > 0);
        },

        ui(key) {
            return uiText[this.language]?.[key] ?? uiText.en[key] ?? key;
        },

        groupLabel(group) {
            return navText[this.language]?.groups?.[group.key] ?? group.label;
        },

        itemLabel(item) {
            const key = item.tab ? `${item.page}:${item.tab}` : item.page;
            return navText[this.language]?.items?.[key] ?? item.label;
        },

        roleLabel() {
            return this.isTechnician() ? this.ui('technicianSafe') : this.ui('adminView');
        },

        setLanguage(language) {
            this.language = language;
            localStorage.setItem('feralix.language', language);
        },

        setTheme(theme) {
            this.theme = theme;
            localStorage.setItem('feralix.theme', theme);
            this.applyTheme();
        },

        toggleTheme() {
            this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
        },

        applyTheme() {
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
        },

        applyGlobalSearch() {
            this.filters.search = this.globalSearch;
            this.filters.page = 1;
            if (this.page !== 'dashboard') {
                this.loadPage();
            }
        },

        adminUrl(key) {
            return `/admin/${key}`;
        },

        menuItemUrl(item) {
            const query = item.tab ? `?tab=${item.tab}` : '';
            return `${this.adminUrl(item.page)}${query}`;
        },

        isMenuItemActive(item) {
            if (this.page !== item.page) return false;
            if (item.tab) return this.activeTab === item.tab;
            return true;
        },

        loadSidebarState() {
            try {
                this.collapsedGroups = JSON.parse(localStorage.getItem('feralix.sidebar.collapsed') ?? '{}') ?? {};
            } catch {
                this.collapsedGroups = {};
            }
        },

        groupOpen(key) {
            return this.collapsedGroups[key] !== true;
        },

        toggleGroup(key) {
            this.collapsedGroups = {
                ...this.collapsedGroups,
                [key]: !this.collapsedGroups[key],
            };
            localStorage.setItem('feralix.sidebar.collapsed', JSON.stringify(this.collapsedGroups));
        },

        badgeFor(item) {
            if (!item.badgeKey) return null;
            return this.badgeCounts[item.badgeKey] ?? 0;
        },

        badgeLabel(item) {
            const count = this.badgeFor(item);
            if (count === null) return '';
            return count > 99 ? '99+' : String(count);
        },

        totalNotifications() {
            return Object.values(this.badgeCounts).reduce((total, count) => total + Number(count ?? 0), 0);
        },

        currentConfig() {
            if (this.page === 'network') {
                return networkTabs[this.activeTab] ?? networkTabs.routers;
            }

            if (this.page === 'hotspot') {
                return hotspotTabs[this.activeTab] ?? hotspotTabs.profiles;
            }

            if (this.page === 'settings-telegram') {
                return telegramTabs[this.activeTab] ?? telegramTabs.bots;
            }

            if (modules[this.page]) {
                return modules[this.page];
            }

            return placeholderPages[this.page] ?? modules.customers;
        },

        defaultTab() {
            if (this.page === 'network') return 'routers';
            if (this.page === 'hotspot') return 'profiles';
            if (this.page === 'settings-telegram') return 'bots';
            return '';
        },

        hasTabs() {
            return ['network', 'hotspot', 'settings-telegram'].includes(this.page);
        },

        currentTabs() {
            if (!this.hasTabs()) return [];

            const tabs = this.page === 'network'
                ? networkTabs
                : (this.page === 'hotspot' ? hotspotTabs : telegramTabs);
            return Object.entries(tabs).map(([key, value]) => ({ key, label: value.label }));
        },

        selectTab(key) {
            this.activeTab = key;
            this.filters.page = 1;
            window.history.replaceState({}, '', `${window.location.pathname}?tab=${key}`);
            this.loadPage();
        },

        currentTitle() {
            if (this.page === 'dashboard') return this.isTechnician() ? this.itemLabel({ page: 'technician-dashboard' }) : this.ui('dashboard');
            return this.currentConfig().title ?? this.currentConfig().label;
        },

        currentSectionLabel() {
            if (this.page === 'dashboard') return this.ui('operations');
            return this.currentConfig().section ?? human(this.page);
        },

        currentDescription() {
            return this.currentConfig().description ?? 'Kelola data operasional ISP dari API v1.';
        },

        isCashflowPage() {
            return this.page === 'cashflow';
        },

        cashflowTypeOptions() {
            return [{ value: '', label: 'Semua type' }, ...baseSelects.cashflow_type];
        },

        cashflowCategoryOptions() {
            const selectedType = this.cashflow.filters.type;
            const rows = (this.references.cashflow_categories ?? [])
                .filter((category) => !selectedType || category.type === selectedType);

            return [
                { value: '', label: 'Semua category' },
                ...rows.map((category) => ({
                    value: category.id,
                    label: `${category.name} (${human(category.type)})`,
                })),
            ];
        },

        applyCashflowFilters() {
            this.filters.page = 1;
            this.loadPage();
        },

        resetCashflowFilters() {
            this.cashflow.filters = {
                type: '',
                category_id: '',
                date_from: '',
                date_to: '',
            };
            this.filters.page = 1;
            this.loadPage();
        },

        technicianPeriodOptions() {
            return [
                { value: 'today', label: this.language === 'id' ? 'Hari ini' : 'Today' },
                { value: 'week', label: this.language === 'id' ? 'Minggu ini' : 'This week' },
                { value: 'month', label: this.language === 'id' ? 'Bulan ini' : 'This month' },
                { value: 'custom', label: this.language === 'id' ? 'Custom' : 'Custom' },
            ];
        },

        technicianFilterOptions() {
            return this.technicianDashboard.data?.filters?.technicians ?? [];
        },

        onTechnicianDashboardPeriodChanged() {
            if (this.technicianDashboard.filters.period !== 'custom') {
                this.technicianDashboard.filters.date_from = '';
                this.technicianDashboard.filters.date_to = '';
            }
        },

        applyTechnicianDashboardFilters() {
            if (this.technicianDashboard.filters.period !== 'custom') {
                this.technicianDashboard.filters.date_from = '';
                this.technicianDashboard.filters.date_to = '';
            }

            this.loadTechnicianDashboard();
        },

        resetTechnicianDashboardFilters() {
            this.technicianDashboard.filters = {
                technician_id: this.isTechnician()
                    ? String(this.user?.technician_id ?? '')
                    : '',
                period: 'month',
                date_from: '',
                date_to: '',
            };

            this.loadTechnicianDashboard();
        },

        technicianDashboardParams() {
            const filters = this.technicianDashboard.filters;
            const params = {
                technician_id: filters.technician_id,
                period: filters.period,
            };

            if (filters.period === 'custom') {
                params.date_from = filters.date_from;
                params.date_to = filters.date_to;
            }

            return params;
        },

        async loadTechnicianDashboard() {
            this.loading = true;
            this.technicianDashboard.loading = true;

            try {
                const response = await api.get(
                    '/api/v1/admin/technician-dashboard',
                    this.technicianDashboardParams(),
                );

                const payload = response.data ?? {};
                const payloadFilters = payload.filters ?? {};

                this.technicianDashboard.data = {
                    filters: {
                        technician_id: payloadFilters.technician_id ?? null,
                        period: payloadFilters.period ?? this.technicianDashboard.filters.period ?? 'month',
                        date_from: payloadFilters.date_from ?? '',
                        date_to: payloadFilters.date_to ?? '',
                        technicians: Array.isArray(payloadFilters.technicians)
                            ? payloadFilters.technicians
                            : [],
                    },
                    summary: {
                        total_installations: payload.summary?.total_installations ?? 0,
                        completed_installations: payload.summary?.completed_installations ?? 0,
                        open_tickets: payload.summary?.open_tickets ?? 0,
                        closed_tickets: payload.summary?.closed_tickets ?? 0,
                        total_points: payload.summary?.total_points ?? 0,
                    },
                    targets: {
                        installation_target: payload.targets?.installation_target ?? 20,
                        ticket_target: payload.targets?.ticket_target ?? 50,
                    },
                    ranking: Array.isArray(payload.ranking) ? payload.ranking : [],
                    work_orders: Array.isArray(payload.work_orders) ? payload.work_orders : [],
                    tickets: Array.isArray(payload.tickets) ? payload.tickets : [],
                    point_rules: Array.isArray(payload.point_rules) ? payload.point_rules : [],
                };

                this.technicianDashboard.filters.technician_id = payloadFilters.technician_id === null
                    ? ''
                    : String(payloadFilters.technician_id ?? '');
                this.technicianDashboard.filters.period = payloadFilters.period ?? this.technicianDashboard.filters.period;
                this.technicianDashboard.filters.date_from = payloadFilters.date_from ?? '';
                this.technicianDashboard.filters.date_to = payloadFilters.date_to ?? '';
            } catch (error) {
                this.toast('error', 'Dashboard teknisi gagal dimuat', error.message);
            } finally {
                this.loading = false;
                this.technicianDashboard.loading = false;
            }
        },

        async exportTechnicianDashboardPdf() {
            this.technicianDashboard.exporting = true;

            try {
                const response = await api.post('/api/v1/admin/technician-dashboard/export-pdf', {});
                this.toast('success', this.language === 'id' ? 'Export PDF' : 'PDF Export', response.message);
            } catch (error) {
                this.toast('error', this.language === 'id' ? 'Export PDF gagal' : 'PDF export failed', error.message);
            } finally {
                this.technicianDashboard.exporting = false;
            }
        },

        activeManualRouters() {
            const routers = this.references.routers ?? [];

            return routers.filter((router) => router.is_active !== false);
        },

        manualRouterLabel(router) {
            return `${router.router_code ?? router.id} - ${router.router_name ?? '-'}`;
        },

        manualSuggestionLabel(item) {
            const target = item.target_identifier ?? item.target_subnet ?? '-';
            const openInfo = item.open_isolation?.id
                ? ` | Open isolation #${item.open_isolation.id}`
                : '';

            return `${item.service_code ?? '#'} | ${item.customer_name ?? '-'} | ${item.target_type ?? '-'}: ${target}${openInfo}`;
        },

        onManualRouterChanged() {
            this.manualIsolir.isolir.options = [];
            this.manualIsolir.isolir.user_id = '';
            this.manualIsolir.release.options = [];
            this.manualIsolir.release.user_id = '';
            this.manualIsolir.result = null;
        },

        canRunManualOperation(mode) {
            const operation = this.manualIsolir?.[mode];

            return Boolean(
                Number(this.manualIsolir.router_id) > 0 &&
                operation?.user_id &&
                !operation?.loading,
            );
        },

        setManualOperationResult(result) {
            this.manualIsolir.result = {
                success: Boolean(result?.success),
                message: result?.message ?? 'Operation finished.',
                data: result?.data ?? {},
                at: new Date().toISOString(),
            };
        },

        activeRouterSyncRouters() {
            const routers = this.references.routers ?? [];

            return routers.filter((router) => router.is_active !== false);
        },

        routerSyncRouterLabel(router) {
            return `${router.router_code ?? router.id} - ${router.router_name ?? '-'}`;
        },

        onRouterSyncRouterChanged() {
            this.routerSync.result = null;
        },

        canRunRouterSyncOperation() {
            return Number(this.routerSync.router_id) > 0 && !this.routerSync.loading;
        },

        setRouterSyncResult(result, operation) {
            this.routerSync.result = {
                success: Boolean(result?.success),
                operation,
                message: result?.message ?? 'Operasi sinkronisasi selesai.',
                data: result?.data ?? {},
                at: new Date().toISOString(),
            };
        },

        isPlaceholderCurrent() {
            return this.currentConfig().placeholder === true;
        },

        currentColumns() {
            return this.currentConfig().columns ?? [];
        },

        canCreateCurrent() {
            const config = this.currentConfig();
            if (config.placeholder) return false;
            return !this.isTechnician() && !config.noCreate && Array.isArray(config.fields);
        },

        canEditCurrent() {
            const config = this.currentConfig();
            if (config.placeholder) return false;
            return !this.isTechnician() && !config.noEdit && Array.isArray(config.fields);
        },

        canEditRow(row) {
            if (this.isCashflowPage()) {
                return row?.can_update !== false && row?.source !== 'system';
            }

            return true;
        },

        canDeleteRow(row) {
            if (this.isCashflowPage()) {
                return row?.can_delete !== false && row?.source !== 'system';
            }

            return true;
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
                api.get('/api/v1/admin/tickets', { per_page: 10 }),
                api.get('/api/v1/admin/work-orders', { per_page: 10 }),
                api.get('/api/v1/admin/telegram-bots', { per_page: 100 }),
            ]);

            if (refs[0].status === 'fulfilled') {
                Object.assign(this.references, refs[0].value.data);
            }

            const pagedKeys = ['customers', 'services', 'invoices', 'hotspot_profiles', 'resellers', 'tickets', 'work_orders', 'telegram_bots'];
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

            if (this.page === 'technician-dashboard') {
                await this.loadTechnicianDashboard();
                return;
            }

            if (this.page === 'router-sync') {
                await this.loadRouterSyncPage();
                return;
            }

            const config = this.currentConfig();

            if (config.placeholder) {
                this.items = [];
                this.pagination = {
                    current_page: 1,
                    last_page: 1,
                    per_page: this.filters.per_page,
                    total: 0,
                    from: 0,
                    to: 0,
                };
                this.loading = false;
                return;
            }

            this.loading = true;

            try {
                const params = this.buildCurrentParams(config);

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

                if (this.isCashflowPage()) {
                    this.applyCashflowMeta(response.meta ?? {});
                    await this.loadCashflowSummary();
                }

                if (this.page === 'config-acs') {
                    await this.syncAcsRouterSelection();
                }
            } catch (error) {
                this.items = [];
                this.toast('error', 'Gagal memuat data', error.message);
            } finally {
                this.loading = false;
            }
        },

        async loadRouterSyncPage() {
            this.loading = false;

            const activeRouters = this.activeRouterSyncRouters();
            const selectedRouter = activeRouters.find((router) => Number(router.id) === Number(this.routerSync.router_id));

            if (!selectedRouter) {
                this.routerSync.router_id = activeRouters[0]?.id ?? '';
                this.routerSync.result = null;
            }

            this.items = [];
            this.pagination = {
                current_page: 1,
                last_page: 1,
                per_page: this.filters.per_page,
                total: 0,
                from: 0,
                to: 0,
            };

            await this.loadRouterSyncLogs();
        },

        async loadRouterSyncLogs() {
            this.routerSync.loadingLogs = true;

            try {
                const response = await api.get('/api/v1/admin/activity-logs', {
                    module: 'router-sync',
                    per_page: 10,
                });

                const logs = Array.isArray(response.data) ? response.data : [];
                this.routerSync.recentLogs = logs
                    .filter((log) => String(log.action ?? '').startsWith('router_sync.'))
                    .slice(0, 10);
            } catch (error) {
                this.routerSync.recentLogs = [];
                this.toast('error', 'Riwayat router sync gagal dimuat', error.message);
            } finally {
                this.routerSync.loadingLogs = false;
            }
        },

        async runRouterSync(type) {
            const endpoints = {
                pppoe: '/api/v1/admin/router-sync/pppoe',
                static: '/api/v1/admin/router-sync/static',
                'address-list': '/api/v1/admin/router-sync/address-list',
                all: '/api/v1/admin/router-sync/all',
            };
            const labels = {
                pppoe: 'Sync PPPoE',
                static: 'Sync Static',
                'address-list': 'Sync Address List',
                all: 'Sync All',
            };

            const endpoint = endpoints[type];
            const label = labels[type] ?? 'Router Sync';

            if (!endpoint) {
                return;
            }

            const routerId = Number(this.routerSync.router_id);

            if (!routerId) {
                this.setRouterSyncResult({
                    success: false,
                    message: 'Pilih router aktif terlebih dahulu sebelum sinkronisasi.',
                    data: {},
                }, label);
                return;
            }

            this.routerSync.loading = true;

            try {
                const response = await api.post(endpoint, {
                    router_id: routerId,
                });

                this.setRouterSyncResult(response, label);
                this.toast(response.success ? 'success' : 'error', label, response.message);
                await this.loadRouterSyncLogs();
            } catch (error) {
                this.setRouterSyncResult({
                    success: false,
                    message: error.message || `${label} gagal diproses.`,
                    data: {
                        errors: error.errors ?? {},
                    },
                }, label);
                this.toast('error', `${label} gagal`, error.message);
            } finally {
                this.routerSync.loading = false;
            }
        },

        buildCurrentParams(config) {
            const params = { ...this.filters };

            if (this.isCashflowPage()) {
                params.type = this.cashflow.filters.type;
                params.category_id = this.cashflow.filters.category_id;
                params.date_from = this.cashflow.filters.date_from;
                params.date_to = this.cashflow.filters.date_to;
            }

            if (config.collection) {
                params.limit = this.filters.per_page;
            }

            return params;
        },

        applyCashflowMeta(meta = {}) {
            if (Array.isArray(meta.categories)) {
                this.references.cashflow_categories = meta.categories;
            }
        },

        async loadCashflowSummary() {
            try {
                const response = await api.get('/api/v1/admin/cashflows/summary', {
                    category_id: this.cashflow.filters.category_id,
                    date_from: this.cashflow.filters.date_from,
                    date_to: this.cashflow.filters.date_to,
                    search: this.filters.search,
                });

                this.cashflow.summary = {
                    total_income: response.data?.total_income ?? '0.00',
                    total_expense: response.data?.total_expense ?? '0.00',
                    net_balance: response.data?.net_balance ?? '0.00',
                    monthly: Array.isArray(response.data?.monthly) ? response.data.monthly : [],
                };
            } catch (error) {
                this.cashflow.summary = {
                    total_income: '0.00',
                    total_expense: '0.00',
                    net_balance: '0.00',
                    monthly: [],
                };
                this.toast('error', 'Summary cashflow gagal dimuat', error.message);
            }
        },

        async syncAcsRouterSelection() {
            if (this.page !== 'config-acs') return;

            if (!Array.isArray(this.items) || this.items.length === 0) {
                this.acsConfig = {
                    ...this.acsConfig,
                    router_id: '',
                    router_name: '',
                    host: '',
                    status: '',
                    acs_inform_url: '',
                    acs_nbi_url: '',
                    acs_username: '',
                    acs_password: '',
                    has_acs_password: false,
                };
                return;
            }

            const selected = this.items.find((item) => Number(item.id) === Number(this.acsConfig.router_id));
            const targetId = selected?.id ?? this.items[0].id;

            if (Number(targetId) !== Number(this.acsConfig.router_id) || this.acsConfig.router_name === '') {
                await this.selectAcsRouter(targetId);
            }
        },

        async refreshCurrentPage() {
            await this.loadPage();
            await this.loadSidebarBadges();
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

        async loadSidebarBadges() {
            const endpoints = {
                isolations: ['/api/v1/admin/service-isolations', { status: 'pending', per_page: 1 }],
                invoices: ['/api/v1/admin/invoices/unpaid', { per_page: 1 }],
                workOrders: ['/api/v1/admin/work-orders', { status: 'open', per_page: 1 }],
                tickets: ['/api/v1/admin/tickets', { status: 'open', per_page: 1 }],
            };

            const requests = Object.entries(endpoints).map(([key, [endpoint, params]]) => (
                api.get(endpoint, params).then((response) => ({ key, total: this.badgeTotal(response) }))
            ));

            const results = await Promise.allSettled(requests);
            const counts = { isolations: 0, invoices: 0, workOrders: 0, tickets: 0 };

            results.forEach((result) => {
                if (result.status === 'fulfilled') {
                    counts[result.value.key] = result.value.total;
                }
                // Badge endpoints are best-effort; failed/unavailable endpoints fall back to 0 so navigation never blocks.
            });

            this.badgeCounts = counts;
        },

        badgeTotal(response) {
            return Number(response.meta?.pagination?.total ?? response.meta?.total ?? (Array.isArray(response.data) ? response.data.length : 0));
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
            const tabs = config.modalTabs ?? [];

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
                tabs,
                activeTab: tabs[0]?.key ?? '',
                tabNotes: config.modalTabNotes ?? {},
                passwordState: {
                    has_api_password: false,
                    has_acs_password: false,
                },
            };
        },

        openEdit(row) {
            if (!this.canEditRow(row)) {
                return;
            }

            const config = this.currentConfig();
            const tabs = config.modalTabs ?? [];

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
                tabs,
                activeTab: tabs[0]?.key ?? '',
                tabNotes: config.modalTabNotes ?? {},
                passwordState: {
                    has_api_password: Boolean(row.has_api_password),
                    has_acs_password: Boolean(row.has_acs_password),
                },
            };
        },

        selectModalTab(tabKey) {
            this.modal.activeTab = tabKey;
        },

        modalVisibleFields() {
            if (!this.modal.activeTab) return this.modal.fields ?? [];
            return (this.modal.fields ?? []).filter((field) => !field.tab || field.tab === this.modal.activeTab);
        },

        modalActiveTabNote() {
            if (!this.modal.activeTab) return '';
            return this.modal.tabNotes?.[this.modal.activeTab] ?? '';
        },

        passwordBadgeLabel(field) {
            const current = this.modal.form?.[field.name];

            if (typeof current === 'string' && current !== '') {
                return 'Password akan diperbarui';
            }

            const key = field.passwordBadgeKey;
            const hasValue = Boolean(key && this.modal.passwordState?.[key]);

            return hasValue ? 'Password tersimpan' : 'Password belum ada';
        },

        passwordBadgeClass(field) {
            const label = this.passwordBadgeLabel(field).toLowerCase();
            if (label.includes('diperbarui')) return 'bg-blue-100 text-blue-700';
            if (label.includes('tersimpan')) return 'bg-emerald-100 text-emerald-700';
            return 'bg-amber-100 text-amber-700';
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

            if (this.page === 'network' && this.activeTab === 'routers') {
                const config = this.currentConfig();
                return [
                    { key: 'test-connection', label: 'Test Connection', class: 'bg-emerald-50 text-emerald-800', handler: () => this.testRouterConnection(row) },
                    ...(config.deletable && !config.noDelete
                        ? [{ key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) }]
                        : []),
                ];
            }

            if (this.page === 'config-acs') {
                return [
                    { key: 'pick-router', label: 'Pilih Router', class: 'bg-slate-100 text-slate-700', handler: () => this.selectAcsRouter(row.id) },
                    { key: 'test-acs', label: 'Test ACS', class: 'bg-emerald-50 text-emerald-800', handler: () => this.testAcsRouter(row.id) },
                    { key: 'sync-ont', label: 'Sync ONT', class: 'bg-blue-50 text-blue-700', handler: () => this.syncOntRouter(row.id) },
                ];
            }

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

            if (this.page === 'user-management') {
                return [
                    {
                        key: row.is_active ? 'disable-user' : 'enable-user',
                        label: row.is_active ? 'Disable' : 'Enable',
                        class: row.is_active ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800',
                        handler: () => this.toggleUser(row),
                    },
                    { key: 'reset-password', label: 'Reset', class: 'bg-slate-100 text-slate-700', handler: () => this.openUserPasswordReset(row) },
                    { key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) },
                ];
            }

            if (this.page === 'settings-telegram') {
                return [
                    {
                        key: 'telegram-test',
                        label: 'Test',
                        class: 'bg-emerald-50 text-emerald-800',
                        handler: () => this.activeTab === 'groups'
                            ? this.openTelegramGroupTest(row)
                            : this.openTelegramBotTest(row),
                    },
                    ...(this.currentConfig().deletable
                        ? [{ key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) }]
                        : []),
                ];
            }

            if (this.page === 'settings-database') {
                return [
                    { key: 'database-test', label: 'Test', class: 'bg-emerald-50 text-emerald-800', handler: () => this.testDatabaseSettings(row) },
                ];
            }

            if (this.page === 'cashflow') {
                return this.canDeleteRow(row)
                    ? [{ key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) }]
                    : [];
            }

            const config = this.currentConfig();
            if (config.deletable && !config.noDelete && this.canDeleteRow(row)) {
                return [{ key: 'delete', label: 'Delete', class: 'bg-red-50 text-red-700', handler: () => this.confirmDelete(row) }];
            }

            return [];
        },

        runRowAction(action) {
            action.handler();
        },

        async testRouterConnection(row) {
            try {
                const response = await api.post(`/api/v1/admin/routers/${row.id}/test-connection`, {});
                this.toast(response.success ? 'success' : 'error', response.message, response.data?.host ?? '');
            } catch (error) {
                this.toast('error', 'Test connection gagal', error.message);
            }
        },

        async selectAcsRouter(routerId) {
            const selectedId = Number(routerId);
            if (!selectedId) return;

            this.acsConfig.router_id = selectedId;
            await this.loadAcsRouter(selectedId);
        },

        async loadAcsRouter(routerId) {
            this.acsConfig.loading = true;

            try {
                const response = await api.get(`/api/v1/admin/routers/${routerId}`);
                const router = response.data ?? {};

                this.acsConfig = {
                    ...this.acsConfig,
                    router_id: router.id ?? routerId,
                    router_name: router.name ?? router.router_name ?? `Router ${routerId}`,
                    host: router.host ?? router.mgmt_ip ?? '-',
                    status: router.status ?? (router.is_active ? 'active' : 'inactive'),
                    acs_inform_url: router.acs_inform_url ?? '',
                    acs_nbi_url: router.acs_nbi_url ?? '',
                    acs_username: router.acs_username ?? '',
                    acs_password: '',
                    has_acs_password: Boolean(router.has_acs_password),
                    result: this.acsConfig.result,
                };
            } catch (error) {
                this.toast('error', 'Gagal memuat Config ACS', error.message);
            } finally {
                this.acsConfig.loading = false;
            }
        },

        async saveAcsConfig() {
            if (!this.acsConfig.router_id) return;

            this.acsConfig.saving = true;

            try {
                const payload = {
                    acs_inform_url: this.acsConfig.acs_inform_url || null,
                    acs_nbi_url: this.acsConfig.acs_nbi_url || null,
                    acs_username: this.acsConfig.acs_username || null,
                };

                if (this.acsConfig.acs_password !== '') {
                    payload.acs_password = this.acsConfig.acs_password;
                }

                const response = await api.patch(`/api/v1/admin/routers/${this.acsConfig.router_id}`, payload);
                this.acsConfig.acs_password = '';
                this.acsConfig.has_acs_password = Boolean(response.data?.has_acs_password);
                this.setAcsOperationResult({
                    success: true,
                    message: response.message ?? 'Config ACS tersimpan.',
                    data: response.data ?? {},
                });
                await this.loadPage();
            } catch (error) {
                this.setAcsOperationResult({
                    success: false,
                    message: error.message || 'Simpan Config ACS gagal.',
                    data: {},
                });
            } finally {
                this.acsConfig.saving = false;
            }
        },

        async testAcsRouter(routerId = null) {
            const targetId = Number(routerId ?? this.acsConfig.router_id);
            if (!targetId) return;

            try {
                const response = await api.post(`/api/v1/admin/routers/${targetId}/test-acs`, {});
                this.setAcsOperationResult(response);
            } catch (error) {
                this.setAcsOperationResult({
                    success: false,
                    message: error.message || 'Test ACS gagal.',
                    data: {},
                });
            }
        },

        async syncOntRouter(routerId = null) {
            const targetId = Number(routerId ?? this.acsConfig.router_id);
            if (!targetId) return;

            try {
                const response = await api.post(`/api/v1/admin/routers/${targetId}/sync-ont`, {});
                this.setAcsOperationResult(response);
            } catch (error) {
                this.setAcsOperationResult({
                    success: false,
                    message: error.message || 'Sync ONT gagal.',
                    data: {},
                });
            }
        },

        setAcsOperationResult(result) {
            this.acsConfig.result = {
                success: Boolean(result?.success),
                message: result?.message ?? 'Operasi selesai.',
                data: result?.data ?? {},
                at: new Date().toISOString(),
            };
        },

        async searchManualUsers(mode) {
            const routerId = Number(this.manualIsolir.router_id);
            const operation = this.manualIsolir?.[mode];

            if (!routerId || !operation) {
                this.setManualOperationResult({
                    success: false,
                    message: 'Pilih router terlebih dahulu sebelum mencari user.',
                    data: {},
                });
                return;
            }

            const search = String(operation.search ?? '').trim();
            if (search === '') {
                operation.options = [];
                operation.user_id = '';
                return;
            }

            operation.loading = true;

            try {
                const response = await api.get('/api/v1/admin/service-isolations/suggestions', {
                    router_id: routerId,
                    search,
                    limit: 30,
                });

                const suggestions = Array.isArray(response.data) ? response.data : [];
                const filteredSuggestions = mode === 'release'
                    ? suggestions.filter((item) => item.open_isolation?.id)
                    : suggestions;

                operation.options = filteredSuggestions.map((item) => ({
                    ...item,
                    label: this.manualSuggestionLabel(item),
                }));
                operation.user_id = operation.options[0]?.service_id ?? '';

                if (operation.options.length === 0) {
                    this.setManualOperationResult({
                        success: false,
                        message: mode === 'release'
                            ? 'User dengan isolir aktif tidak ditemukan untuk router ini.'
                            : 'User tidak ditemukan untuk router ini.',
                        data: {
                            router_id: routerId,
                            search,
                        },
                    });
                }
            } catch (error) {
                this.setManualOperationResult({
                    success: false,
                    message: error.message || 'Pencarian user gagal.',
                    data: {
                        errors: error.errors ?? {},
                    },
                });
            } finally {
                operation.loading = false;
            }
        },

        async runManualIsolir() {
            if (!this.canRunManualOperation('isolir')) {
                return;
            }

            const payload = {
                router_id: Number(this.manualIsolir.router_id),
                user_id: this.manualIsolir.isolir.user_id,
            };

            this.manualIsolir.isolir.loading = true;

            try {
                const response = await api.post('/api/v1/admin/isolir/manual', payload);
                this.setManualOperationResult(response);
                this.toast('success', 'Isolir dikirim', response.message);
                await this.loadPage();
                await this.searchManualUsers('release');
            } catch (error) {
                this.setManualOperationResult({
                    success: false,
                    message: error.message || 'Manual isolir gagal diproses.',
                    data: {
                        payload,
                        errors: error.errors ?? {},
                    },
                });
                this.toast('error', 'Isolir gagal', error.message);
            } finally {
                this.manualIsolir.isolir.loading = false;
            }
        },

        async runManualRelease() {
            if (!this.canRunManualOperation('release')) {
                return;
            }

            const payload = {
                router_id: Number(this.manualIsolir.router_id),
                user_id: this.manualIsolir.release.user_id,
            };

            this.manualIsolir.release.loading = true;

            try {
                const response = await api.post('/api/v1/admin/isolir/release', payload);
                this.setManualOperationResult(response);
                this.toast('success', 'Release dikirim', response.message);
                await this.loadPage();
                await this.searchManualUsers('release');
            } catch (error) {
                this.setManualOperationResult({
                    success: false,
                    message: error.message || 'Manual release gagal diproses.',
                    data: {
                        payload,
                        errors: error.errors ?? {},
                    },
                });
                this.toast('error', 'Release gagal', error.message);
            } finally {
                this.manualIsolir.release.loading = false;
            }
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

        openUserPasswordReset(row) {
            this.modal = {
                open: true,
                mode: 'reset-user-password',
                title: `Reset password ${row.username}`,
                fields: [
                    { name: 'password', label: 'Password baru', type: 'password' },
                    { name: 'password_confirmation', label: 'Konfirmasi password', type: 'password' },
                ],
                form: { password: '', password_confirmation: '' },
                errors: {},
                message: '',
                endpoint: `/api/v1/admin/users/${row.id}/reset-password`,
                method: 'PATCH',
            };
        },

        openTelegramBotTest(row) {
            this.modal = {
                open: true,
                mode: 'telegram-bot-test',
                title: `Test ${row.bot_name}`,
                fields: [
                    { name: 'chat_id', label: 'Chat ID' },
                    { name: 'message', label: 'Pesan test', type: 'textarea' },
                ],
                form: {
                    chat_id: '',
                    message: 'Feralix Billing Telegram test message.',
                },
                errors: {},
                message: '',
                endpoint: `/api/v1/admin/telegram-bots/${row.id}/test`,
                method: 'POST',
            };
        },

        openTelegramGroupTest(row) {
            this.modal = {
                open: true,
                mode: 'telegram-group-test',
                title: `Test ${row.group_name}`,
                fields: [
                    { name: 'message', label: 'Pesan test', type: 'textarea' },
                ],
                form: {
                    message: 'Feralix Billing Telegram group test message.',
                },
                errors: {},
                message: '',
                endpoint: `/api/v1/admin/telegram-groups/${row.id}/test`,
                method: 'POST',
            };
        },

        async testDatabaseSettings(row) {
            try {
                const response = await api.post('/api/v1/admin/database-settings/test', {
                    host: row.host,
                    port: row.port,
                    database: row.database,
                    username: row.username,
                });

                this.toast(
                    response.data?.connected ? 'success' : 'error',
                    response.message,
                    response.data?.message ?? 'Database connection test finished.',
                );
            } catch (error) {
                this.toast('error', 'Test koneksi gagal', error.message);
            }
        },

        toggleUser(row) {
            const disabled = Boolean(row.is_active);
            const action = disabled ? 'disable' : 'enable';
            const title = disabled ? 'Disable user?' : 'Enable user?';
            const message = `${row.username} akan ${disabled ? 'dinonaktifkan dan token aktif dicabut' : 'diaktifkan kembali'}.`;

            this.askConfirm(title, message, async () => {
                const response = await api.patch(`/api/v1/admin/users/${row.id}/${action}`, {});
                this.toast('success', 'Status user diperbarui', response.message);
                await this.loadPage();
            });
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
            if (ref === 'routers') return `${row.router_code ?? row.id} - ${row.router_name ?? row.name ?? row.host ?? '-'}`;
            if (ref === 'olts') return `${row.olt_code ?? row.id} - ${row.olt_name}`;
            if (ref === 'onts') return `${row.ont_sn} - ${row.ont_name ?? row.status ?? ''}`;
            if (ref === 'packages') return `${row.package_name} - ${this.money(row.monthly_price)}`;
            if (ref === 'locations') return `${row.location_code ?? row.id} - ${row.location_name}`;
            if (ref === 'technicians') return `${row.technician_code ?? row.id} - ${row.full_name}`;
            if (ref === 'available_vids') return `${row.router?.router_name ?? row.router_id} / VID ${row.vid} / ${row.subnet_cidr ?? 'no subnet'}`;
            if (ref === 'hotspot_profiles') return `${row.profile_name} - ${this.money(row.selling_price)}`;
            if (ref === 'resellers') return `${row.reseller_code ?? row.id} - ${row.full_name}`;
            if (ref === 'telegram_bots') return `${row.bot_name} - ${human(row.status)}`;
            if (ref === 'cashflow_categories') return `${row.name} (${human(row.type)})`;
            return row.name ?? row.label ?? row.id;
        },

        defaultsFor(fields = []) {
            return Object.fromEntries(fields.map((field) => {
                if (field.name === 'isolation_type') return [field.name, 'manual'];
                if (field.name === 'issue_now') return [field.name, true];
                if (field.name === 'is_active') return [field.name, true];
                if (field.name === 'use_ssl') return [field.name, false];
                if (field.name === 'timeout') return [field.name, 5];
                if (field.name === 'role') return [field.name, 'admin'];
                if (field.type === 'multiselect') return [field.name, []];
                if (field.name === 'group_type') return [field.name, 'admin'];
                if (field.name === 'port') return [field.name, 3306];
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
                if (field.name === 'type' && this.isCashflowPage()) return [field.name, 'income'];
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

                if (field.type === 'multiselect') {
                    payload[key] = Array.isArray(value)
                        ? value.filter((item) => item !== '').map((item) => Number(item))
                        : [];
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
            if (column.type === 'datetime') {
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) return String(value);
                return parsed.toLocaleString('id-ID');
            }
            if (typeof value === 'boolean') {
                if (['has_api_password', 'has_acs_password'].includes(column.key)) {
                    return value ? 'Password tersimpan' : 'Password belum ada';
                }

                return value ? 'Aktif' : 'Tidak';
            }
            return human(value);
        },

        statusClass(value) {
            const status = String(value ?? '').toLowerCase();
            if (statusGroups.green.includes(status) || value === true) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200';
            if (statusGroups.red.includes(status) || value === false) return 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-200';
            if (statusGroups.yellow.includes(status)) return 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200';
            return 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200';
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
                ?? row.name
                ?? row.router_name
                ?? row.batch_code
                ?? row.username
                ?? row.bot_name
                ?? row.group_name
                ?? row.database
                ?? row.description
                ?? `#${row.id}`;
        },

        cashflowSummaryCards() {
            const summary = this.cashflow.summary ?? {};

            return [
                {
                    key: 'income',
                    label: 'Total Income',
                    value: this.moneyValue(summary.total_income ?? 0),
                    class: 'text-emerald-700 dark:text-emerald-200',
                },
                {
                    key: 'expense',
                    label: 'Total Expense',
                    value: this.moneyValue(summary.total_expense ?? 0),
                    class: 'text-rose-700 dark:text-rose-200',
                },
                {
                    key: 'net',
                    label: 'Net Balance',
                    value: this.moneyValue(summary.net_balance ?? 0),
                    class: this.numeric(summary.net_balance ?? 0) >= 0
                        ? 'text-blue-700 dark:text-blue-200'
                        : 'text-amber-700 dark:text-amber-200',
                },
            ];
        },

        cashflowMonthlyPoints() {
            return this.cashflow.summary?.monthly ?? [];
        },

        cashflowMonthlyMax() {
            return Math.max(1, ...this.cashflowMonthlyPoints().map((point) => (
                Math.max(this.numeric(point.income), this.numeric(point.expense))
            )));
        },

        dashboardCards() {
            return this.dashboardKpiCards();
        },

        dashboardKpiCards() {
            const kpis = this.dashboard?.kpis ?? {};
            const analytics = this.dashboard?.analytics ?? {};
            const invoice = analytics.revenue_by_invoice ?? {};

            return [
                {
                    key: 'revenue_today',
                    label: this.ui('revenueToday'),
                    value: this.moneyValue(analytics.revenue?.current_amount ?? kpis.monthly_income?.value ?? 0),
                    meta: analytics.revenue?.growth_percentage !== undefined
                        ? `${analytics.revenue.growth_percentage ?? 0}% vs prev`
                        : this.ui('currentPeriod'),
                    tone: 'blue',
                },
                {
                    key: 'billing_today',
                    label: this.ui('billingToday'),
                    value: invoice.invoice_count ?? kpis.invoice_unpaid?.value ?? 0,
                    meta: `${invoice.open_invoice_count ?? kpis.invoice_unpaid?.value ?? 0} ${this.ui('openInvoices').toLowerCase()}`,
                    tone: 'amber',
                },
                {
                    key: 'active_users',
                    label: this.ui('activeUsers'),
                    value: kpis.customer_active?.value ?? kpis.total_customers?.value ?? 0,
                    meta: `${kpis.ppp_active?.value ?? 0} PPPoE online`,
                    tone: 'emerald',
                },
                {
                    key: 'tickets',
                    label: this.ui('tickets'),
                    value: this.dashboard?.summaries?.tickets?.open ?? kpis.monthly_tickets?.value ?? 0,
                    meta: `${this.dashboard?.summaries?.tickets?.total ?? 0} ${this.ui('currentPeriod').toLowerCase()}`,
                    tone: 'rose',
                },
            ];
        },

        revenuePoints() {
            return this.dashboard?.charts?.monthly_revenue?.points ?? [];
        },

        pppPoints() {
            return this.dashboard?.charts?.ppp_active_trend?.points ?? [];
        },

        revenueMax() {
            return Math.max(1, ...this.revenuePoints().map((point) => this.numeric(point.revenue)));
        },

        pppMax() {
            return Math.max(1, ...this.pppPoints().map((point) => Number(point.active ?? 0)));
        },

        barHeight(value, max) {
            return Math.max(8, Math.round((this.numeric(value) / Math.max(1, max)) * 100));
        },

        dashboardInvoiceRows() {
            return (this.references.invoices ?? []).slice(0, 5).map((invoice) => ({
                id: invoice.id,
                invoice: invoice.invoice_number ?? `INV-${invoice.id}`,
                customer: invoice.customer?.full_name ?? invoice.customer_name ?? '-',
                plan: invoice.service?.package?.package_name ?? invoice.service?.service_code ?? invoice.billing_period ?? '-',
                amount: this.moneyValue(invoice.remaining_amount ?? invoice.total_amount ?? 0),
                status: invoice.payment_status ?? invoice.status ?? 'pending',
            }));
        },

        invoiceStatusLabel(status) {
            const normalized = String(status ?? '').toLowerCase();
            if (this.language === 'id' && normalized === 'paid') return this.ui('paid');
            if (this.language === 'id' && normalized === 'overdue') return this.ui('overdue');
            if (this.language === 'id' && ['pending', 'unpaid', 'issued', 'partially_paid'].includes(normalized)) return this.ui('pending');
            if (normalized === 'paid') return this.ui('paid');
            if (normalized === 'overdue') return this.ui('overdue');
            if (['pending', 'unpaid', 'issued', 'partially_paid'].includes(normalized)) return this.ui('pending');
            return human(status);
        },

        networkSummaryCards() {
            const ppp = this.dashboard?.summaries?.ppp ?? {};
            const active = ppp.active ?? this.dashboard?.kpis?.ppp_active?.value ?? 0;
            const total = ppp.total_monitored ?? this.dashboard?.kpis?.ppp_active?.meta?.total_monitored ?? active;
            const inactive = ppp.inactive ?? Math.max(0, Number(total) - Number(active));

            return [
                {
                    key: 'pppoe',
                    label: 'PPPoE',
                    value: `${active} online / ${inactive} offline`,
                    meta: `${total} ${this.ui('monitored').toLowerCase()}`,
                    percent: this.percentWidth(ppp.active_ratio),
                    class: 'bg-emerald-500',
                },
                {
                    key: 'hotspot',
                    label: 'Hotspot',
                    value: `${this.references.hotspot_profiles.length} profiles`,
                    meta: `${this.references.resellers.length} resellers`,
                    percent: Math.min(100, this.references.hotspot_profiles.length * 18),
                    class: 'bg-blue-500',
                },
                {
                    key: 'nas',
                    label: 'NAS health',
                    value: this.routerSwitcher.enabled ? 'Scoped' : 'All routers',
                    meta: 'Router sync ready',
                    percent: 86,
                    class: 'bg-cyan-500',
                },
            ];
        },

        helpdeskCards() {
            const tickets = this.dashboard?.summaries?.tickets ?? {};
            const installs = this.dashboard?.summaries?.installations ?? {};

            return [
                { key: 'open', label: this.ui('openTickets'), value: tickets.open ?? 0, tone: 'rose' },
                { key: 'closed', label: this.language === 'id' ? 'Tiket selesai' : 'Closed tickets', value: tickets.closed ?? 0, tone: 'emerald' },
                { key: 'wo', label: this.language === 'id' ? 'WO pending' : 'Pending WO', value: installs.pending ?? 0, tone: 'amber' },
            ];
        },

        technicianDashboardSummary() {
            return this.technicianDashboard.data?.summary ?? {
                total_installations: 0,
                completed_installations: 0,
                open_tickets: 0,
                closed_tickets: 0,
                total_points: 0,
            };
        },

        technicianDashboardTargets() {
            return this.technicianDashboard.data?.targets ?? {
                installation_target: 20,
                ticket_target: 50,
            };
        },

        technicianDashboardRanking() {
            return this.technicianDashboard.data?.ranking ?? [];
        },

        technicianDashboardWorkOrders() {
            return this.technicianDashboard.data?.work_orders ?? [];
        },

        technicianDashboardTickets() {
            return this.technicianDashboard.data?.tickets ?? [];
        },

        technicianDashboardPointRules() {
            return this.technicianDashboard.data?.point_rules ?? [];
        },

        technicianDashboardTargetProgress(type) {
            const summary = this.technicianDashboardSummary();
            const targets = this.technicianDashboardTargets();

            if (type === 'installation') {
                const value = Number(summary.completed_installations ?? 0);
                const target = Math.max(0, Number(targets.installation_target ?? 0));

                return {
                    value,
                    target,
                    percent: target > 0 ? Math.min(100, Math.round((value / target) * 100)) : 0,
                };
            }

            const value = Number(summary.closed_tickets ?? 0);
            const target = Math.max(0, Number(targets.ticket_target ?? 0));

            return {
                value,
                target,
                percent: target > 0 ? Math.min(100, Math.round((value / target) * 100)) : 0,
            };
        },

        activityRows() {
            const rows = [];

            (this.references.invoices ?? []).slice(0, 2).forEach((invoice) => {
                rows.push({
                    type: 'Billing',
                    title: `${invoice.invoice_number ?? 'Invoice'} ${this.invoiceStatusLabel(invoice.payment_status)}`,
                    meta: invoice.customer?.full_name ?? invoice.billing_period ?? this.ui('currentPeriod'),
                });
            });

            (this.references.work_orders ?? []).slice(0, 2).forEach((workOrder) => {
                rows.push({
                    type: 'Work order',
                    title: `${workOrder.wo_number ?? 'WO'} ${human(workOrder.status ?? 'open')}`,
                    meta: workOrder.customer?.full_name ?? workOrder.router?.router_name ?? 'Field ops',
                });
            });

            (this.references.tickets ?? []).slice(0, 2).forEach((ticket) => {
                rows.push({
                    type: 'Helpdesk',
                    title: `${ticket.ticket_number ?? 'Ticket'} ${human(ticket.status ?? 'open')}`,
                    meta: ticket.customer?.full_name ?? ticket.category ?? 'Support queue',
                });
            });

            return rows.slice(0, 5);
        },

        kpiToneClass(tone) {
            return {
                blue: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-400/20',
                emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/20',
                amber: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20',
                rose: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/20',
            }[tone] ?? 'bg-slate-50 text-slate-700 ring-slate-100 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700';
        },

        percentWidth(value) {
            if (value === null || value === undefined || value === '') return 0;
            return Math.max(0, Math.min(100, this.numeric(value)));
        },

        numeric(value) {
            if (typeof value === 'number') return value;
            const cleaned = String(value ?? '0').replace(/[^0-9.-]/g, '');
            return Number(cleaned || 0);
        },

        moneyValue(value) {
            return this.money(this.numeric(value));
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
