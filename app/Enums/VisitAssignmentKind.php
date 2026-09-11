<?php

namespace App\Enums;

enum VisitAssignmentKind: string
{
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case Replacement = 'replacement';
}
