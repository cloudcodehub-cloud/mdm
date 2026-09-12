<?php

namespace App\Enums;

enum CredentialType: string
{
    case Cpr = 'cpr';
    case FirstAid = 'first_aid';
    case DriversLicense = 'drivers_license';
    case BackgroundCheck = 'background_check';
    case MedicationAdministration = 'medication_administration';
    case TbScreening = 'tb_screening';
    case PhysicianStatement = 'physician_statement';
    case ProfessionalLicense = 'professional_license';

    public function label(): string
    {
        return match ($this) {
            self::Cpr => 'CPR',
            self::FirstAid => 'First Aid',
            self::DriversLicense => "Driver's License",
            self::BackgroundCheck => 'Background Check',
            self::MedicationAdministration => 'Medication Administration',
            self::TbScreening => 'TB Screening',
            self::PhysicianStatement => 'Physician Good-Health Statement',
            self::ProfessionalLicense => 'License / Certification',
        };
    }
}
