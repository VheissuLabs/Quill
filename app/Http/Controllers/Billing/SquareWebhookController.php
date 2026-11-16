<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RecordSquarePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SquareWebhookRequest;
use App\Models\BillingSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class SquareWebhookController extends Controller
{
    public function __invoke(SquareWebhookRequest $request, BillingSetting $billingSetting, RecordSquarePayment $recordPayment): Response
    {
        if ($request->isPaymentEvent()) {
            try {
                $recordPayment->handle($billingSetting->organization, $request->payment());
            } catch (Throwable $exception) {
                Log::warning('Square webhook payment could not be recorded.', [
                    'organization_id' => $billingSetting->organization_id,
                    'payment_id' => $request->payment()['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response('', 200);
    }
}
