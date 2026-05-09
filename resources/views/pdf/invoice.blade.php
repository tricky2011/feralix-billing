<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; padding: 30px; }

        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company-name { font-size: 22px; font-weight: bold; color: #2563eb; }
        .company-info { font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.6; }
        .invoice-title { font-size: 28px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; margin: 20px 0 10px; }
        .invoice-meta { font-size: 12px; line-height: 1.8; }
        .invoice-meta strong { display: inline-block; width: 100px; }

        .stamp { display: inline-block; border: 2px solid #dc2626; border-radius: 4px; padding: 3px 10px;
                 color: #dc2626; font-weight: bold; font-size: 11px; text-transform: uppercase; transform: rotate(-5deg); margin-top: 8px; }
        .stamp.paid { border-color: #16a34a; color: #16a34a; }

        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }

        .section { margin: 20px 0; }
        .section-title { font-size: 11px; font-weight: bold; color: #2563eb; text-transform: uppercase;
                         letter-spacing: 1px; margin-bottom: 8px; }
        .customer-name { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
        .customer-info { font-size: 12px; color: #475569; line-height: 1.6; }
        .meta-grid { display: flex; gap: 20px; margin-top: 8px; }
        .meta-item { font-size: 12px; }
        .meta-item strong { color: #1e293b; }

        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #f8fafc; text-align: left; padding: 8px 12px; font-size: 11px;
             text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        th:last-child { text-align: right; }
        td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; vertical-align: top; }
        td:last-child { text-align: right; font-weight: bold; }
        .item-title { font-weight: bold; font-size: 13px; }
        .item-sub { color: #64748b; font-size: 11px; margin-top: 2px; }

        .total-section { text-align: right; margin: 15px 0; }
        .total-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
        .total-amount { font-size: 26px; font-weight: bold; color: #1e293b; }

        .status-box { margin-top: 20px; padding: 14px 16px; border-radius: 6px; }
        .status-box.unpaid { background: #fef2f2; border: 1px solid #fecaca; }
        .status-box.paid { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .status-title { font-weight: bold; font-size: 13px; margin-bottom: 6px; }
        .status-title.unpaid { color: #dc2626; }
        .status-title.paid { color: #16a34a; }
        .status-row { font-size: 12px; line-height: 1.8; }
        .status-row strong { color: #1e293b; }
        .status-note { font-size: 11px; color: #64748b; margin-top: 6px; }

        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <div class="company-name">{{ $company['name'] ?? config('app.name') }}</div>
        <div class="company-info">
            @if($company['address'] ?? null) {{ $company['address'] }}<br> @endif
            @if($company['phone'] ?? null) {{ $company['phone'] }}<br> @endif
            @if($company['email'] ?? null) {{ $company['email'] }} @endif
        </div>
    </div>
    <div style="text-align: right;">
        <div style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">ISP Billing System</div>
    </div>
</div>

<div class="invoice-title">Invoice</div>
<div class="invoice-meta">
    <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
    <strong>Tanggal:</strong> {{ $invoice->invoice_date?->locale('id')->isoFormat('DD MMM YYYY') }}<br>
    <strong>Jatuh Tempo:</strong> {{ $invoice->due_date?->locale('id')->isoFormat('DD MMM YYYY') }}
</div>
<div>
    @if($invoice->payment_status?->value === 'paid')
        <span class="stamp paid">Lunas</span>
    @else
        <span class="stamp">Belum Lunas</span>
    @endif
</div>

<hr class="divider">

<div class="section">
    <div class="section-title">Tagihan Untuk</div>
    <div class="customer-name">{{ $invoice->customer?->full_name }}</div>
    <div class="customer-info">
        @if($invoice->customer?->phone) {{ $invoice->customer->phone }}<br> @endif
        @if($invoice->customer?->address) {{ $invoice->customer->address }} @endif
    </div>
    <div class="meta-grid" style="margin-top: 10px;">
        @if($invoice->service?->internet_vid)
        <div class="meta-item"><strong>VID:</strong> V-{{ $invoice->service->internet_vid }}</div>
        @endif
        @if($invoice->service?->pppoe_username)
        <div class="meta-item"><strong>Username PPPoE:</strong> {{ $invoice->service->pppoe_username }}</div>
        @endif
        <div class="meta-item"><strong>Periode:</strong> {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $invoice->billing_period)->locale('id')->isoFormat('MMMM YYYY') }}</div>
    </div>
</div>

<hr class="divider">

<div class="section">
    <div class="section-title">Detail Tagihan</div>
    <table>
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th>Periode</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="item-title">Layanan Internet</div>
                    <div class="item-sub">{{ $invoice->service?->service_code }}</div>
                    @php
                        $periodStart = \Illuminate\Support\Carbon::createFromFormat('Y-m', $invoice->billing_period)->startOfMonth();
                        $periodEnd = $periodStart->copy()->endOfMonth();
                    @endphp
                    <div class="item-sub">{{ $periodStart->format('d-m-Y') }} s/d {{ $periodEnd->format('d-m-Y') }}</div>
                </td>
                <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $invoice->billing_period)->locale('id')->isoFormat('MMMM YYYY') }}</td>
                <td>Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if((float) $invoice->penalty_amount > 0)
            <tr>
                <td><div class="item-title">Denda Keterlambatan</div></td>
                <td>-</td>
                <td>Rp {{ number_format((float) $invoice->penalty_amount, 0, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<div class="total-section">
    <div class="total-label">Total Tagihan</div>
    <div class="total-amount">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</div>
</div>

@php
    $isPaid = in_array($invoice->payment_status?->value, ['paid', 'canceled']);
    $amountPaid = $invoice->payments_sum_amount_paid ?? 0;
    $remaining = max(0, (float) $invoice->total_amount - (float) $amountPaid);
@endphp

<div class="status-box {{ $isPaid ? 'paid' : 'unpaid' }}">
    @if($isPaid)
        <div class="status-title paid">✓ Invoice Telah Dibayar</div>
        <div class="status-row">Pembayaran: <strong>Rp {{ number_format((float) $amountPaid, 0, ',', '.') }}</strong></div>
    @else
        <div class="status-title unpaid">⚠ Invoice Belum Dibayar</div>
        <div class="status-row">Sisa Tagihan: <strong>Rp {{ number_format($remaining, 0, ',', '.') }}</strong></div>
        <div class="status-row">Jatuh Tempo: <strong>{{ $invoice->due_date?->locale('id')->isoFormat('DD MMMM YYYY') }}</strong></div>
        <div class="status-note">Status overdue lebih dari 5 hari akan masuk isolir otomatis.</div>
        <div class="status-note">Silakan lakukan pembayaran sebelum tanggal jatuh tempo.</div>
    @endif
</div>

<div class="footer">
    Dokumen ini digenerate secara otomatis oleh sistem {{ config('app.name') }} &bull; {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
</div>

</body>
</html>