<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Anda Dinonaktifkan — {{ $company['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }

        /* Header */
        .card-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            padding: 28px 28px 20px;
            position: relative;
            overflow: hidden;
        }
        .card-header::before {
            content: '';
            position: absolute;
            top: -30px; right: -30px;
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -20px; left: 60px;
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }

        .company-name {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .warning-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .header-title {
            font-size: 22px;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }

        .header-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.75);
            margin-top: 6px;
        }

        /* Body */
        .card-body { padding: 24px 28px; }

        /* Customer info */
        .customer-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .customer-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .customer-name {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .customer-detail {
            font-size: 12px;
            color: #64748b;
            line-height: 1.7;
        }

        /* Invoice box */
        .invoice-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .invoice-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ea580c;
            margin-bottom: 10px;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .invoice-row-label { color: #64748b; }
        .invoice-row-value { font-weight: 600; color: #1e293b; }

        .invoice-number { font-family: monospace; font-size: 12px; color: #7c3aed; }

        .divider-line { border: none; border-top: 1px dashed #e2e8f0; margin: 10px 0; }

        .invoice-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        .invoice-total-label { font-size: 13px; font-weight: 600; color: #dc2626; }
        .invoice-total-amount { font-size: 20px; font-weight: 800; color: #dc2626; }

        /* Status badge */
        .status-overdue {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 14px;
        }

        /* Info box */
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }

        .info-box-title {
            font-size: 12px;
            font-weight: 700;
            color: #0369a1;
            margin-bottom: 8px;
        }

        .info-box-text {
            font-size: 12px;
            color: #0c4a6e;
            line-height: 1.7;
        }

        /* Not found */
        .not-found {
            text-align: center;
            padding: 20px 0;
        }

        .not-found-icon { font-size: 40px; margin-bottom: 12px; }
        .not-found-title { font-size: 16px; font-weight: 700; color: #374151; margin-bottom: 8px; }
        .not-found-text { font-size: 13px; color: #6b7280; line-height: 1.6; }

        /* Footer */
        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 28px;
            text-align: center;
        }

        .contact-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .contact-items {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .contact-item {
            font-size: 12px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .powered {
            font-size: 10px;
            color: #cbd5e1;
            margin-top: 12px;
        }

        @media (max-width: 480px) {
            .card { border-radius: 16px; }
            .card-header { padding: 22px 20px 18px; }
            .card-body { padding: 20px; }
            .card-footer { padding: 14px 20px; }
            .invoice-total-amount { font-size: 18px; }
        }
    </style>
</head>
<body>

<div class="card">

    {{-- Header --}}
    <div class="card-header">
        <div class="company-name">{{ $company['name'] }}</div>
        <div class="warning-icon">🚫</div>
        <div class="header-title">Layanan Internet<br>Anda Dinonaktifkan</div>
        <div class="header-sub">Terdapat tagihan yang belum diselesaikan</div>
    </div>

    {{-- Body --}}
    <div class="card-body">

        @if($customer)

            {{-- Customer Info --}}
            <div class="customer-box">
                <div class="customer-label">Pelanggan</div>
                <div class="customer-name">{{ $customer->full_name }}</div>
                <div class="customer-detail">
                    @if($customer->phone) {{ $customer->phone }}<br> @endif
                    @if($service?->pppoe_username) PPPoE: {{ $service->pppoe_username }}<br> @endif
                    @if($service?->internet_vid) VID: V-{{ $service->internet_vid }} @endif
                </div>
            </div>

            @if($invoice)

                {{-- Status --}}
                <div class="status-overdue">
                    ⚠ Tagihan Overdue
                </div>

                {{-- Invoice --}}
                <div class="invoice-box">
                    <div class="invoice-label">Detail Tagihan</div>

                    <div class="invoice-row">
                        <span class="invoice-row-label">No. Invoice</span>
                        <span class="invoice-number">{{ $invoice->invoice_number }}</span>
                    </div>

                    <div class="invoice-row">
                        <span class="invoice-row-label">Periode</span>
                        <span class="invoice-row-value">
                            {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $invoice->billing_period)->locale('id')->isoFormat('MMMM YYYY') }}
                        </span>
                    </div>

                    <div class="invoice-row">
                        <span class="invoice-row-label">Jatuh Tempo</span>
                        <span class="invoice-row-value" style="color: #dc2626;">
                            {{ $invoice->due_date?->locale('id')->isoFormat('DD MMMM YYYY') }}
                        </span>
                    </div>

                    <hr class="divider-line">

                    <div class="invoice-total">
                        <span class="invoice-total-label">Total Tagihan</span>
                        <span class="invoice-total-amount">
                            Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Info cara bayar --}}
                <div class="info-box">
                    <div class="info-box-title">💡 Cara Mengaktifkan Kembali</div>
                    <div class="info-box-text">
                        Hubungi operator untuk melakukan pembayaran. Setelah konfirmasi pembayaran diterima,
                        layanan internet Anda akan aktif kembali secara otomatis dalam beberapa menit.
                    </div>
                </div>

            @else

                {{-- Ada customer tapi tidak ada invoice overdue --}}
                <div class="info-box">
                    <div class="info-box-title">ℹ️ Informasi</div>
                    <div class="info-box-text">
                        Layanan Anda sedang dalam proses pemeriksaan.
                        Silakan hubungi operator untuk informasi lebih lanjut.
                    </div>
                </div>

            @endif

        @else

            {{-- IP tidak ditemukan di sistem --}}
            <div class="not-found">
                <div class="not-found-icon">🔍</div>
                <div class="not-found-title">Data Tidak Ditemukan</div>
                <div class="not-found-text">
                    IP Anda (<code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;">{{ $ip }}</code>)
                    tidak terdaftar dalam sistem.<br><br>
                    Silakan hubungi operator untuk bantuan.
                </div>
            </div>

        @endif

    </div>

    {{-- Footer --}}
    <div class="card-footer">
        <div class="contact-title">Hubungi Kami</div>
        <div class="contact-items">
            @if($company['phone'] ?? null)
            <div class="contact-item">📞 {{ $company['phone'] }}</div>
            @endif
            @if($company['email'] ?? null)
            <div class="contact-item">✉️ {{ $company['email'] }}</div>
            @endif
            @if(!($company['phone'] ?? null) && !($company['email'] ?? null))
            <div class="contact-item" style="color:#94a3b8;">Hubungi operator Anda</div>
            @endif
        </div>
        <div class="powered">Powered by Feralix ISP Cloud &bull; {{ now()->timezone('Asia/Jakarta')->format('H:i') }} WIB</div>
    </div>

</div>

</body>
</html>