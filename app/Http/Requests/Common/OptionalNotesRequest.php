<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\ApiFormRequest;

class OptionalNotesRequest extends ApiFormRequest
{
    protected ?int $maxLength = null;

    public function rules(): array
    {
        $rules = ['nullable', 'string'];

        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return ['notes' => $rules];
    }
}
