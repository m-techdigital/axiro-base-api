<?php
namespace App\Services\Auth;
use App\Models\Customer;
use App\Models\CustomerSecurityToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
class CustomerSecurityService {
    private function issue(Customer $customer, string $purpose, array $payload = [], int $minutes = 30): string {
        CustomerSecurityToken::where('customer_id',$customer->id)->where('purpose',$purpose)->whereNull('used_at')->update(['used_at'=>now()]);
        $plain = Str::random(64);
        CustomerSecurityToken::create([
            'customer_id'=>$customer->id,'purpose'=>$purpose,'token'=>hash('sha256',$plain),
            'payload'=>$payload,'expires_at'=>now()->addMinutes($minutes),
        ]);
        return $plain;
    }
    private function consume(string $plain, string $purpose): ?CustomerSecurityToken {
        return CustomerSecurityToken::with('customer')->where('token',hash('sha256',$plain))->where('purpose',$purpose)
            ->whereNull('used_at')->where('expires_at','>',now())->first();
    }
    private function frontendUrl(string $path, array $query): string {
        $base = rtrim((string)config('app.frontend_url', env('FRONTEND_URL','http://127.0.0.1:5174')),'/');
        return $base.$path.'?'.http_build_query($query);
    }
    public function requestEmailChange(Customer $customer, string $newEmail): void {
        $token=$this->issue($customer,'email_change',['email'=>$newEmail],60);
        $url=$this->frontendUrl('/account/verify-email',['token'=>$token]);
        Mail::raw("Bạn đã yêu cầu đổi địa chỉ thư điện tử MBN sang {$newEmail}.\n\nXác nhận tại: {$url}\n\nLiên kết có hiệu lực trong 60 phút.", fn($m)=>$m->to($newEmail)->subject('Xác nhận đổi địa chỉ thư điện tử MBN'));
    }
    public function verifyEmailChange(string $plain): Customer {
        return DB::transaction(function() use($plain){
            $record=$this->consume($plain,'email_change');
            abort_unless($record,422,'Liên kết xác nhận không hợp lệ hoặc đã hết hạn.');
            $email=(string)($record->payload['email']??'');
            abort_if(Customer::where('email',$email)->whereKeyNot($record->customer_id)->exists(),422,'Địa chỉ thư điện tử đã được sử dụng.');
            $record->customer->update(['email'=>$email,'email_verified_at'=>now()]);
            $record->update(['used_at'=>now()]);
            return $record->customer->fresh();
        });
    }
    public function changePassword(Customer $customer, string $current, string $new): void {
        abort_unless(Hash::check($current,$customer->password),422,'Mật khẩu hiện tại không đúng.');
        $customer->update(['password'=>$new]);
        \App\Models\CustomerRefreshToken::where('customer_id',$customer->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
    }
    public function forgotPassword(string $login): void {
        $customer=Customer::where('email',$login)->orWhere('username',$login)->first();
        if(!$customer || !$customer->email) return;
        $token=$this->issue($customer,'password_reset',[],30);
        $url=$this->frontendUrl('/reset-password',['token'=>$token,'email'=>$customer->email]);
        Mail::raw("Bạn đã yêu cầu đặt lại mật khẩu MBN.\n\nĐặt lại tại: {$url}\n\nLiên kết có hiệu lực trong 30 phút.", fn($m)=>$m->to($customer->email)->subject('Đặt lại mật khẩu MBN'));
    }
    public function resetPassword(string $plain, string $email, string $password): void {
        DB::transaction(function() use($plain,$email,$password){
            $record=$this->consume($plain,'password_reset');
            abort_unless($record && strcasecmp((string)$record->customer->email,$email)===0,422,'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            $record->customer->update(['password'=>$password]);
            \App\Models\CustomerRefreshToken::where('customer_id',$record->customer_id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            $record->update(['used_at'=>now()]);
        });
    }
}
