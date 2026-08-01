<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Services\Documents\MarketplaceDocumentService;
use Illuminate\Http\Request;

class CustomerDocumentController extends Controller
{
    public function index()
    {
        $customerId = auth('customer_api')->id();
        $documents = GeneratedDocument::query()->whereHas('transaction', fn ($q) => $q->where('buyer_customer_id', $customerId)->orWhere('seller_customer_id', $customerId))->with(['transaction:id,code,buyer_customer_id,seller_customer_id', 'template:id,code,name,type', 'acceptances.customer:id,code,name'])->latest('issued_at')->get();

        return success_response($documents);
    }

    public function transactionDocuments(Transaction $transaction, MarketplaceDocumentService $service)
    {
        $this->party($transaction);
        $documents = $service->ensureForTransaction($transaction);
        $customerId = auth('customer_api')->id();
        $documents->each(fn ($document) => $document->setAttribute('accepted_by_current_customer', $document->acceptances->contains('customer_id', $customerId)));

        return success_response($documents);
    }

    public function preview(GeneratedDocument $generatedDocument, MarketplaceDocumentService $service)
    {
        $service->authorizeCustomer($generatedDocument, auth('customer_api')->id());
        $loaded = $generatedDocument->load('acceptances.customer:id,code,name');

        return success_response(['id' => $loaded->id, 'title' => $loaded->title, 'html' => $loaded->rendered_html, 'status' => $loaded->status, 'version' => $loaded->version, 'acceptances' => $loaded->acceptances, 'accepted_by_current_customer' => $loaded->acceptances->contains('customer_id', auth('customer_api')->id())]);
    }

    public function download(GeneratedDocument $generatedDocument, MarketplaceDocumentService $service)
    {
        $service->authorizeCustomer($generatedDocument, auth('customer_api')->id());

        return response($service->pdf($generatedDocument), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.str($generatedDocument->code)->slug().'.pdf"']);
    }

    public function accept(Request $request, GeneratedDocument $generatedDocument, MarketplaceDocumentService $service)
    {
        $service->authorizeCustomer($generatedDocument, auth('customer_api')->id());

        return success_response($service->accept($generatedDocument, auth('customer_api')->id(), $request), 'Đã xác nhận tài liệu');
    }

    private function party(Transaction $transaction): void
    {
        abort_unless(in_array(auth('customer_api')->id(), [$transaction->buyer_customer_id, $transaction->seller_customer_id], true), 403);
    }
}
