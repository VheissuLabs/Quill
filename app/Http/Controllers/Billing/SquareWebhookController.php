<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\RecordSquarePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SquareWebhookRequest;
use App\Models\BillingSetting;
use Illuminate\Http\Response;
use Throwable;

class SquareWebhookController extends Controller
{
    public function __invoke(SquareWebhookRequest $request, BillingSetting $billingSetting, RecordSquarePayment $recordPayment): Response
    {
        if ($request->isPaymentEvent()) {
            try {
                $recordPayment->handle($billingSetting->organization, $request->payment());
            } catch (Throwable $exception) {
                report($exception);

                return response('', 500);
            }
        }

        return response('', 200);
    }
}
