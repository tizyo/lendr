<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoanOffer;
use App\Models\Tenant\LoanOfferRule;
use App\Services\LoanOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanOfferController extends BaseApiController
{
    public function __construct(private LoanOfferService $service) {}

    // ─── Rules ────────────────────────────────────────────────────────────────

    public function rules(): JsonResponse
    {
        $rules = LoanOfferRule::with('loanPlan')->orderByDesc('id')->get()
            ->map(fn ($r) => $this->formatRule($r));

        return $this->success(['rules' => $rules]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'min_credit_score' => ['required', 'integer', 'min:300', 'max:850'],
            'max_credit_score' => ['required', 'integer', 'min:300', 'max:850', 'gte:min_credit_score'],
            'loan_plan_id' => ['required', 'exists:loan_plans,id'],
            'min_offered_amount' => ['required', 'numeric', 'min:1'],
            'max_offered_amount' => ['required', 'numeric', 'gte:min_offered_amount'],
            'validity_days' => ['integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],
        ]);

        $rule = LoanOfferRule::create($data);

        return $this->success(['rule' => $this->formatRule($rule->load('loanPlan'))], 'Offer rule created.', 201);
    }

    public function updateRule(Request $request, LoanOfferRule $rule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'min_credit_score' => ['sometimes', 'integer', 'min:300', 'max:850'],
            'max_credit_score' => ['sometimes', 'integer', 'min:300', 'max:850'],
            'loan_plan_id' => ['sometimes', 'exists:loan_plans,id'],
            'min_offered_amount' => ['sometimes', 'numeric', 'min:1'],
            'max_offered_amount' => ['sometimes', 'numeric'],
            'validity_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],
        ]);

        $rule->update($data);

        return $this->success(['rule' => $this->formatRule($rule->fresh()->load('loanPlan'))], 'Rule updated.');
    }

    public function destroyRule(LoanOfferRule $rule): JsonResponse
    {
        $rule->delete();

        return $this->success(null, 'Rule deleted.');
    }

    // ─── Offers ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = LoanOffer::with(['borrower', 'loanPlan'])
            ->when($request->borrower_id, fn ($q, $v) => $q->where('borrower_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            fn ($o) => $this->formatOffer($o),
        );
    }

    public function show(LoanOffer $offer): JsonResponse
    {
        return $this->success(['offer' => $this->formatOffer($offer->load(['borrower', 'loanPlan']))]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'borrower_id' => ['required', 'exists:borrowers,id'],
        ]);

        $borrower = Borrower::findOrFail($request->borrower_id);
        $offers = $this->service->generateForBorrower($borrower);

        return $this->success([
            'generated' => count($offers),
            'offers' => array_map(fn ($o) => $this->formatOffer($o), $offers),
        ], count($offers).' offer(s) generated.');
    }

    public function accept(Request $request, LoanOffer $offer): JsonResponse
    {
        try {
            $offer = $this->service->accept($offer, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['offer' => $this->formatOffer($offer)], 'Offer accepted.');
    }

    public function decline(Request $request, LoanOffer $offer): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $offer = $this->service->decline($offer, $request->reason);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['offer' => $this->formatOffer($offer)], 'Offer declined.');
    }

    public function expire(LoanOffer $offer): JsonResponse
    {
        if ($offer->status !== 'pending') {
            return $this->error('Only pending offers can be expired.', 422);
        }

        $offer->update(['status' => 'expired']);

        return $this->success(['offer' => $this->formatOffer($offer->fresh())], 'Offer expired.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatRule(LoanOfferRule $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'min_credit_score' => $r->min_credit_score,
            'max_credit_score' => $r->max_credit_score,
            'loan_plan_id' => $r->loan_plan_id,
            'loan_plan_name' => $r->loanPlan?->name,
            'min_offered_amount' => (float) $r->min_offered_amount,
            'max_offered_amount' => (float) $r->max_offered_amount,
            'validity_days' => $r->validity_days,
            'is_active' => $r->is_active,
        ];
    }

    private function formatOffer(LoanOffer $o): array
    {
        return [
            'id' => $o->id,
            'borrower_id' => $o->borrower_id,
            'borrower_name' => $o->borrower?->full_name ?? $o->borrower?->first_name,
            'loan_plan_id' => $o->loan_plan_id,
            'loan_plan_name' => $o->loanPlan?->name,
            'offered_amount' => (float) $o->offered_amount,
            'interest_rate' => (float) $o->interest_rate,
            'tenure' => $o->tenure,
            'credit_score_at_offer' => $o->credit_score_at_offer,
            'status' => $o->status,
            'expires_at' => $o->expires_at?->toDateTimeString(),
            'accepted_at' => $o->accepted_at?->toDateTimeString(),
            'declined_at' => $o->declined_at?->toDateTimeString(),
            'decline_reason' => $o->decline_reason,
            'created_at' => $o->created_at->toDateTimeString(),
        ];
    }
}
