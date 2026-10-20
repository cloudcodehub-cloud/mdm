<?php

namespace App\Enums;

enum SupportTicketWorkImpact: string
{
    case CanWorkNormally = 'can_work_normally';
    case CanWorkWithProblems = 'can_work_with_problems';
    case CannotPerformPart = 'cannot_perform_part';
    case CannotWork = 'cannot_work';

    public function label(): string
    {
        return match ($this) {
            self::CanWorkNormally => 'I can still work normally',
            self::CanWorkWithProblems => 'I can work, but this is causing problems',
            self::CannotPerformPart => 'I cannot perform part of my work',
            self::CannotWork => 'I cannot work because of this issue',
        };
    }

    public function urgency(): SupportTicketUrgency
    {
        return match ($this) {
            self::CanWorkNormally => SupportTicketUrgency::Low,
            self::CanWorkWithProblems => SupportTicketUrgency::Medium,
            self::CannotPerformPart => SupportTicketUrgency::High,
            self::CannotWork => SupportTicketUrgency::Critical,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
