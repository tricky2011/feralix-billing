<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotLoginController extends Controller
{
    public function __invoke(Request $request): View
    {
        $errorMessages = [
            'invalid-login'          => 'Username atau password salah.',
            'uptime-limit-reached'   => 'Batas waktu penggunaan voucher telah habis.',
            'traffic-limit-reached'  => 'Kuota data voucher telah habis.',
            'user-limit-reached'     => 'Batas jumlah pengguna tercapai.',
            'trial-uptime-limit'     => 'Batas waktu percobaan habis.',
            'trial-traffic-limit'    => 'Batas data percobaan habis.',
            'no-login'               => '',
        ];

        $errorCode = $request->query('error', '');
        $errorMessage = $errorMessages[$errorCode] ?? ($errorCode !== '' ? 'Login gagal. Silakan coba lagi.' : '');

        return view('hotspot.login', [
            'linkLogin'    => $request->query('link-login', ''),
            'linkOrig'     => $request->query('link-orig', $request->query('dst', '')),
            'linkLogout'   => $request->query('link-logout', ''),
            'mac'          => $request->query('mac', ''),
            'ip'           => $request->query('ip', ''),
            'identity'     => $request->query('identity', ''),
            'username'     => $request->query('username', ''),
            'errorCode'    => $errorCode,
            'errorMessage' => $errorMessage,
        ]);
    }
}
