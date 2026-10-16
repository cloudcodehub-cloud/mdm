<?php

namespace App\Enums;

enum IncidentPartyKind: string
{
    case PrimaryClient = 'primary_client';
    case Client = 'client';
    case Employee = 'employee';
    case Outside = 'outside';

    public function label(): string
    {
        return match ($this) {
            self::PrimaryClient => 'Primary client',
            self::Client => 'Client',
            self::Employee => 'Employee',
            self::Outside => 'Outside person',
        };
    }
}
