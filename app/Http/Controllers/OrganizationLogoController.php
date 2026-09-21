<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationLogoController extends Controller
{
    public function __invoke(SettingsService $settings): StreamedResponse
    {
        return $settings->streamLogo();
    }
}
