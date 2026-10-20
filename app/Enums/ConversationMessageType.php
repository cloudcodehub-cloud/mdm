<?php

namespace App\Enums;

enum ConversationMessageType: string
{
    case User = 'user';
    case System = 'system';
}
