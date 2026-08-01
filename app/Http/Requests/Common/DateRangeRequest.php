<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class DateRangeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ];
    }
}
