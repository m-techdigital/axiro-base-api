<?php

namespace App\Services\Documents;

use App\Models\DocumentAcceptance;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Transaction;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceDocumentService
{
    public const TYPES = [
        'sale_contract' => 'Hồ sơ mua bán tài khoản trò chơi',
        'rental_contract' => 'Hồ sơ thuê tài khoản trò chơi',
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
        $types = [$transaction->transaction_type === 'rental' ? 'rental_contract' : 'sale_contract'];
        if ($transaction->payments->contains(fn ($payment) => in_array($payment->status, ['submitted', 'confirmed'], true))) {
            $types[] = 'payment_confirmation';
        }
        if ($transaction->purchase_mode === 'installment') {
            $types[] = 'installment_appendix';
        }
        if ($transaction->purchase_mode === 'deposit' || (float) $transaction->deposit_amount > 0) {
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
        if ((float) $transaction->refunded_amount > 0) {
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
        if (! isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['document_type' => 'Loại tài liệu không hợp lệ.']);
        }
        $template = DocumentTemplate::query()->where('code', $type)->where('status', 'approved')->firstOrFail();
        app(MarketplaceDocumentTemplateValidator::class)->validateOrFail($type, $template->content_html);
        $existing = GeneratedDocument::query()->where('transaction_id', $transaction->id)->where('document_type', $type)->latest('version')->first();
        if ($existing && ! $regenerate) {
            return $existing->load(['template', 'acceptances.customer:id,code,name']);
        }
        $version = $existing ? $existing->version + 1 : 1;
        $payload = $this->payload($transaction->fresh()->load(['product.offerModes', 'buyer', 'seller', 'payments', 'events', 'disputes', 'checkpoints']));
        $html = $this->render($template->content_html, $payload);
        if (preg_match('/\{\{[^}]+\}\}/', $html, $matches)) {
            throw ValidationException::withMessages(['content_html' => 'Tài liệu còn trường trộn chưa được thay thế: '.$matches[0]]);
        }

        return GeneratedDocument::create([
            'code' => 'DOC-'.strtoupper(Str::random(10)), 'document_template_id' => $template->id,
            'transaction_id' => $transaction->id,
            'document_type' => $type, 'status' => 'issued', 'version' => $version,
            'title' => self::TYPES[$type].' - '.$transaction->code, 'merge_payload' => $payload,
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
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($document->rendered_html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function payload(Transaction $transaction): array
    {
        $payments = '<table><thead><tr><th>Mã khoản</th><th>Số tiền</th><th>Hạn</th><th>Trạng thái</th></tr></thead><tbody>'.
            $transaction->payments->map(fn ($p) => '<tr><td>'.e($p->code).'</td><td class="right">'.$this->money($p->amount).'</td><td>'.e($p->due_date ?: '—').'</td><td>'.e($this->label($p->status)).'</td></tr>')->implode('').'</tbody></table>';
        if ($transaction->payments->isEmpty()) {
            $payments = '<div class="notice">Chưa có kế hoạch thanh toán.</div>';
        }
        $dispute = $transaction->disputes->sortByDesc('id')->first();
        $attributes = collect($transaction->product?->attributes ?: [])->map(fn ($v, $k) => e((string) $k).': '.e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)))->implode('<br>') ?: 'Không có thông tin bổ sung.';
        $security = collect($transaction->product?->attributes ?: [])->only(['email_linked', 'phone_linked', 'social_linked', 'two_factor', 'changeable'])->map(fn ($v, $k) => e((string) $k).': '.e(is_bool($v) ? ($v ? 'Có' : 'Không') : (string) $v))->implode('<br>') ?: 'Chưa khai báo chi tiết; các bên phải kiểm tra tại thời điểm bàn giao.';
        $checkpoints = $transaction->checkpoints->sortBy('confirmed_at')->map(fn ($c) => e($c->checkpoint).' — '.optional($c->confirmed_at)->format('d/m/Y H:i').' — '.e($c->note ?: 'Đã xác nhận'))->implode('<br>') ?: 'Chưa có mốc xác nhận.';

        return [
            'operator_name' => config('marketplace_documents.operator.name'), 'operator_tax_code' => config('marketplace_documents.operator.tax_code'),
            'operator_address' => config('marketplace_documents.operator.address'), 'operator_support_phone' => config('marketplace_documents.operator.support_phone'),
            'operator_support_email' => config('marketplace_documents.operator.support_email'), 'operator_website' => config('marketplace_documents.operator.website'),
            'policy_version' => config('marketplace_documents.policy_version'), 'document_date' => now()->format('d/m/Y'), 'document_time' => now()->format('H:i:s'),
            'transaction_code' => $transaction->code,
            'transaction_type' => $transaction->transaction_type === 'rental' ? 'Cho thuê' : 'Bán',
            'purchase_mode' => match ($transaction->purchase_mode) {
                'installment' => 'Trả góp', 'deposit' => 'Đặt cọc', default => 'Thanh toán đủ'
            },
            'product_name' => $transaction->product?->name ?: 'Tài khoản trò chơi',
            'product_code' => $transaction->product?->code ?: '—',
            'product_type' => $transaction->product?->product_type ?: '—',
            'product_offer_modes' => collect($transaction->product?->offer_modes ?: [])->map(fn (string $mode) => match ($mode) {
                'sell' => 'Bán', 'rent' => 'Cho thuê', default => $mode
            })->implode(', ') ?: '—',
            'game_code' => $transaction->product?->game_code ?: '—', 'server_name' => $transaction->product?->server_name ?: '—',
            'level' => $transaction->product?->level ?: '—', 'product_attributes' => $attributes, 'product_security_state' => $security,
            'buyer_name' => $transaction->buyer?->name ?: '—', 'buyer_code' => $transaction->buyer?->code ?: '—', 'buyer_phone' => $transaction->buyer?->phone ?: '—', 'buyer_email' => $transaction->buyer?->email ?: '—',
            'seller_name' => $transaction->seller?->name ?: '—', 'seller_code' => $transaction->seller?->code ?: '—', 'seller_phone' => $transaction->seller?->phone ?: '—', 'seller_email' => $transaction->seller?->email ?: '—',
            'transaction_value' => $this->money($transaction->transaction_value), 'service_fee' => $this->money($transaction->service_fee), 'discount' => $this->money($transaction->discount),
            'deposit_amount' => $this->money($transaction->deposit_amount), 'initial_payment_amount' => $this->money($transaction->initial_payment_amount), 'installment_count' => $transaction->installment_count ?: '—',
            'total_payable' => $this->money($transaction->total_payable), 'paid_amount' => $this->money($transaction->paid_amount),
            'remaining_amount' => $this->money(max(0, (float) $transaction->total_payable - (float) $transaction->paid_amount)), 'refunded_amount' => $this->money($transaction->refunded_amount),
            'transaction_date' => optional($transaction->transaction_date)->format('d/m/Y') ?: (string) $transaction->transaction_date, 'due_date' => optional($transaction->due_date)->format('d/m/Y') ?: ($transaction->due_date ?: '—'),
            'rental_start' => optional($transaction->rental_start_at)->format('d/m/Y H:i') ?: ($transaction->rental_start_at ?: '—'), 'rental_end' => optional($transaction->rental_end_at)->format('d/m/Y H:i') ?: ($transaction->rental_end_at ?: '—'),
            'status' => $this->label($transaction->status), 'payment_method' => $transaction->payment_method ?: 'Chưa xác định', 'payment_schedule' => $payments,
            'handover_time' => optional($transaction->handed_over_at)->format('d/m/Y H:i') ?: 'Chưa xác nhận', 'return_time' => optional($transaction->returned_at)->format('d/m/Y H:i') ?: 'Chưa xác nhận',
            'completed_at' => optional($transaction->completed_at)->format('d/m/Y H:i') ?: 'Chưa hoàn tất', 'checkpoint_summary' => $checkpoints,
            'dispute_reason' => $dispute?->reason ?: '—', 'dispute_description' => $dispute?->description ?: 'Chưa có tranh chấp.',
            'dispute_resolution' => $dispute?->resolution ?: 'Chưa có kết luận.', 'dispute_resolved_at' => optional($dispute?->resolved_at)->format('d/m/Y H:i') ?: 'Chưa xử lý',
            'refund_reason' => $transaction->note ?: 'Theo kết quả đối soát giao dịch.', 'note' => $transaction->note ?: 'Không có ghi chú.',
        ];
    }

    private function render(string $html, array $payload): string
    {
        $htmlFields = ['payment_schedule', 'product_attributes', 'product_security_state', 'checkpoint_summary'];
        foreach ($payload as $key => $value) {
            $rendered = in_array($key, $htmlFields, true) ? (string) $value : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = str_replace('{{'.$key.'}}', $rendered, $html);
        }

        return $html;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    private function label(string $value): string
    {
        return match ($value) {
            'pending' => 'Đang chờ','submitted' => 'Đã gửi đối soát','confirmed' => 'Đã xác nhận','rejected' => 'Bị từ chối','pending_payment' => 'Chờ thanh toán','partially_paid' => 'Đã thanh toán một phần','paid' => 'Đã thanh toán','handover_pending' => 'Chờ bên nhận xác nhận','handed_over' => 'Đã bàn giao','active' => 'Đang thuê','return_pending' => 'Chờ xác nhận hoàn trả','returned' => 'Đã hoàn trả','completed' => 'Hoàn tất','cancelled' => 'Đã hủy','disputed' => 'Đang tranh chấp',default => $value
        };
    }
}
