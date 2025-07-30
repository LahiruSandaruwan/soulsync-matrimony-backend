<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripePaymentService;
use App\Services\Payment\PayPalPaymentService;
use App\Services\Payment\PayHerePaymentService;
use App\Services\Payment\WebXPayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private StripePaymentService $stripeService;
    private PayPalPaymentService $paypalService;
    private PayHerePaymentService $payhereService;
    private WebXPayPaymentService $webxpayService;

    public function __construct(
        StripePaymentService $stripeService,
        PayPalPaymentService $paypalService,
        PayHerePaymentService $payhereService,
        WebXPayPaymentService $webxpayService
    ) {
        $this->stripeService = $stripeService;
        $this->paypalService = $paypalService;
        $this->payhereService = $payhereService;
        $this->webxpayService = $webxpayService;
    }

    /**
     * Handle Stripe webhooks
     */
    public function stripe(Request $request): JsonResponse
    {
        try {
            Log::info('Stripe webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            $result = $this->stripeService->processWebhook($request);

            if ($result['success']) {
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('Stripe webhook processing failed', $result);
                return response()->json(['status' => 'error', 'message' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('Stripe webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function paypal(Request $request): JsonResponse
    {
        try {
            Log::info('PayPal webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            $result = $this->paypalService->processWebhook($request);

            if ($result['success']) {
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('PayPal webhook processing failed', $result);
                return response()->json(['status' => 'error', 'message' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('PayPal webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle PayHere webhooks
     */
    public function payhere(Request $request): JsonResponse
    {
        try {
            Log::info('PayHere webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            $result = $this->payhereService->processWebhook($request->all());

            if ($result['success']) {
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('PayHere webhook processing failed', $result);
                return response()->json(['status' => 'error', 'message' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('PayHere webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle WebXPay webhooks
     */
    public function webxpay(Request $request): JsonResponse
    {
        try {
            Log::info('WebXPay webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            $result = $this->webxpayService->processWebhook($request->all());

            if ($result['success']) {
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('WebXPay webhook processing failed', $result);
                return response()->json(['status' => 'error', 'message' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('WebXPay webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Generic webhook handler for testing
     */
    public function test(Request $request): JsonResponse
    {
        Log::info('Test webhook received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Test webhook received successfully',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Health check for webhook endpoints
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'webhooks' => [
                'stripe' => config('services.stripe.webhook_secret') ? 'configured' : 'not_configured',
                'paypal' => config('services.paypal.webhook_id') ? 'configured' : 'not_configured',
                'payhere' => config('services.payhere.secret') ? 'configured' : 'not_configured',
                'webxpay' => config('services.webxpay.secret') ? 'configured' : 'not_configured',
            ],
        ]);
    }
}
