<?php

namespace App\Enums;

enum CriticalAlertCategory: string
{
    case Allergy = 'allergy';
    case MedicalWarning = 'medical_warning';
    case FallRisk = 'fall_risk';
    case MobilityWarning = 'mobility_warning';
    case BehavioralRisk = 'behavioral_risk';
    case SpecialSupervision = 'special_supervision';
    case CommunicationRestriction = 'communication_restriction';
    case SupervisorDesignated = 'supervisor_designated';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Allergy',
            self::MedicalWarning => 'Critical medical/care warning',
            self::FallRisk => 'Fall risk',
            self::MobilityWarning => 'Mobility warning',
            self::BehavioralRisk => 'Behavioral/safety risk',
            self::SpecialSupervision => 'Special supervision requirement',
            self::CommunicationRestriction => 'Communication restriction/instruction',
            self::SupervisorDesignated => 'Supervisor-designated critical care alert',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
