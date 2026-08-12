<?php

namespace App\Mail\Templates;

use App\Support\FrontendLocalization;

/**
 * The shell every subscription email is rendered into.
 *
 * One template rather than one per message: these all say the same kind of
 * thing — a headline, a sentence, and a way back to the billing page — and four
 * near-identical HTML files would drift apart the first time one was edited.
 */
class SubscriptionEmailTemplate
{
    public static function render(string $headline, string $body, string $locale, ?string $action = null): string
    {
        $direction = FrontendLocalization::direction($locale);
        $align = $direction === 'rtl' ? 'right' : 'left';
        $url = route('billing.edit');
        $year = date('Y');
        $appName = e((string) config('app.name'));

        $headline = e($headline);
        $body = e($body);

        $button = $action === null ? '' : <<<HTML
            <tr>
                <td style="padding-top:24px;">
                    <a href="{$url}" style="display:inline-block;padding:12px 22px;background:#02cd86;color:#06130d;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">{$action}</a>
                </td>
            </tr>
        HTML;

        return <<<HTML
        <!doctype html>
        <html dir="{$direction}" lang="{$locale}">
        <body style="margin:0;padding:0;background:#0b0b0b;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0b;padding:32px 16px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#141414;border-radius:18px;border:1px solid #232323;padding:32px;text-align:{$align};">
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#ffffff;padding-bottom:12px;">{$headline}</td>
                            </tr>
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#b4b4b4;">{$body}</td>
                            </tr>
                            {$button}
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6f6f6f;padding-top:28px;border-top:1px solid #232323;margin-top:24px;">&copy; {$year} {$appName}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
