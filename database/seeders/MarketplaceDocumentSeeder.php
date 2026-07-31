<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Documents\MarketplaceDocumentService;
use Illuminate\Database\Seeder;

class MarketplaceDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', env('ADMIN_USERNAME', 'admin'))->first();
        foreach ($this->templates() as $code => $template) {
            DocumentTemplate::query()->updateOrCreate(
                ['code' => $code],
                [...$template, 'target_module' => 'transactions', 'status' => 'approved', 'version' => 3,
                    'merge_fields' => $this->mergeFields(), 'created_by' => $admin?->id, 'updated_by' => $admin?->id]
            );
        }
        $service = app(MarketplaceDocumentService::class);
        Transaction::query()->where('code', 'like', 'TRX-DEMO-%')->each(fn (Transaction $transaction) => $service->ensureForTransaction($transaction));
    }

    private function mergeFields(): array
    {
        return [
            'operator_name','operator_tax_code','operator_address','operator_support_phone','operator_support_email','operator_website','policy_version',
            'transaction_code','transaction_type','purchase_mode','listing_code','listing_title','listing_type','product_name','product_code','game_code','server_name','level','product_attributes','product_security_state',
            'buyer_name','buyer_code','buyer_phone','buyer_email','seller_name','seller_code','seller_phone','seller_email',
            'transaction_value','service_fee','discount','deposit_amount','initial_payment_amount','installment_count','total_payable','paid_amount','remaining_amount','refunded_amount',
            'transaction_date','due_date','rental_start','rental_end','status','payment_method','payment_schedule','handover_time','return_time','completed_at',
            'checkpoint_summary','dispute_reason','dispute_description','dispute_resolution','dispute_resolved_at','refund_reason','note','document_date','document_time'
        ];
    }

    private function templates(): array
    {
        return [
            'sale_contract' => $this->template('Mẫu hợp đồng mua bán tài khoản trò chơi', 'sale_contract', 'HỢP ĐỒNG MUA BÁN TÀI KHOẢN TRÒ CHƠI', $this->saleBody()),
            'rental_contract' => $this->template('Mẫu hợp đồng thuê tài khoản trò chơi', 'rental_contract', 'HỢP ĐỒNG THUÊ TÀI KHOẢN TRÒ CHƠI', $this->rentalBody()),
            'installment_appendix' => $this->template('Phụ lục lịch thanh toán trả góp', 'installment_appendix', 'PHỤ LỤC LỊCH THANH TOÁN TRẢ GÓP', $this->installmentBody()),
            'deposit_confirmation' => $this->template('Thỏa thuận đặt cọc giữ tài khoản', 'deposit_confirmation', 'THỎA THUẬN ĐẶT CỌC GIỮ TÀI KHOẢN', $this->depositBody()),
            'payment_confirmation' => $this->template('Xác nhận thanh toán giao dịch', 'payment_confirmation', 'XÁC NHẬN THANH TOÁN GIAO DỊCH', $this->paymentBody()),
            'handover_minutes' => $this->template('Biên bản bàn giao tài khoản', 'handover_minutes', 'BIÊN BẢN BÀN GIAO TÀI KHOẢN', $this->handoverBody()),
            'return_minutes' => $this->template('Biên bản hoàn trả tài khoản thuê', 'return_minutes', 'BIÊN BẢN HOÀN TRẢ TÀI KHOẢN THUÊ', $this->returnBody()),
            'dispute_minutes' => $this->template('Biên bản tiếp nhận tranh chấp', 'dispute_minutes', 'BIÊN BẢN TIẾP NHẬN TRANH CHẤP', $this->disputeBody()),
            'dispute_resolution' => $this->template('Biên bản xử lý tranh chấp', 'dispute_resolution', 'BIÊN BẢN XỬ LÝ TRANH CHẤP', $this->disputeResolutionBody()),
            'refund_settlement' => $this->template('Biên bản hoàn tiền và đối soát', 'refund_settlement', 'BIÊN BẢN HOÀN TIỀN VÀ ĐỐI SOÁT', $this->refundBody()),
            'completion_minutes' => $this->template('Biên bản hoàn tất giao dịch', 'completion_minutes', 'BIÊN BẢN HOÀN TẤT GIAO DỊCH', $this->completionBody()),
            'security_checklist' => $this->template('Phiếu kiểm tra bảo mật khi bàn giao', 'security_checklist', 'PHIẾU KIỂM TRA BẢO MẬT TÀI KHOẢN', $this->securityBody()),
            'platform_transaction_record' => $this->template('Phiếu ghi nhận giao dịch trên nền tảng', 'platform_transaction_record', 'PHIẾU GHI NHẬN GIAO DỊCH TRÊN NỀN TẢNG', $this->platformBody()),
        ];
    }

    private function template(string $name, string $type, string $title, string $body): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'description' => 'Mẫu nghiệp vụ phiên bản 3, có đầy đủ thông tin các bên, đối tượng, giá trị, nghĩa vụ, phân bổ rủi ro, miễn trừ có giới hạn, thay đổi pháp luật/nhà phát hành, bảo mật, tranh chấp và xác nhận điện tử.',
            'content_html' => $this->css().$this->header($title).$this->operator().$body.$this->legalNotice().$this->signatures(),
        ];
    }

    private function css(): string
    {
        return '<style>@page{margin:24mm 18mm 22mm}body{font-family:"DejaVu Sans",sans-serif;color:#17131b;font-size:11.2px;line-height:1.58}h1{text-align:center;font-size:18px;margin:0 0 5px}h2{text-align:center;font-size:11px;margin:0 0 18px;font-weight:normal;color:#554b5c}h3{font-size:12px;margin:16px 0 7px;text-transform:uppercase}h4{font-size:11px;margin:12px 0 6px}table{width:100%;border-collapse:collapse;margin:7px 0 12px}td,th{border:1px solid #6d6174;padding:6px;vertical-align:top}.label{width:31%;font-weight:bold;background:#f2edf5}.center{text-align:center}.right{text-align:right}.notice{padding:8px;border:1px solid #8f67a7;background:#faf6fc;margin:8px 0}.warning{padding:8px;border:1px solid #a66b39;background:#fff8ef;margin:8px 0}.small{font-size:9px;color:#605666}.sign{margin-top:28px;border:0}.sign td{border:0;text-align:center;width:50%;height:100px}.footer{margin-top:18px;border-top:1px solid #b5aabd;padding-top:6px;font-size:8.5px;color:#675d6c}.page-break{page-break-before:always}ol,ul{padding-left:20px;margin:6px 0}li{margin:4px 0}</style>';
    }

    private function header(string $title): string
    {
        return '<h1>'.$title.'</h1><h2>Mã giao dịch: {{transaction_code}} · Ngày lập: {{document_date}} {{document_time}} · Phiên bản chính sách: {{policy_version}}</h2>';
    }

    private function operator(): string
    {
        return '<table><tr><td class="label">Đơn vị vận hành nền tảng</td><td><b>{{operator_name}}</b><br>Mã số thuế: {{operator_tax_code}}<br>Địa chỉ: {{operator_address}}<br>Hỗ trợ: {{operator_support_phone}} · {{operator_support_email}}<br>Website: {{operator_website}}</td></tr></table>';
    }

    private function parties(): string
    {
        return '<h3>I. Thông tin các bên</h3><table><tr><td class="label">Bên mua / bên thuê</td><td><b>{{buyer_name}}</b> ({{buyer_code}})<br>Điện thoại: {{buyer_phone}}<br>Thư điện tử: {{buyer_email}}</td></tr><tr><td class="label">Bên bán / bên cho thuê</td><td><b>{{seller_name}}</b> ({{seller_code}})<br>Điện thoại: {{seller_phone}}<br>Thư điện tử: {{seller_email}}</td></tr></table>';
    }

    private function account(): string
    {
        return '<h3>II. Đối tượng giao dịch</h3><table><tr><td class="label">Tin đăng</td><td>{{listing_code}} — {{listing_title}} ({{listing_type}})</td></tr><tr><td class="label">Tài khoản trò chơi</td><td>{{product_name}} — {{product_code}}</td></tr><tr><td class="label">Trò chơi / máy chủ / cấp độ</td><td>{{game_code}} / {{server_name}} / {{level}}</td></tr><tr><td class="label">Thuộc tính đã công bố</td><td>{{product_attributes}}</td></tr><tr><td class="label">Tình trạng bảo mật đã khai báo</td><td>{{product_security_state}}</td></tr></table>';
    }

    private function money(): string
    {
        return '<h3>III. Giá trị và thanh toán</h3><table><tr><td class="label">Giá trị giao dịch</td><td class="right">{{transaction_value}}</td></tr><tr><td class="label">Phí dịch vụ</td><td class="right">{{service_fee}}</td></tr><tr><td class="label">Giảm giá</td><td class="right">{{discount}}</td></tr><tr><td class="label">Tiền cọc</td><td class="right">{{deposit_amount}}</td></tr><tr><td class="label">Tổng phải thanh toán</td><td class="right"><b>{{total_payable}}</b></td></tr><tr><td class="label">Đã thanh toán</td><td class="right">{{paid_amount}}</td></tr><tr><td class="label">Còn lại</td><td class="right">{{remaining_amount}}</td></tr><tr><td class="label">Phương thức / hình thức</td><td>{{payment_method}} / {{purchase_mode}}</td></tr></table><h4>Lịch và trạng thái thanh toán</h4>{{payment_schedule}}';
    }

    private function commonTerms(): string
    {
        return '<h3>ĐIỀU KHOẢN CHUNG BỔ SUNG</h3><h4>1. Quyền và nghĩa vụ chung của các bên</h4><p>Các bên có quyền được cung cấp thông tin trung thực, được kiểm tra dữ liệu giao dịch, được khiếu nại và yêu cầu xử lý theo quy trình đã công bố; đồng thời có nghĩa vụ cung cấp thông tin chính xác, bảo vệ thông tin xác thực, hợp tác đối soát và thực hiện đúng các mốc thanh toán, bàn giao, hoàn trả đã xác nhận.</p><h4>2. Phân bổ rủi ro do hành vi của người sử dụng</h4><ol><li>Mỗi bên tự chịu trách nhiệm đối với thiệt hại phát sinh trực tiếp từ hành vi vi phạm điều khoản giao dịch, cung cấp thông tin sai, chia sẻ thông tin xác thực, sử dụng phần mềm gian lận, can thiệp trái phép, chuyển vật phẩm, thay đổi bảo mật hoặc cho bên thứ ba sử dụng ngoài phạm vi đã thỏa thuận.</li><li>Bên mua hoặc thuê chịu rủi ro đối với hành vi sử dụng tài khoản sau thời điểm nhận bàn giao, trừ trường hợp có bằng chứng cho thấy thiệt hại bắt nguồn từ thông tin sai, hành vi che giấu hoặc việc thu hồi trái cam kết của bên bán/cho thuê.</li><li>Bên bán hoặc cho thuê chịu trách nhiệm về quyền kiểm soát hợp pháp, nguồn gốc, mô tả, lịch sử xử phạt và các liên kết bảo mật đã biết nhưng không công bố.</li></ol><h4>3. Rủi ro từ nhà phát hành và hệ sinh thái trò chơi</h4><ol><li>Các bên hiểu rằng tài khoản, vật phẩm và tiền tệ ảo có thể chỉ là quyền sử dụng theo điều khoản của nhà phát hành; việc mua bán, chia sẻ hoặc cho thuê có thể bị hạn chế, vô hiệu hóa, khóa hoặc thu hồi.</li><li>Nền tảng không đại diện, không kiểm soát và không thể buộc nhà phát hành khôi phục tài khoản, vật phẩm, giá trị ảo hoặc công nhận giao dịch giữa người dùng.</li><li>Khi nhà phát hành thay đổi điều khoản, máy chủ, cơ chế bảo mật, giá trị vật phẩm hoặc chính sách xử phạt, các bên phải phối hợp đánh giá ảnh hưởng; nền tảng có thể tạm dừng danh mục, giao dịch hoặc bàn giao để giảm thiểu thiệt hại.</li></ol><h4>4. Rủi ro pháp lý và thay đổi quy định</h4><ol><li>Giao dịch chỉ được tiếp tục trong phạm vi pháp luật cho phép. Khi có quy định mới, yêu cầu của cơ quan có thẩm quyền hoặc dấu hiệu giao dịch bị cấm/hạn chế, nền tảng có quyền tạm dừng, yêu cầu bổ sung thông tin, hủy hoặc điều chỉnh quy trình.</li><li>Việc tạm dừng hoặc điều chỉnh phải được thông báo, ghi nhận lý do và xử lý tiền/tài sản theo trạng thái thực tế, quyền lợi hợp pháp của các bên và quy định bắt buộc áp dụng.</li><li>Không điều khoản nào trong tài liệu này được hiểu là loại trừ quyền khiếu nại, yêu cầu bồi thường hoặc quyền khác mà pháp luật không cho phép từ bỏ.</li></ol><h4>5. Giới hạn trách nhiệm hợp lý của nền tảng</h4><ol><li>Nền tảng chịu trách nhiệm đối với phần dịch vụ do mình trực tiếp cung cấp, bao gồm ghi nhận giao dịch, lưu vết xác nhận, đối soát trong phạm vi dữ liệu nhận được và thực hiện quy trình hỗ trợ đã công bố.</li><li>Nền tảng không chịu trách nhiệm cho thiệt hại gián tiếp, mất lợi nhuận kỳ vọng, thay đổi giá trị vật phẩm ảo, lỗi trò chơi, gián đoạn máy chủ hoặc quyết định độc lập của nhà phát hành, trừ trường hợp thiệt hại phát sinh do lỗi cố ý, lỗi nghiêm trọng hoặc nghĩa vụ bắt buộc của nền tảng theo pháp luật.</li><li>Miễn trừ không áp dụng đối với hành vi gian dối, cố ý che giấu, vi phạm bảo mật, xử lý dữ liệu trái quy định hoặc nghĩa vụ bồi thường bắt buộc.</li></ol><h4>6. Bất khả kháng và gián đoạn dịch vụ</h4><ol><li>Bất khả kháng hoặc sự kiện ngoài khả năng kiểm soát hợp lý có thể gồm thiên tai, dịch bệnh, mất điện/viễn thông diện rộng, tấn công mạng, gián đoạn ngân hàng, sự cố nhà phát hành hoặc quyết định của cơ quan nhà nước.</li><li>Bên bị ảnh hưởng phải thông báo sớm, cung cấp bằng chứng hợp lý và thực hiện biện pháp giảm thiểu. Nghĩa vụ thanh toán đã hoàn tất hoặc nghĩa vụ bảo mật không tự động được xóa bỏ.</li></ol><h4>7. Sửa đổi điều khoản và phiên bản áp dụng</h4><ol><li>Mọi sửa đổi phải có số phiên bản, ngày hiệu lực và nội dung thay đổi. Phiên bản áp dụng cho giao dịch là phiên bản đã hiển thị và được các bên xác nhận tại thời điểm tạo giao dịch, trừ thay đổi bắt buộc theo pháp luật.</li><li>Thay đổi làm tăng nghĩa vụ hoặc giảm quyền lợi của khách hàng không được áp dụng hồi tố nếu chưa có sự đồng ý hợp lệ, trừ trường hợp pháp luật bắt buộc.</li><li>Khi điều khoản nhà phát hành hoặc pháp luật thay đổi giữa chừng, nền tảng phải thông báo và đưa ra lựa chọn xử lý phù hợp như tiếp tục có điều chỉnh, tạm dừng, hoàn tiền/đối soát hoặc chấm dứt.</li></ol><h4>8. Khiếu nại và tranh chấp</h4><ol><li>Khi có sai lệch, bên phát hiện phải dừng thao tác có thể làm mất bằng chứng, lưu ảnh/video/biên nhận và mở yêu cầu trên hệ thống.</li><li>Nền tảng có thể tạm giữ trạng thái thanh toán, hoàn tiền hoặc hoàn cọc trong thời gian xác minh.</li><li>Kết quả xử lý căn cứ tin đăng, lịch sử hệ thống, chứng từ thanh toán, bằng chứng bàn giao và điều khoản đã xác nhận.</li><li>Nếu không đạt thỏa thuận, các bên có quyền lựa chọn cơ chế giải quyết phù hợp theo pháp luật áp dụng.</li></ol>';
    }

    private function saleBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Bàn giao và chuyển quyền sử dụng</h3><ol><li>Bên bán bàn giao đúng tài khoản, đúng tình trạng và đúng thời hạn được ghi trong giao dịch.</li><li>Bên mua kiểm tra đăng nhập, nhân vật, vật phẩm, máy chủ, liên kết và khả năng thay đổi bảo mật trước khi xác nhận nhận tài khoản.</li><li>Việc xác nhận hoàn tất không loại trừ trách nhiệm đối với hành vi gian dối, che giấu thông tin hoặc thu hồi tài khoản có bằng chứng.</li></ol>'.$this->commonTerms();
    }

    private function rentalBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Thời hạn và phạm vi thuê</h3><table><tr><td class="label">Bắt đầu</td><td>{{rental_start}}</td></tr><tr><td class="label">Kết thúc</td><td>{{rental_end}}</td></tr></table><ol><li>Người thuê không được đổi bảo mật, chuyển vật phẩm, xóa nhân vật, cho thuê lại, chia sẻ cho người khác hoặc dùng phần mềm gian lận.</li><li>Người thuê phải hoàn trả đúng hạn và giữ nguyên hiện trạng, trừ thay đổi được chấp thuận trước.</li><li>Bên cho thuê phải duy trì khả năng truy cập hợp pháp trong suốt kỳ thuê và không tự ý thu hồi khi người thuê không vi phạm.</li><li>Tiền cọc chỉ được khấu trừ khi có lý do, giá trị và bằng chứng được ghi nhận.</li></ol>'.$this->commonTerms();
    }

    private function installmentBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Kế hoạch trả góp</h3><table><tr><td class="label">Khoản thanh toán ban đầu</td><td>{{initial_payment_amount}}</td></tr><tr><td class="label">Số kỳ</td><td>{{installment_count}}</td></tr><tr><td class="label">Hạn cuối</td><td>{{due_date}}</td></tr></table><ol><li>Mỗi khoản chỉ được công nhận sau khi đối soát.</li><li>Gia hạn hoặc thay đổi lịch phải được ghi nhận trước ngày đến hạn.</li><li>Hậu quả quá hạn, hoàn hoặc khấu trừ chỉ áp dụng theo điều khoản đã hiển thị và được xác nhận trước giao dịch.</li><li>Nếu tài khoản được bàn giao có kiểm soát trước khi thanh toán đủ, bên mua không được chuyển nhượng hoặc thay đổi bảo mật trái thỏa thuận.</li></ol>'.$this->commonTerms();
    }

    private function depositBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Điều kiện đặt cọc</h3><ol><li>Khoản cọc có hiệu lực sau khi được đối soát và được tính vào tổng nghĩa vụ thanh toán, trừ khi giao dịch công bố rõ khác.</li><li>Tin đăng được giữ trong thời hạn đến {{due_date}}; mọi gia hạn phải được ghi nhận trên hệ thống.</li><li>Nếu bên bán không thể bàn giao đúng cam kết, khoản cọc được xử lý theo điều khoản và kết quả đối soát.</li><li>Nếu bên mua hủy hoặc quá hạn, việc hoàn hoặc khấu trừ phải căn cứ điều khoản đã xác nhận và chi phí thực tế có chứng cứ.</li></ol>'.$this->commonTerms();
    }

    private function paymentBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Xác nhận đối soát</h3><p>Tài liệu này ghi nhận các khoản thanh toán đã được hệ thống tiếp nhận và trạng thái đối soát tại thời điểm phát hành. Tài liệu không thay thế chứng từ của ngân hàng hoặc đơn vị trung gian thanh toán.</p>'.$this->commonTerms();
    }

    private function handoverBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Thông tin bàn giao</h3><table><tr><td class="label">Thời điểm bàn giao</td><td>{{handover_time}}</td></tr><tr><td class="label">Tình trạng bảo mật</td><td>{{product_security_state}}</td></tr><tr><td class="label">Các mốc xác nhận</td><td>{{checkpoint_summary}}</td></tr><tr><td class="label">Ghi chú</td><td>{{note}}</td></tr></table><ol><li>Bên giao xác nhận đã cung cấp thông tin trong phạm vi thỏa thuận.</li><li>Bên nhận phải kiểm tra đăng nhập, nhân vật, vật phẩm và liên kết bảo mật trước khi xác nhận.</li><li>Nếu có sai lệch, bên nhận không xác nhận hoàn tất và phải mở yêu cầu tranh chấp.</li></ol>'.$this->commonTerms();
    }

    private function returnBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Đối soát hoàn trả</h3><table><tr><td class="label">Thời điểm hoàn trả</td><td>{{return_time}}</td></tr><tr><td class="label">Tiền cọc</td><td>{{deposit_amount}}</td></tr><tr><td class="label">Các mốc xác nhận</td><td>{{checkpoint_summary}}</td></tr><tr><td class="label">Trạng thái</td><td>{{status}}</td></tr></table><ol><li>Bên cho thuê kiểm tra hiện trạng theo bản ghi bàn giao.</li><li>Mọi khấu trừ cọc phải ghi rõ lý do, số tiền và bằng chứng.</li><li>Sau khi hai bên xác nhận, giao dịch chuyển sang hoàn tất đối soát.</li></ol>'.$this->commonTerms();
    }

    private function disputeBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Nội dung tiếp nhận</h3><table><tr><td class="label">Lý do</td><td>{{dispute_reason}}</td></tr><tr><td class="label">Mô tả</td><td>{{dispute_description}}</td></tr><tr><td class="label">Trạng thái</td><td>{{status}}</td></tr></table><ol><li>Giao dịch được bảo toàn trong thời gian đối chiếu.</li><li>Hai bên cung cấp bằng chứng theo yêu cầu và không tự ý thay đổi hiện trạng làm mất dữ liệu.</li><li>Biên bản này chỉ ghi nhận tiếp nhận, chưa phải kết luận cuối cùng.</li></ol>'.$this->commonTerms();
    }

    private function disputeResolutionBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Kết quả xử lý</h3><table><tr><td class="label">Lý do tranh chấp</td><td>{{dispute_reason}}</td></tr><tr><td class="label">Kết luận / phương án</td><td>{{dispute_resolution}}</td></tr><tr><td class="label">Thời điểm xử lý</td><td>{{dispute_resolved_at}}</td></tr></table><p>Các bên có trách nhiệm thực hiện phương án đã ghi nhận. Quyền yêu cầu xem xét lại hoặc sử dụng cơ chế pháp lý khác được bảo lưu theo quy định áp dụng.</p>'.$this->commonTerms();
    }

    private function refundBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Nội dung hoàn tiền</h3><table><tr><td class="label">Số tiền hoàn</td><td>{{refunded_amount}}</td></tr><tr><td class="label">Lý do</td><td>{{refund_reason}}</td></tr><tr><td class="label">Trạng thái giao dịch</td><td>{{status}}</td></tr></table><ol><li>Số tiền hoàn được đối chiếu với lịch sử thanh toán và khoản khấu trừ hợp lệ nếu có.</li><li>Thời gian tiền về phụ thuộc phương thức thanh toán và đơn vị trung gian.</li><li>Tài liệu này phải được lưu cùng chứng từ hoàn tiền thực tế.</li></ol>'.$this->commonTerms();
    }

    private function completionBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Xác nhận hoàn tất</h3><table><tr><td class="label">Thời điểm hoàn tất</td><td>{{completed_at}}</td></tr><tr><td class="label">Trạng thái cuối</td><td>{{status}}</td></tr><tr><td class="label">Các mốc xác nhận</td><td>{{checkpoint_summary}}</td></tr></table><p>Các bên xác nhận các nghĩa vụ chính đã được thực hiện theo dữ liệu trên hệ thống, trừ khi có tranh chấp hoặc nghĩa vụ bảo mật còn tiếp tục.</p>'.$this->commonTerms();
    }

    private function securityBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Danh sách kiểm tra bảo mật</h3><table><tr><td class="label">Tình trạng khai báo</td><td>{{product_security_state}}</td></tr><tr><td class="label">Thời điểm bàn giao</td><td>{{handover_time}}</td></tr></table><ol><li>Đăng nhập thành công trên thiết bị của bên nhận.</li><li>Đối chiếu máy chủ, nhân vật, vật phẩm và thông tin tin đăng.</li><li>Kiểm tra thư điện tử, số điện thoại, mạng xã hội, thiết bị tin cậy và xác thực hai lớp đang liên kết.</li><li>Không trao đổi OTP, mật khẩu thư điện tử, mã khôi phục hoặc cookie.</li><li>Ghi lại video hoặc ảnh liên tục tại thời điểm bàn giao.</li><li>Đăng xuất phiên lạ và thay đổi thông tin chỉ khi nhà phát hành và giao dịch cho phép.</li></ol>'.$this->commonTerms();
    }

    private function platformBody(): string
    {
        return $this->parties().$this->account().$this->money().'<h3>IV. Ghi nhận trên nền tảng</h3><table><tr><td class="label">Loại giao dịch</td><td>{{transaction_type}}</td></tr><tr><td class="label">Hình thức</td><td>{{purchase_mode}}</td></tr><tr><td class="label">Ngày giao dịch</td><td>{{transaction_date}}</td></tr><tr><td class="label">Trạng thái</td><td>{{status}}</td></tr></table><p>Phiếu này là bản ghi dữ liệu giao dịch trên hệ thống, dùng để đối chiếu với hợp đồng, thanh toán, bàn giao và tranh chấp liên quan.</p>'.$this->commonTerms();
    }

    private function legalNotice(): string
    {
        return '<div class="warning"><b>Cảnh báo rủi ro:</b> Tài khoản trò chơi có thể bị giới hạn chuyển nhượng, chia sẻ hoặc cho thuê; nhà phát hành hoặc cơ quan có thẩm quyền có thể thay đổi quy định, khóa, thu hồi hoặc yêu cầu dừng giao dịch. Miễn trừ trong tài liệu chỉ áp dụng trong giới hạn pháp luật và không loại trừ trách nhiệm do gian dối, lỗi cố ý, vi phạm bảo mật hoặc nghĩa vụ bắt buộc.</div><div class="notice"><b>Xác nhận điện tử và sửa đổi:</b> Việc xác nhận ghi nhận mã tài liệu, phiên bản, thời điểm, địa chỉ mạng và thiết bị. Mọi sửa đổi phải tạo phiên bản mới; phiên bản bất lợi không áp dụng hồi tố nếu chưa có sự đồng ý hợp lệ, trừ yêu cầu bắt buộc của pháp luật.</div>';
    }

    private function signatures(): string
    {
        return '<table class="sign"><tr><td><b>BÊN MUA / BÊN THUÊ</b><br><span class="small">Xác nhận điện tử trên MBN</span></td><td><b>BÊN BÁN / BÊN CHO THUÊ</b><br><span class="small">Xác nhận điện tử trên MBN</span></td></tr></table><div class="footer">Tài liệu được tạo từ dữ liệu hệ thống AXIRO Mini/MBN. Cần cấu hình chính xác thông tin đơn vị vận hành và được rà soát pháp lý trước khi phát hành chính thức.</div>';
    }
}
