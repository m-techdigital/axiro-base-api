<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DatabaseExpressions
{
    public static function greatest(string ...$expressions): string
    {
        $function = DB::connection()->getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST';

        return $function.'('.implode(', ', $expressions).')';
    }
}
