<?php

namespace App\Services\Payouts;

use App\Models\CustomerVerification;
use App\Models\CustomerWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Contracts\Pagination\Paginator;

class PayoutJourneyPresenter
{
    public function customer(
        CustomerWallet $wallet,
        CustomerVerification $verification,
        $accounts,
        $withdrawals,
    ): array {
        $verifiedAccounts = collect($accounts)->where('status', 'verified');
        $latestWithdrawal = collect($withdrawals instanceof Paginator
            ? $withdrawals->items()
            : $withdrawals)->first();

        $steps = [
            [
                'key' => 'verification',
                'label' => 'Xác minh người bán',
                'status' => $verification->status === 'verified'
                    ? 'completed'
                    : ($verification->status === 'pending' ? 'current' : 'blocked'),
                'detail' => $this->verificationDetail($verification),
            ],
            [
                'key' => 'payout_account',
                'label' => 'Tài khoản nhận tiền',
                'status' => $verifiedAccounts->isNotEmpty()
                    ? 'completed'
                    : ($verification->status === 'verified' ? 'current' : 'blocked'),
                'detail' => $verifiedAccounts->isNotEmpty()
                    ? 'Đã có tài khoản nhận tiền được xác minh.'
                    : 'Cần ít nhất một tài khoản nhận tiền được xác minh.',
            ],
            [
                'key' => 'withdrawal',
                'label' => 'Yêu cầu rút tiền',
                'status' => $latestWithdrawal
                    ? ($latestWithdrawal->status === 'paid' ? 'completed' : 'current')
                    : ($verifiedAccounts->isNotEmpty() ? 'current' : 'blocked'),
                'detail' => $latestWithdrawal
                    ? $this->withdrawalDetail($latestWithdrawal)
                    : 'Chưa có yêu cầu rút tiền.',
            ],
        ];

        $blockedReasons = [];
        if ($verification->status !== 'verified') {
            $blockedReasons[] = $this->verificationDetail($verification);
        }
        if ($verifiedAccounts->isEmpty()) {
            $blockedReasons[] = 'Chưa có tài khoản nhận tiền đã xác minh.';
        }
        if (bccomp((string) $wallet->available_balance, '50000', 2) < 0) {
            $blockedReasons[] = 'Số dư khả dụng chưa đạt mức rút tối thiểu 50.000 đ.';
        }

        $canWithdraw = $verification->status === 'verified'
            && $verifiedAccounts->isNotEmpty()
            && bccomp((string) $wallet->available_balance, '50000', 2) >= 0;

        return [
            'steps' => $steps,
            'can_withdraw' => $canWithdraw,
            'next_action' => $this->customerNextAction($verification, $verifiedAccounts->isNotEmpty(), $latestWithdrawal),
            'blocked_reasons' => array_values(array_unique($blockedReasons)),
            'latest_withdrawal' => $latestWithdrawal,
        ];
    }

    public function adminWithdrawal(WithdrawalRequest $withdrawal): array
    {
        $action = match ($withdrawal->status) {
            'submitted' => ['key' => 'approve', 'label' => 'Duyệt yêu cầu rút tiền'],
            'approved' => ['key' => 'mark_paid', 'label' => 'Xác nhận đã chi trả'],
            'paid' => null,
            'rejected' => null,
            default => null,
        };

        return [
            'status' => $withdrawal->status,
            'next_action' => $action,
            'blocked_reason' => $action === null
                ? match ($withdrawal->status) {
                    'paid' => 'Yêu cầu đã được chi trả.',
                    'rejected' => 'Yêu cầu đã bị từ chối.',
                    default => 'Yêu cầu không có thao tác tiếp theo.',
                }
                : null,
            'customer_context' => [
                'verification_status' => $withdrawal->customer?->verification?->status,
                'available_balance' => $withdrawal->customer?->wallet?->available_balance,
                'held_balance' => $withdrawal->customer?->wallet?->held_balance,
                'payout_account_status' => $withdrawal->payoutAccount?->status,
            ],
        ];
    }

    private function verificationDetail(CustomerVerification $verification): string
    {
        return match ($verification->status) {
            'verified' => 'Hồ sơ người bán đã được xác minh.',
            'pending' => 'Hồ sơ đang chờ Admin xác minh.',
            'rejected' => $verification->review_note
                ? 'Hồ sơ bị từ chối: '.$verification->review_note
                : 'Hồ sơ bị từ chối, vui lòng cập nhật và gửi lại.',
            default => 'Cần gửi hồ sơ xác minh người bán.',
        };
    }

    private function withdrawalDetail(WithdrawalRequest $withdrawal): string
    {
        return match ($withdrawal->status) {
            'submitted' => 'Yêu cầu đang chờ Admin duyệt.',
            'approved' => 'Yêu cầu đã duyệt, đang chờ chi trả.',
            'paid' => 'Yêu cầu đã được chi trả.',
            'rejected' => $withdrawal->review_note
                ? 'Yêu cầu bị từ chối: '.$withdrawal->review_note
                : 'Yêu cầu đã bị từ chối.',
            default => 'Đang cập nhật trạng thái yêu cầu.',
        };
    }

    private function customerNextAction(
        CustomerVerification $verification,
        bool $hasVerifiedAccount,
        ?WithdrawalRequest $latestWithdrawal,
    ): ?array {
        if ($verification->status !== 'verified') {
            return ['key' => 'submit_verification', 'label' => 'Hoàn tất xác minh người bán'];
        }
        if (! $hasVerifiedAccount) {
            return ['key' => 'add_payout_account', 'label' => 'Thêm tài khoản nhận tiền'];
        }
        if ($latestWithdrawal && in_array($latestWithdrawal->status, ['submitted', 'approved'], true)) {
            return ['key' => 'track_withdrawal', 'label' => 'Theo dõi yêu cầu rút tiền'];
        }

        return ['key' => 'create_withdrawal', 'label' => 'Tạo yêu cầu rút tiền'];
    }
}
