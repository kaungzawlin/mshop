<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\MessageCampaignController;
use App\Http\Controllers\Api\Admin\MessageLogController;
use App\Http\Controllers\Api\Admin\MessageTemplateController;
use App\Http\Controllers\Api\Admin\OpenApiController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Api\Admin\ReliabilityController;
use App\Http\Controllers\Api\Admin\ResellerController as AdminResellerController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Api\Admin\TierController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerPublicController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Finance\PaymentVerificationController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\Ops\ShipmentAssignmentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\Reseller\ListingController;
use App\Http\Controllers\Api\Reseller\SettlementController as ResellerSettlementController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\Rider\ShipmentController as RiderShipmentController;
use App\Http\Controllers\Api\RolePingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
    });
});

Route::prefix('catalog')->group(function (): void {
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{slug}', [CatalogController::class, 'show']);
    Route::get('banners', [BannerPublicController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::patch('me/locale', [LocaleController::class, 'update']);
    Route::get('me/points', [PointsController::class, 'me']);

    Route::get('cart', [CartController::class, 'show']);
    Route::patch('cart', [CartController::class, 'updateItems']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

    Route::post('checkout/quote', [CheckoutController::class, 'quote']);
    Route::post('orders', [CheckoutController::class, 'place']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);

    Route::post('refunds', [RefundController::class, 'store']);
    Route::get('refunds', [RefundController::class, 'index']);

    Route::post('reviews', [ReviewController::class, 'store']);

    Route::get('reseller/ping', RolePingController::class)->middleware('role:reseller');
    Route::middleware('role:reseller')->prefix('reseller')->group(function (): void {
        Route::get('listings', [ListingController::class, 'index']);
        Route::post('listings', [ListingController::class, 'store']);
        Route::put('listings/{product}', [ListingController::class, 'update']);
        Route::post('listings/{product}/variants/{variant}/inventory', [ListingController::class, 'adjustInventory']);
        Route::post('apply', [ListingController::class, 'apply']);
        Route::get('settlements', [ResellerSettlementController::class, 'index']);
    });

    Route::get('rider/ping', RolePingController::class)->middleware('role:rider');
    Route::middleware('role:rider')->prefix('rider')->group(function (): void {
        Route::get('shipments', [RiderShipmentController::class, 'index']);
        Route::post('shipments/{shipment}/status', [RiderShipmentController::class, 'updateStatus']);
    });

    Route::get('ops/ping', RolePingController::class)->middleware('role:ops');
    Route::middleware('role:ops')->prefix('ops')->group(function (): void {
        Route::post('shipments/{shipment}/assign', [ShipmentAssignmentController::class, 'assign']);
        Route::post('shipments/{shipment}/force-majeure', [ShipmentAssignmentController::class, 'forceMajeure']);
    });

    Route::get('finance/ping', RolePingController::class)->middleware('role:finance');
    Route::middleware('role:finance')->prefix('finance')->group(function (): void {
        Route::get('payments/pending', [PaymentVerificationController::class, 'index']);
        Route::post('payments/{payment}/verify', [PaymentVerificationController::class, 'verify']);
        Route::post('payments/{payment}/reject', [PaymentVerificationController::class, 'reject']);
        Route::post('settlements/{settlement}/pay', [AdminSettlementController::class, 'markPaid']);
    });

    Route::get('responsible/ping', RolePingController::class)->middleware('role:responsible');
    Route::get('system/ping', RolePingController::class)->middleware('role:system');

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('openapi.json', [OpenApiController::class, 'spec']);
        Route::get('docs', [OpenApiController::class, 'ui']);
        Route::apiResource('banners', BannerController::class);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers/{customer}/block', [CustomerController::class, 'block']);
        Route::post('customers/{customer}/unblock', [CustomerController::class, 'unblock']);
        Route::get('customers/{customer}/reliability', [ReliabilityController::class, 'index']);
        Route::post('customers/{customer}/reliability', [ReliabilityController::class, 'adjust']);

        Route::get('settings', [SettingsController::class, 'index']);
        Route::get('settings/{group}', [SettingsController::class, 'show']);
        Route::put('settings/{group}', [SettingsController::class, 'update']);
        Route::post('settings/{group}/test', [SettingsController::class, 'test']);

        Route::post('media', [MediaController::class, 'store']);
        Route::delete('media/{medium}', [MediaController::class, 'destroy']);

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products', AdminProductController::class);
        Route::post('products/{product}/approve', [AdminProductController::class, 'approve']);

        Route::get('resellers', [AdminResellerController::class, 'index']);
        Route::post('resellers/{reseller}/approve', [AdminResellerController::class, 'approve']);
        Route::post('resellers/{reseller}/suspend', [AdminResellerController::class, 'suspend']);

        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        Route::apiResource('tiers', TierController::class)->except(['show']);

        Route::get('refunds', [AdminRefundController::class, 'index']);
        Route::post('refunds/{refundRequest}/approve', [AdminRefundController::class, 'approve']);
        Route::post('refunds/{refundRequest}/reject', [AdminRefundController::class, 'reject']);

        Route::get('settlements', [AdminSettlementController::class, 'index']);
        Route::post('settlements/{settlement}/pay', [AdminSettlementController::class, 'markPaid']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);

        Route::get('message-templates', [MessageTemplateController::class, 'index']);
        Route::post('message-templates', [MessageTemplateController::class, 'store']);
        Route::put('message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
        Route::delete('message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);
        Route::post('message-templates/{messageTemplate}/preview', [MessageTemplateController::class, 'preview']);
        Route::post('message-templates/{messageTemplate}/sample', [MessageTemplateController::class, 'sample']);

        Route::get('message-campaigns', [MessageCampaignController::class, 'index']);
        Route::post('message-campaigns', [MessageCampaignController::class, 'store']);
        Route::get('message-campaigns/{messageCampaign}', [MessageCampaignController::class, 'show']);
        Route::post('message-campaigns/{messageCampaign}/upload', [MessageCampaignController::class, 'upload']);
        Route::post('message-campaigns/{messageCampaign}/schedule', [MessageCampaignController::class, 'schedule']);
        Route::post('message-campaigns/{messageCampaign}/cancel', [MessageCampaignController::class, 'cancel']);

        Route::get('message-logs', [MessageLogController::class, 'index']);
        Route::get('message-logs/export', [MessageLogController::class, 'export']);
    });
});
