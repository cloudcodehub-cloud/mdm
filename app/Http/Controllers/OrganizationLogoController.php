<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationLogoController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings): StreamedResponse
    {
        $variant = $request->query('variant', 'primary');
        $variant = $variant === 'horizontal' ? 'horizontal' : 'primary';

        return $settings->streamLogo($variant);
    }
}
