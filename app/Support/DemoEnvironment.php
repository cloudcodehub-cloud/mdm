<?php

namespace App\Support;

class DemoEnvironment
{
    public static function toolsEnabled(): bool
    {
        return in_array(app()->environment(), ['local', 'demo', 'testing'], true);
    }
}
