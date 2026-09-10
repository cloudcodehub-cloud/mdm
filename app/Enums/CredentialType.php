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
}
