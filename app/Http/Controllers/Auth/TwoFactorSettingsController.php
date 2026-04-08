<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorSettingsController extends Controller
{
    /**
     * Gera um novo segredo 2FA e um QR Code para leitura no telemóvel.
     */
    public function enable(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();
        
        $user->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        // Prepara o URL compatível com a App do Authenticator
        $g2faUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Renderiza visualmente como um SVG limpo
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($g2faUrl);

        return back()
            ->with('status', 'two-factor-authentication-enabled')
            ->with('qr_code', $svg)
            ->with('secret', $secret);
    }

    /**
     * Remove o 2FA da conta do utilizador.
     */
    public function disable(Request $request)
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
        ])->save();

        return back()->with('status', 'two-factor-authentication-disabled');
    }
}