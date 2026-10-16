<?php

namespace App\Enums;

enum EmployeeProfileChangeField: string
{
    case Phone = 'phone';
    case CellPhone = 'cell_phone';
    case HomePhone = 'home_phone';
    case AlternatePhone = 'alternate_phone';
    case AddressLine1 = 'address_line_1';
    case AddressLine2 = 'address_line_2';
    case City = 'city';
    case State = 'state';
    case PostalCode = 'postal_code';
    case EmergencyContactName = 'emergency_contact_name';
    case EmergencyContactRelationship = 'emergency_contact_relationship';
    case EmergencyContactPhone = 'emergency_contact_phone';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::CellPhone => 'Cell phone',
            self::HomePhone => 'Home phone',
            self::AlternatePhone => 'Alternate phone',
            self::AddressLine1 => 'Address line 1',
            self::AddressLine2 => 'Address line 2',
            self::City => 'City',
            self::State => 'State',
            self::PostalCode => 'Postal code',
            self::EmergencyContactName => 'Emergency contact name',
            self::EmergencyContactRelationship => 'Emergency contact relationship',
            self::EmergencyContactPhone => 'Emergency contact phone',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $field): string => $field->value, self::cases());
    }
}
