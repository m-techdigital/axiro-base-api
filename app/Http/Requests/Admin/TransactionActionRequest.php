<?php
namespace App\Http\Requests\Admin;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
class TransactionActionRequest extends ApiFormRequest { public function rules(): array { return ['action'=>['required',Rule::in(['force_handover','force_return','complete','cancel','reopen'])],'note'=>['nullable','string','max:2000']]; } }
