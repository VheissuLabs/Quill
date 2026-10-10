<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ResendInbox
{
    public function email(string $id): InboundEmail
    {
        return new InboundEmail($this->client()->get("emails/receiving/{$id}")->throw()->json());
    }

    public function attachmentContents(string $emailId, string $attachmentId): string
    {
        $downloadUrl = $this->client()
            ->get("emails/receiving/{$emailId}/attachments/{$attachmentId}")
            ->throw()
            ->json('download_url');

        return Http::get($downloadUrl)->throw()->body();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.resend.com')
            ->withToken((string) config('services.resend.key'))
            ->acceptJson()
            ->retry(3, 500, throw: false);
    }
}
