<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerSecurityToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerTwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function beginSetup(Customer $customer): array
    {
        if ($this->enabled($customer)) {
            throw ValidationException::withMessages([
                'two_factor' => 'Xác thực hai lớp đang hoạt động. Hãy tắt cấu hình hiện tại trước khi thiết lập lại.',
            ]);
        }

        $secret = $this->randomSecret();
        $customer->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = rawurlencode(config('app.name', 'MuaBanNick.Pro'));
        $account = rawurlencode($customer->email ?: $customer->username);

        return [
            'secret' => $secret,
            'otpauth_url' => "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&digits=6&period=30",
        ];
    }

    public function confirm(Customer $customer, string $code): array
    {
        return DB::transaction(function () use ($customer, $code): array {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $secret = $this->secret($locked);

            if (! $secret || ! $this->verifyCode($secret, $code)) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực không đúng hoặc đã hết hạn.']);
            }

            $codes = $this->generateRecoveryCodes();
            $locked->forceFill([
                'two_factor_recovery_codes' => $this->encryptRecoveryCodes($codes),
                'two_factor_confirmed_at' => now(),
            ])->save();

            return ['recovery_codes' => $codes];
        });
    }

    public function disable(Customer $customer, string $password, string $code): void
    {
        if (! password_verify($password, $customer->password)) {
            throw ValidationException::withMessages(['password' => 'Mật khẩu hiện tại không đúng.']);
        }

        DB::transaction(function () use ($customer, $code): void {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if (! $this->verifyForCustomer($locked, $code, true, false)) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực hoặc mã khôi phục không đúng.']);
            }

            $locked->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            CustomerSecurityToken::query()
                ->where('customer_id', $locked->id)
                ->where('purpose', 'two_factor_login')
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        });
    }

    public function regenerateRecoveryCodes(Customer $customer, string $code): array
    {
        return DB::transaction(function () use ($customer, $code): array {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if (! $this->verifyForCustomer($locked, $code, true, false)) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực hoặc mã khôi phục không đúng.']);
            }

            $codes = $this->generateRecoveryCodes();
            $locked->forceFill([
                'two_factor_recovery_codes' => $this->encryptRecoveryCodes($codes),
            ])->save();

            return ['recovery_codes' => $codes];
        });
    }

    public function issueChallenge(Customer $customer): string
    {
        return DB::transaction(function () use ($customer): string {
            $plain = Str::random(80);

            CustomerSecurityToken::query()
                ->where('customer_id', $customer->id)
                ->where('purpose', 'two_factor_login')
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            CustomerSecurityToken::create([
                'customer_id' => $customer->id,
                'purpose' => 'two_factor_login',
                'token' => hash('sha256', $plain),
                'payload' => [
                    'ip' => request()->ip(),
                    'user_agent_hash' => hash('sha256', (string) request()->userAgent()),
                ],
                'expires_at' => now()->addMinutes(5),
            ]);

            return $plain;
        });
    }

    public function consumeChallenge(string $challenge, string $code): ?Customer
    {
        return DB::transaction(function () use ($challenge, $code): ?Customer {
            $row = CustomerSecurityToken::query()
                ->where('purpose', 'two_factor_login')
                ->where('token', hash('sha256', $challenge))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            $customer = Customer::query()->lockForUpdate()->find($row->customer_id);
            if (! $customer || ! $this->verifyForCustomer($customer, $code, true, true)) {
                return null;
            }

            $row->update(['used_at' => now()]);

            return $customer;
        });
    }

    public function enabled(Customer $customer): bool
    {
        return (bool) $customer->two_factor_confirmed_at && (bool) $customer->two_factor_secret;
    }

    /**
     * @param bool $persistRecoveryConsumption Set false only when the caller already holds a lock
     *                                         and wants to defer persistence to a later update.
     */
    public function verifyForCustomer(
        Customer $customer,
        string $code,
        bool $consumeRecovery = true,
        bool $persistRecoveryConsumption = true,
    ): bool {
        $secret = $this->secret($customer);
        if ($secret && $this->verifyCode($secret, $code)) {
            return true;
        }

        $hash = hash('sha256', strtoupper(trim($code)));
        $codes = $this->recoveryHashes($customer);
        $index = array_search($hash, $codes, true);

        if ($index === false) {
            return false;
        }

        if ($consumeRecovery) {
            unset($codes[$index]);
            $customer->forceFill([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR)),
            ]);

            if ($persistRecoveryConsumption) {
                $customer->save();
            }
        }

        return true;
    }

    private function secret(Customer $customer): ?string
    {
        try {
            return $customer->two_factor_secret
                ? Crypt::decryptString($customer->two_factor_secret)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function recoveryHashes(Customer $customer): array
    {
        try {
            return $customer->two_factor_recovery_codes
                ? (json_decode(Crypt::decryptString($customer->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR) ?: [])
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function encryptRecoveryCodes(array $codes): string
    {
        $hashes = array_map(
            fn (string $code): string => hash('sha256', strtoupper(trim($code))),
            $codes,
        );

        return Crypt::encryptString(json_encode($hashes, JSON_THROW_ON_ERROR));
    }

    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => strtoupper(Str::random(5).'-'.Str::random(5)),
            range(1, 8),
        );
    }

    private function randomSecret(int $length = 32): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, 31)];
        }

        return $out;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }

        $counter = (int) floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->totp($secret, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function totp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $bin = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24)
            | ((ord($hash[$offset + 1]) & 255) << 16)
            | ((ord($hash[$offset + 2]) & 255) << 8)
            | (ord($hash[$offset + 3]) & 255);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper($value)) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
