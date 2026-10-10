<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotFreeMailDomain implements ValidationRule
{
    private const DOMAINS = [
        'aol.com',
        'gmail.com',
        'googlemail.com',
        'gmx.com',
        'gmx.net',
        'hey.com',
        'hotmail.com',
        'icloud.com',
        'live.com',
        'mac.com',
        'mail.com',
        'me.com',
        'msn.com',
        'outlook.com',
        'proton.me',
        'protonmail.com',
        'yahoo.com',
        'yandex.com',
        'zoho.com',
    ];

    public static function isFreeMail(string $domain): bool
    {
        return in_array(strtolower($domain), self::DOMAINS, true);
    }

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isFreeMail(trim($value))) {
            $fail(__('Anyone can send from :domain, so it cannot identify a client.', ['domain' => trim($value)]));
        }
    }
}
