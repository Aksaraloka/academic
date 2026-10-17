<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SsoReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Masuk dengan akun lain" pada halaman penolakan SSO: keluar, lalu ke halaman masuk dengan tujuan otorisasi OAuth yang
 * sama tersimpan, sehingga login berikutnya langsung kembali ke aplikasi asal. Hanya URL otorisasi milik aplikasi ini
 * yang diterima.
 */
class SwitchSsoAccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $returnUrl = (string) $request->input('return');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (SsoReturn::isAuthorizeUrl($returnUrl)) {
            $request->session()->put('url.intended', $returnUrl);
        }

        return redirect()->route('login');
    }
}
