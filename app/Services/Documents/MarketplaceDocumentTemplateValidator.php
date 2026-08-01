<?php

namespace App\Services\Documents;

use Illuminate\Validation\ValidationException;

class MarketplaceDocumentTemplateValidator
{
    private const REQUIRED_COMMON = [
        '{{transaction_code}}', '{{document_date}}', '{{buyer_name}}', '{{seller_name}}',
        '{{product_name}}', '{{total_payable}}',
    ];

    private const REQUIRED_BY_TYPE = [
        'sale_contract' => ['{{transaction_value}}', '{{payment_schedule}}'],
        'rental_contract' => ['{{rental_start}}', '{{rental_end}}', '{{deposit_amount}}'],
        'installment_appendix' => ['{{initial_payment_amount}}', '{{installment_count}}', '{{payment_schedule}}'],
        'deposit_confirmation' => ['{{deposit_amount}}', '{{remaining_amount}}'],
        'payment_confirmation' => ['{{paid_amount}}', '{{remaining_amount}}', '{{payment_schedule}}'],
        'handover_minutes' => ['{{handover_time}}', '{{product_security_state}}'],
        'return_minutes' => ['{{return_time}}', '{{deposit_amount}}'],
        'dispute_minutes' => ['{{dispute_reason}}', '{{dispute_description}}'],
        'dispute_resolution' => ['{{dispute_resolution}}', '{{dispute_resolved_at}}'],
        'refund_settlement' => ['{{refunded_amount}}', '{{refund_reason}}'],
        'completion_minutes' => ['{{completed_at}}', '{{status}}'],
        'security_checklist' => ['{{product_security_state}}', '{{handover_time}}'],
        'platform_transaction_record' => ['{{product_code}}', '{{product_type}}', '{{transaction_type}}', '{{purchase_mode}}'],
    ];

    public function validateOrFail(string $type, string $html): void
    {
        $errors = [];
        if (mb_strlen(strip_tags($html)) < 500) {
            $errors[] = 'Nội dung mẫu quá ngắn; cần có đầy đủ phạm vi, thông tin các bên, nghĩa vụ, xử lý vi phạm và xác nhận.';
        }
        foreach (array_unique([...self::REQUIRED_COMMON, ...(self::REQUIRED_BY_TYPE[$type] ?? [])]) as $field) {
            if (! str_contains($html, $field)) {
                $errors[] = "Thiếu trường trộn bắt buộc {$field}.";
            }
        }
        $plain = mb_strtolower(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $requiredSections = [
            'quyền và nghĩa vụ' => ['/quyền\s*(?:,|và)?\s*nghĩa vụ/u', '/nghĩa vụ\s*(?:,|và)?\s*quyền/u'],
            'tranh chấp' => ['/tranh chấp/u', '/khiếu nại/u'],
            'bảo mật' => ['/bảo mật/u', '/thông tin xác thực/u'],
            'xác nhận điện tử' => ['/xác nhận điện tử/u'],
            'miễn trừ' => ['/miễn trừ/u', '/giới hạn trách nhiệm/u'],
            'nhà phát hành' => ['/nhà phát hành/u'],
            'pháp luật' => ['/pháp luật/u', '/cơ quan có thẩm quyền/u'],
            'sửa đổi điều khoản' => ['/sửa đổi điều khoản/u', '/phiên bản áp dụng/u'],
        ];
        foreach ($requiredSections as $label => $patterns) {
            if (! collect($patterns)->contains(fn (string $pattern) => preg_match($pattern, $plain) === 1)) {
                $errors[] = "Thiếu nội dung bắt buộc liên quan đến {$label}.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['content_html' => $errors]);
        }
    }
}
