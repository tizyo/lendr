<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\GhostUser;
use App\Models\Landlord\RepoCart;
use App\Models\Landlord\RepoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ghost-user cart for repossessed items.
 * Cart = saved items a ghost user wants to enquire about.
 * [ghost auth required for all endpoints]
 */
class CartController extends BaseApiController
{
    /**
     * GET /api/v1/public/cart
     */
    public function index(Request $request): JsonResponse
    {
        /** @var GhostUser $ghostUser */
        $ghostUser = $request->user();

        if (! $ghostUser instanceof GhostUser) {
            return $this->error('Ghost user authentication required.', 403);
        }

        $items = RepoCart::where('ghost_user_id', $ghostUser->id)
            ->with(['item.primaryImage'])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'cart_id' => $c->id,
                'notes' => $c->notes,
                'added_at' => $c->created_at->toIso8601String(),
                'item' => $c->item ? [
                    'id' => $c->item->id,
                    'title' => $c->item->title,
                    'price' => (float) $c->item->price,
                    'category' => $c->item->category,
                    'condition' => $c->item->condition,
                    'location' => $c->item->location,
                    'tenant_name' => $c->item->tenant_name,
                    'is_sold' => $c->item->is_sold,
                    'image_url' => $c->item->primaryImage?->image_url,
                ] : null,
            ]);

        return $this->success($items);
    }

    /**
     * POST /api/v1/public/cart
     * Add an item to cart (idempotent).
     */
    public function add(Request $request): JsonResponse
    {
        /** @var GhostUser $ghostUser */
        $ghostUser = $request->user();

        if (! $ghostUser instanceof GhostUser) {
            return $this->error('Ghost user authentication required.', 403);
        }

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $item = RepoItem::active()->findOrFail($data['item_id']);

        $cart = RepoCart::firstOrCreate(
            ['ghost_user_id' => $ghostUser->id, 'item_id' => $item->id],
            ['notes' => $data['notes'] ?? null],
        );

        return $this->success([
            'cart_id' => $cart->id,
            'item_id' => $item->id,
        ], 'Item added to cart.', 201);
    }

    /**
     * DELETE /api/v1/public/cart/{cartId}
     * Remove an item from cart.
     */
    public function remove(Request $request, int $cartId): JsonResponse
    {
        /** @var GhostUser $ghostUser */
        $ghostUser = $request->user();

        if (! $ghostUser instanceof GhostUser) {
            return $this->error('Ghost user authentication required.', 403);
        }

        $cart = RepoCart::where('ghost_user_id', $ghostUser->id)->findOrFail($cartId);
        $cart->delete();

        return $this->success(null, 'Item removed from cart.');
    }
}
