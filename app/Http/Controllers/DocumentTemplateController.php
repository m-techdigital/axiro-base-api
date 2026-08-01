<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\Documents\MarketplaceDocumentService;
use App\Services\Documents\MarketplaceDocumentTemplateValidator;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $q = DocumentTemplate::query()->when($request->keyword, fn ($q, $v) => $q->where(fn ($x) => $x->where('code', 'like', "%$v%")->orWhere('name', 'like', "%$v%")))->when($request->type, fn ($q, $v) => $q->where('type', $v))->latest('updated_at');
        $p = $q->paginate($request->integer('per_page', 20));

        return success_response($p->items(), 'Thành công', 200, ['pagination' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $data['updated_by'] = user_id();

        return success_response(DocumentTemplate::create($data), 'Đã tạo mẫu tài liệu', 201);
    }

    public function show(DocumentTemplate $documentTemplate)
    {
        return success_response($documentTemplate);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $this->validated($request, $documentTemplate->id);
        $data['updated_by'] = user_id();
        $documentTemplate->update($data);

        return success_response($documentTemplate->fresh());
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        abort_if($documentTemplate->generatedDocuments()->exists(), 422, 'Mẫu đã phát sinh tài liệu, không thể xóa.');
        $documentTemplate->delete();

        return success_response();
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $types = implode(',', array_keys(MarketplaceDocumentService::TYPES));
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'unique:document_templates,code,'.($id ?: 'NULL')], 'name' => 'required|string|max:255', 'type' => 'required|in:'.$types, 'target_module' => 'nullable|string|max:100', 'status' => 'required|in:draft,approved,archived', 'version' => 'nullable|integer|min:1', 'merge_fields' => 'nullable|array', 'content_html' => 'required|string', 'description' => 'nullable|string|max:2000']);
        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail($data['type'],$data['content_html']);

        return $data;
    }
}
