<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class ExportFiltersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'filters' => ['nullable', 'array'],
        ];
    }
}
