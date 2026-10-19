<?php

namespace App\Enums;

enum ClientAdminHistoryCategory: string
{
    case CarePlan = 'care_plan';
    case Lifecycle = 'lifecycle';
    case Service = 'service';
    case Authorization = 'authorization';
    case CriticalAlert = 'critical_alert';
    case Instruction = 'instruction';
    case EffectiveDateCorrection = 'effective_date_correction';
    case Contact = 'contact';
    case ServiceLocation = 'service_location';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::CarePlan => 'Care Plan',
            self::Lifecycle => 'Lifecycle',
            self::Service => 'Service',
            self::Authorization => 'Authorization',
            self::CriticalAlert => 'Critical safety/supervision',
            self::Instruction => 'Significant instruction',
            self::EffectiveDateCorrection => 'Effective-date correction',
            self::Contact => 'Contact',
            self::ServiceLocation => 'Service location',
            self::Note => 'Historical note correction',
        };
    }
}
