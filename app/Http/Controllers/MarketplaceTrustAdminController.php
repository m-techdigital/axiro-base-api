<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\MarketplaceReview;
use App\Models\MarketplaceRiskFlag;
use Illuminate\Http\Request;

class MarketplaceTrustAdminController extends Controller
{
    public function reviews(Request $r)
    {
        $q = MarketplaceReview::with(['transaction:id,code', 'reviewer:id,code,name', 'reviewee:id,code,name'])->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function moderateReview(Request $r, MarketplaceReview $review)
    {
        $d = $r->validate(['status' => 'required|in:published,hidden', 'note' => 'nullable|string|max:2000']);
        $review->update(['status' => $d['status'], 'moderation_note' => $d['note'] ?? null, 'moderated_by' => user_id(), 'moderated_at' => now()]);

        return success_response($review->fresh());
    }

    public function contents(Request $r)
    {
        $q = ContentEntry::when($r->type, fn ($q, $v) => $q->where('type', $v))->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function storeContent(Request $r)
    {
        $d = $this->contentData($r);
        $item = ContentEntry::create([...$d, 'created_by' => user_id(), 'updated_by' => user_id(), 'published_at' => $d['status'] === 'published' ? now() : null]);

        return success_response($item, 'Đã tạo nội dung.', 201);
    }

    public function updateContent(Request $r, ContentEntry $contentEntry)
    {
        $d = $this->contentData($r);
        $contentEntry->update([...$d, 'version' => $contentEntry->version + 1, 'updated_by' => user_id(), 'published_at' => $d['status'] === 'published' ? ($contentEntry->published_at ?? now()) : null]);

        return success_response($contentEntry->fresh(), 'Đã cập nhật nội dung.');
    }

    private function contentData(Request $r): array
    {
        return $r->validate(['type' => 'required|in:topic,guide,policy,announcement,faq', 'slug' => 'required|string|max:180', 'title' => 'required|string|max:255', 'summary' => 'nullable|string|max:1000', 'body' => 'required|string', 'status' => 'required|in:draft,published,archived', 'requires_acceptance' => 'nullable|boolean', 'effective_at' => 'nullable|date', 'metadata' => 'nullable|array']);
    }

    public function risks(Request $r)
    {
        $q = MarketplaceRiskFlag::when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->level, fn ($q, $v) => $q->where('level', $v))->latest();

        return success_response($q->paginate(min(100, max(1, $r->integer('per_page', 20)))));
    }

    public function resolveRisk(Request $r, MarketplaceRiskFlag $riskFlag)
    {
        $d = $r->validate(['status' => 'required|in:reviewing,resolved,dismissed', 'resolution' => 'required|string|max:3000']);
        $riskFlag->update(['status' => $d['status'], 'resolution' => $d['resolution'], 'resolved_by' => user_id(), 'resolved_at' => in_array($d['status'], ['resolved', 'dismissed'], true) ? now() : null]);

        return success_response($riskFlag->fresh());
    }
}
