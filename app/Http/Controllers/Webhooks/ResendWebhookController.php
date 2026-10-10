<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Tickets\ReceiveSupportEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ResendWebhookRequest;
use Illuminate\Http\Response;

class ResendWebhookController extends Controller
{
    public function __invoke(ResendWebhookRequest $request, ReceiveSupportEmail $receive): Response
    {
        if ($request->isReceivedEmail()) {
            $receive->handle($request->emailId());
        }

        return response()->noContent();
    }
}
