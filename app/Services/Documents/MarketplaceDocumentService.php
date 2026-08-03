<?php

namespace App\Services\Documents;

use App\Models\DocumentAcceptance;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use App\Support\Marketplace\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceDocumentService
{
    public function __construct(
        private MarketplaceDocumentPayloadBuilder $payloadBuilder,
        private MarketplaceDocumentRenderer $renderer,
    ) {}

    public const TYPES = [
        'sale_record' => 'Hồ sơ mua bán tài khoản trò chơi',
        'rental_record' => 'Hồ sơ thuê tài khoản trò chơi',
        'installment_appendix' => 'Phụ lục lịch thanh toán trả góp',
        'deposit_confirmation' => 'Thỏa thuận đặt cọc giữ tài khoản',
        'payment_confirmation' => 'Xác nhận thanh toán giao dịch',
        'handover_minutes' => 'Biên bản bàn giao tài khoản',
        'return_minutes' => 'Biên bản hoàn trả tài khoản thuê',
        'dispute_minutes' => 'Biên bản tiếp nhận tranh chấp',
        'dispute_resolution' => 'Biên bản xử lý tranh chấp',
        'refund_settlement' => 'Biên bản hoàn tiền và đối soát',
        'completion_minutes' => 'Biên bản hoàn tất giao dịch',
        'security_checklist' => 'Phiếu kiểm tra bảo mật khi bàn giao',
        'platform_transaction_record' => 'Phiếu ghi nhận giao dịch trên nền tảng',
    ];

    public function ensureForTransaction(Transaction $transaction): Collection
    {
        $transaction->loadMissing(['product.offerModes', 'buyer', 'seller', 'payments', 'events', 'disputes', 'checkpoints']);
        $types = [$transaction->transaction_type === 'rental' ? DocumentType::RENTAL_RECORD : DocumentType::SALE_RECORD];
        if ($transaction->payments->contains(fn ($payment) => in_array($payment->status, ['submitted', 'confirmed'], true))) {
            $types[] = 'payment_confirmation';
        }
        if ($transaction->purchase_mode === 'installment') {
            $types[] = 'installment_appendix';
        }
        if ($transaction->purchase_mode === 'deposit' || bccomp((string) $transaction->deposit_amount, '0.00', 2) > 0) {
            $types[] = 'deposit_confirmation';
        }
        if (in_array($transaction->status, ['handover_pending', 'handed_over', 'active', 'return_pending', 'returned', 'completed', 'disputed'], true)) {
            $types[] = 'handover_minutes';
            $types[] = 'security_checklist';
        }
        if ($transaction->transaction_type === 'rental' && in_array($transaction->status, ['return_pending', 'returned', 'completed', 'disputed'], true)) {
            $types[] = 'return_minutes';
        }
        if ($transaction->status === 'disputed' || $transaction->disputes->isNotEmpty()) {
            $types[] = 'dispute_minutes';
        }
        if ($transaction->disputes->contains(fn ($d) => $d->status === 'resolved')) {
            $types[] = 'dispute_resolution';
        }
        if (bccomp((string) $transaction->refunded_amount, '0.00', 2) > 0) {
            $types[] = 'refund_settlement';
        }
        if ($transaction->status === 'completed') {
            $types[] = 'completion_minutes';
        }
        foreach (array_unique($types) as $type) {
            $this->generate($transaction, $type);
        }

        return GeneratedDocument::query()->where('transaction_id', $transaction->id)->whereIn('document_type', array_unique($types))->with(['template', 'acceptances.customer:id,code,name'])->orderBy('id')->get();
    }

    public function generate(Transaction $transaction, string $type, ?int $adminId = null, bool $regenerate = false): GeneratedDocument
    {
        $type = DocumentType::canonical($type);
        if (! isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['document_type' => 'Loại tài liệu không hợp lệ.']);
        }
        $template = DocumentTemplate::query()
            ->whereIn('code', DocumentType::aliasesFor($type))
            ->where('status', 'approved')
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$type])
            ->orderByDesc('version')
            ->firstOrFail();
        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail($type, $template->content_html);
        $existing = GeneratedDocument::query()->where('transaction_id', $transaction->id)->where('document_type', $type)->latest('version')->first();
        if ($existing && ! $regenerate) {
            return $existing->load(['template', 'acceptances.customer:id,code,name']);
        }
        $version = $existing ? $existing->version + 1 : 1;
        $payload = $this->payloadBuilder->build($transaction->fresh()->load(['product.offerModes', 'buyer', 'seller', 'payments', 'events', 'disputes', 'checkpoints']));
        $html = $this->renderer->merge($template->content_html, $payload);
        if (preg_match('/\{\{[^}]+\}\}/', $html, $matches)) {
            throw ValidationException::withMessages(['content_html' => 'Tài liệu còn trường trộn chưa được thay thế: '.$matches[0]]);
        }

        return GeneratedDocument::create([
            'code' => 'DOC-'.strtoupper(Str::random(10)), 'document_template_id' => $template->id,
            'transaction_id' => $transaction->id,
            'document_type' => $type, 'status' => 'issued', 'version' => $version,
            'title' => DocumentType::label($type).' - '.$transaction->code, 'merge_payload' => $payload,
            'rendered_html' => $html, 'issued_at' => now(), 'issued_by' => $adminId,
        ])->load(['template', 'acceptances.customer:id,code,name']);
    }

    public function accept(GeneratedDocument $document, int $customerId, Request $request): DocumentAcceptance
    {
        $request->validate(['accepted_terms' => ['required', 'accepted'], 'acceptance_statement' => ['required', 'string', 'max:1000']]);
        $transaction = $document->transaction()->firstOrFail();
        if (! in_array($customerId, [$transaction->buyer_customer_id, $transaction->seller_customer_id], true)) {
            abort(403);
        }
        $existing = DocumentAcceptance::query()->where('generated_document_id', $document->id)->where('customer_id', $customerId)->first();
        if ($existing?->status === 'accepted') {
            return $existing->load('customer:id,code,name');
        }
        $role = $customerId === $transaction->buyer_customer_id ? 'buyer' : 'seller';
        $acceptance = DocumentAcceptance::query()->updateOrCreate(
            ['generated_document_id' => $document->id, 'customer_id' => $customerId],
            ['role' => $role, 'status' => 'accepted', 'accepted_at' => now(), 'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'metadata' => ['document_version' => $document->version, 'document_sha256' => hash('sha256', $document->rendered_html),
                    'acceptance_statement' => $request->string('acceptance_statement')->toString()]]
        )->load('customer:id,code,name');
        if ($document->acceptances()->count() >= 2) {
            $document->update(['status' => 'accepted']);
        }

        return $acceptance;
    }

    public function authorizeCustomer(GeneratedDocument $document, int $customerId): void
    {
        $transaction = $document->transaction()->firstOrFail();
        abort_unless(in_array($customerId, [$transaction->buyer_customer_id, $transaction->seller_customer_id], true), 403);
    }

    public function pdf(GeneratedDocument $document): string
    {
        return $this->renderer->pdf($document);
    }

    public function payload(Transaction $transaction): array
    {
        return $this->payloadBuilder->build($transaction);
    }
}
