<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\EscrowBoxCounterpartyInviteRequest;
use App\Http\Requests\Customer\EscrowBoxCounterpartyResolveRequest;
use App\Http\Requests\Customer\EscrowBoxCreateRequest;
use App\Http\Requests\Customer\EscrowBoxHandoverSubmitRequest;
use App\Http\Requests\Customer\EscrowBoxMediaRequest;
use App\Http\Requests\Customer\EscrowBoxTermsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\EscrowBox;
use App\Models\EscrowBoxMedia;
use App\Services\Marketplace\EscrowBoxMediaService;
use App\Services\Marketplace\EscrowBoxPresenter;
use App\Services\Marketplace\EscrowBoxService;
use App\Services\Marketplace\EscrowBoxTimelineService;
use Illuminate\Http\Request;

class CustomerEscrowBoxController extends Controller
{
    public function index(Request $request, EscrowBoxPresenter $presenter)
    {
        $customerId = auth('customer_api')->id();
        $query = EscrowBox::query()
            ->with(['obligations', 'handoverSteps.media', 'events' => fn ($query) => $query->latest('occurred_at')->limit(20)])
            ->where(fn ($nested) => $nested->where('party_a_customer_id', $customerId)->orWhere('party_b_customer_id', $customerId));
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $page = $query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20))));
        $page->setCollection($page->getCollection()->map(fn ($box) => $presenter->customer($box, $customerId)));

        return ApiResponse::paginated($page);
    }

    public function store(EscrowBoxCreateRequest $request, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $created = $service->create(auth('customer_api')->id(), $request->validated());

        return ApiResponse::success([
            'box' => $presenter->customer($created['box']->load(['obligations', 'handoverSteps.media', 'events']), auth('customer_api')->id()),
            'invite_token' => $created['invite_token'],
            'invite_path' => $created['invite_path'],
        ], 'Đã tạo box giao dịch trung gian.', 201);
    }

    public function preview(string $token, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        return ApiResponse::success($presenter->invitePreview($service->preview($token, auth('customer_api')->id())));
    }

    public function claim(string $token, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $box = $service->claim($token, auth('customer_api')->id());

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Bạn đã trở thành Bên B của box.');
    }

    public function previewAssignedInvite(string $token, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $invite = $service->previewAssignedInvite($token, auth('customer_api')->id());

        return ApiResponse::success($presenter->assignedInvitePreview($invite['box'], $invite['party_side']));
    }

    public function acceptAssignedInvite(string $token, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $box = $service->acceptAssignedInvite($token, auth('customer_api')->id());

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã xác nhận tham gia box theo vai trò được Admin chỉ định.');
    }

    public function show(EscrowBox $escrowBox, EscrowBoxPresenter $presenter)
    {
        $box = $escrowBox->load(['obligations', 'handoverSteps.media', 'events' => fn ($query) => $query->latest('occurred_at')->limit(100), 'transaction.payments']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()));
    }

    public function timeline(Request $request, EscrowBox $escrowBox, EscrowBoxTimelineService $timeline)
    {
        $customerId = (int) auth('customer_api')->id();

        return ApiResponse::paginated(
            $timeline->list(
                $escrowBox,
                $request->only(['page', 'per_page', 'limit', 'activity_type', 'activity_subtype']),
                'customer',
                $customerId,
            ),
        );
    }

    public function updateTerms(EscrowBoxTermsRequest $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $box = $service->updateTerms($escrowBox, auth('customer_api')->id(), $request->validated());

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã tạo phiên bản điều khoản mới.');
    }

    public function confirm(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $box = $service->confirm($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã xác nhận phiên bản điều khoản hiện tại.');
    }

    public function cancel(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $box = $service->cancel($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã hủy box.');
    }

    public function rotateInvite(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $rotated = $service->rotateCustomerInvite($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success([
            'box' => $presenter->customer($rotated['box'], auth('customer_api')->id()),
            'invite_token' => $rotated['invite_token'],
            'invite_path' => $rotated['invite_path'],
        ], 'Đã vô hiệu hóa link cũ và tạo link mới.');
    }

    public function resolveCounterparty(EscrowBoxCounterpartyResolveRequest $request, EscrowBox $escrowBox, EscrowBoxService $service)
    {
        $data = $request->validated();

        return ApiResponse::success(
            $service->resolveCounterpartyByPhone($escrowBox, auth('customer_api')->id(), (int) $data['expected_version'], $data['phone']),
            'Đã tìm thấy khách hàng phù hợp.',
        );
    }

    public function inviteCounterparty(EscrowBoxCounterpartyInviteRequest $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validated();
        $box = $service->inviteCounterpartyCandidate($escrowBox, auth('customer_api')->id(), (int) $data['expected_version'], $data['candidate_token']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã gửi lời mời trong hệ thống cho Bên B.');
    }

    public function cancelCounterpartyInvite(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $box = $service->cancelCounterpartyInvite($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã hủy lời mời. Bạn có thể mời người khác hoặc lấy link mới.');
    }

    public function acceptCounterpartyInvite(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $box = $service->acceptCounterpartyInvite($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã chấp nhận lời mời tham gia Box.');
    }

    public function cloneBox(EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $created = $service->cloneCancelled($escrowBox, auth('customer_api')->id());

        return ApiResponse::success([
            'box' => $presenter->customer($created['box']->load(['obligations', 'handoverSteps.media', 'events']), auth('customer_api')->id()),
            'invite_token' => $created['invite_token'],
            'invite_path' => $created['invite_path'],
        ], 'Đã sao chép thành box mới và phát hành link mới.', 201);
    }

    public function uploadMedia(EscrowBoxMediaRequest $request, EscrowBox $escrowBox, EscrowBoxMediaService $media, EscrowBoxPresenter $presenter)
    {
        $customerId = auth('customer_api')->id();
        $side = (int) $escrowBox->party_a_customer_id === $customerId ? 'party_a' : ((int) $escrowBox->party_b_customer_id === $customerId ? 'party_b' : null);
        abort_unless($side, 403);
        $data = $request->validated();
        $media->store($escrowBox, $customerId, $side, $data['images'], $data['handover_step_id'] ?? null);

        return ApiResponse::success($presenter->customer($escrowBox->fresh(['obligations', 'handoverSteps.media', 'events']), $customerId), 'Đã tối ưu và lưu ảnh bằng chứng.');
    }

    public function media(EscrowBox $escrowBox, EscrowBoxMedia $media, EscrowBoxMediaService $service)
    {
        $customerId = auth('customer_api')->id();
        abort_unless(in_array($customerId, [(int) $escrowBox->party_a_customer_id, (int) $escrowBox->party_b_customer_id], true), 403);
        abort_unless((int) $media->escrow_box_id === (int) $escrowBox->id, 404);

        return $service->stream($media, request()->boolean('thumbnail'));
    }

    public function confirmReceipt(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $box = $service->confirmReceipt($escrowBox, auth('customer_api')->id(), (int) $data['expected_version']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã xác nhận nhận đúng tài sản.');
    }

    public function openDispute(Request $request, EscrowBox $escrowBox, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:3000'],
            'evidence' => ['nullable', 'array'],
        ]);
        $box = $service->openDispute($escrowBox, auth('customer_api')->id(), (int) $data['expected_version'], $data);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã mở tranh chấp và khóa quyết toán.');
    }

    public function submitHandover(EscrowBoxHandoverSubmitRequest $request, EscrowBox $escrowBox, string $partySide, EscrowBoxService $service, EscrowBoxPresenter $presenter)
    {
        abort_unless(in_array($partySide, ['party_a', 'party_b'], true), 404);
        $data = $request->validated();
        $box = $service->submitHandover($escrowBox, auth('customer_api')->id(), $partySide, (int) $data['expected_version'], $data['note']);

        return ApiResponse::success($presenter->customer($box, auth('customer_api')->id()), 'Đã gửi bằng chứng bàn giao cho Admin xác minh.');
    }
}
