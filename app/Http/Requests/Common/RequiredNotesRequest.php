<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class RequiredNotesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
