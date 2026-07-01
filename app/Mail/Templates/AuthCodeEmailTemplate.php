<?php

namespace App\Mail\Templates;

use App\Support\FrontendLocalization;
use Illuminate\Support\Facades\Lang;

class AuthCodeEmailTemplate
{
    public static function subject(string $purpose, ?string $locale = null): string
    {
        $purposeKey = self::purposeKey($purpose);
        $locale = self::localeForPurpose($purposeKey, $locale);

        return self::translate("mail.auth_code.subject.{$purposeKey}", $locale);
    }

    public static function render(string $code, string $purpose, ?string $locale = null): string
    {
        $year = date('Y');
        $digits = str_split($code);
        $purposeKey = self::purposeKey($purpose);
        $locale = self::localeForPurpose($purposeKey, $locale);
        $subject = self::subject($purposeKey, $locale);
        $headline = self::translate("mail.auth_code.headline.{$purposeKey}", $locale);
        $subline = self::translate("mail.auth_code.subline.{$purposeKey}", $locale);
        $codeLabel = self::translate('mail.auth_code.code_label', $locale);
        $expiry = self::translate('mail.auth_code.expiry', $locale);
        $ignore = self::translate('mail.auth_code.ignore', $locale);
        $noChanges = self::translate('mail.auth_code.no_changes', $locale);
        $footer = self::translate('mail.auth_code.footer', $locale);
        $direction = FrontendLocalization::direction($locale);

        $cellStyle = 'width:48px; height:60px; text-align:center; vertical-align:middle; font-size:34px; font-weight:700; color:#02cd86; background-color:#0f2a1e; border-radius:10px; border:1px solid #1a4030;';
        $gapStyle = 'width:8px;';
        $sepStyle = 'width:20px; text-align:center; vertical-align:middle;';

        $codeCells = implode('', [
            "<td style=\"{$cellStyle}\">{$digits[0]}</td>",
            "<td style=\"{$gapStyle}\"></td>",
            "<td style=\"{$cellStyle}\">{$digits[1]}</td>",
            "<td style=\"{$gapStyle}\"></td>",
            "<td style=\"{$cellStyle}\">{$digits[2]}</td>",
            "<td style=\"{$sepStyle}\"><span style=\"display:block;width:6px;height:6px;background:#2a2a2a;border-radius:50%;margin:0 auto;\"></span></td>",
            "<td style=\"{$cellStyle}\">{$digits[3]}</td>",
            "<td style=\"{$gapStyle}\"></td>",
            "<td style=\"{$cellStyle}\">{$digits[4]}</td>",
            "<td style=\"{$gapStyle}\"></td>",
            "<td style=\"{$cellStyle}\">{$digits[5]}</td>",
        ]);

        $logoSvg = <<<'SVG'
        <svg width="28" height="28" viewBox="0 0 720 1079" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
            <path fill="#02cd86" d="M689.06,727.95c0,2.65-.59,5.29-1.82,7.77,0,0-.06.18-.24.53-1.29,2.53-2.65,5-4,7.47.35-.06.71-.12,1.06-.06-19.18,46.07-125.03,278.54-326.73,326.85-25.18,5.47-51.19,8.18-77.96,8.18-14.12,0-27.71-.76-40.72-2.29-14.18-1.53-27.71-4.06-40.6-7.47-.06-.06-.12-.06-.18-.06-29.95-7.88-56.19-20.53-78.78-38.07-41.25-31.95-71.43-72.96-90.49-123.09C9.53,857.63,0,805.5,0,751.37c0-70.02,10.65-140.33,31.95-210.88,8.65-28.71,18.65-56.96,30.01-84.67,0,0,0,.06.06.24.41,3.12,6.12,38.89,46.78,54.6,14.59,5.65,33.71,8.71,58.78,6.77,15.47-1.24,33.24-4.41,53.54-10.06-5.3,17.06-10.06,34.19-14.42,51.54-15.42,61.49-23.12,119.91-23.12,175.16,0,40.13,5.35,78.67,16.18,115.44,10.77,36.83,28.54,66.72,53.19,89.79,24.71,23,56.66,34.54,96.02,34.54,41.66,0,82.9-12.59,123.8-37.71,40.89-25.07,78.43-58.13,112.73-99.14,30.36-36.25,55.43-75.14,75.2-116.62,4.18-8.71,14.36-11.47,21.77-5.94,4.24,3.12,6.59,8.24,6.59,13.53Z"/>
            <path fill="#ae98f9" d="M642.1,305.1l-9.19-45.87c-.09-.59-.21-1.19-.35-1.77v-.07c-.23-.9-.5-1.78-.82-2.65-8.43-22.81-50.82-30.11-109.19-22.73-38.68,4.89-84.39,16.23-131.86,33.79-119.1,44.03-204.28,110.48-190.26,148.4l.31.8,12.63,33.31,7.75,20.43c-52.32,18.54-94.58,18.43-115.94-4.71-46.43-50.31,23.53-190.39,156.24-312.9,30.12-27.79,60.88-52.3,90.99-72.99,116.12-87.23,250.02-104.74,313.73-35.7,54.72,59.29,41.68,165.07-24.05,262.66Z"/>
            <path fill="#ae98f9" d="M615.51,333.45c-1.16-.07-2.32-.11-3.52-.18-3.55-.14-7.31-.21-11.07-.21-2.5,0-5.06.03-7.66.1-27.73.67-61.02,5.2-96.35,13.08l-1.9.46-2.32.53c-26.68,6.08-54.24,13.99-81.97,23.48-24.99,8.58-49.18,18.1-71.99,28.37-.25.1-.49.21-.74.35h-.11l-6.33,2.92c-32.41,15.04-61.09,31.28-83.8,47.45l-4.82-12.72-12.2-32.2c1.51-5.24,11.56-21.65,44.08-44.89,32.83-23.52,77.89-46.43,126.82-64.54,43.8-16.17,86.96-27.28,124.89-32.06,13.22-1.69,25.48-2.53,36.45-2.53,12.79,0,21.79,1.12,28.01,2.5,6.22,1.34,9.67,2.92,11.25,3.83l8.82,44.11,4.43,22.15Z"/>
            <path fill="#ae98f9" d="M719.65,439.69c-16.91.39-36.87,1.34-60.39,2.99h-.04c-9.49.67-19.58,1.44-30.26,2.36-8.47.7-17.33,1.51-26.61,2.39-4.25.39-8.61.81-13.08,1.27-45.55,4.53-82.39,6.47-112.23,6.61h-.04c-27.98.14-49.77-1.3-66.75-3.62-19.75-2.74-33.01-6.64-41.9-10.72-8.86-4.08-13.36-8.26-15.61-11.56-.39-.53-.67-1.02-.95-1.51,21.41-9.63,44.64-18.84,69.18-27.24,6.82-2.32,13.57-4.53,20.28-6.64,1.97-.63,3.94-1.23,5.87-1.79,9.07-2.81,18-5.38,26.75-7.7,2.88-.77,5.73-1.55,8.58-2.25,2.64-.7,5.27-1.34,7.87-1.97,2.46-.6,4.89-1.19,7.28-1.72.74-.21,1.44-.35,2.18-.53.7-.14,1.44-.32,2.18-.49,0,0,.18-.03.42-.11l.53-.1c.21-.07.42-.1.63-.14,1.65-.35,3.27-.74,4.85-1.05,2.67-.6,5.31-1.16,7.94-1.69,3.66-.74,7.24-1.41,10.79-2.08,8.58-1.58,16.87-2.92,24.92-4.01,1.9-.25,3.76-.49,5.62-.74,5.87-.74,11.6-1.34,17.12-1.79,1.48-.1,2.92-.21,4.36-.32.74-.07,1.48-.11,2.21-.14,2.39-.18,4.71-.32,6.99-.39,1.93-.1,3.83-.18,5.69-.21,5.83-.14,11.39-.11,16.63.1,20.21.84,35.92,4.43,45.55,10.86l7.98,8.05,55.43,55.89Z"/>
        </svg>
        SVG;

        $clockSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#f59e0b" stroke-width="1.5"/><path d="M12 8v4l2 2" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round"/></svg>';

        $footerLogoSvg = '<svg width="20" height="20" viewBox="0 0 720 1079" xmlns="http://www.w3.org/2000/svg" style="opacity:0.2;"><path fill="#02cd86" d="M689.06,727.95c0,2.65-.59,5.29-1.82,7.77,0,0-.06.18-.24.53-1.29,2.53-2.65,5-4,7.47.35-.06.71-.12,1.06-.06-19.18,46.07-125.03,278.54-326.73,326.85-25.18,5.47-51.19,8.18-77.96,8.18-14.12,0-27.71-.76-40.72-2.29-14.18-1.53-27.71-4.06-40.6-7.47-.06-.06-.12-.06-.18-.06-29.95-7.88-56.19-20.53-78.78-38.07-41.25-31.95-71.43-72.96-90.49-123.09C9.53,857.63,0,805.5,0,751.37c0-70.02,10.65-140.33,31.95-210.88,8.65-28.71,18.65-56.96,30.01-84.67,0,0,0,.06.06.24.41,3.12,6.12,38.89,46.78,54.6,14.59,5.65,33.71,8.71,58.78,6.77,15.47-1.24,33.24-4.41,53.54-10.06-5.3,17.06-10.06,34.19-14.42,51.54-15.42,61.49-23.12,119.91-23.12,175.16,0,40.13,5.35,78.67,16.18,115.44,10.77,36.83,28.54,66.72,53.19,89.79,24.71,23,56.66,34.54,96.02,34.54,41.66,0,82.9-12.59,123.8-37.71,40.89-25.07,78.43-58.13,112.73-99.14,30.36-36.25,55.43-75.14,75.2-116.62,4.18-8.71,14.36-11.47,21.77-5.94,4.24,3.12,6.59,8.24,6.59,13.53Z"/></svg>';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}" dir="{$direction}">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="x-apple-disable-message-reformatting" />
            <title>{$subject}</title>
            <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { background-color: #0d0d0d; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
                table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
                @media only screen and (max-width: 560px) {
                    .email-card { width: 100% !important; border-radius: 0 !important; }
                    .email-body { padding: 36px 24px !important; }
                    .email-footer { padding: 20px 24px !important; }
                    .code-cell { font-size: 26px !important; width: 38px !important; height: 50px !important; }
                }
            </style>
        </head>
        <body style="background-color:transparent; font-family:'IBM Plex Sans',ui-sans-serif,system-ui,-apple-system,sans-serif; margin:0; padding:0; direction:{$direction};">

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:transparent;">
                <tr>
                    <td align="center" style="padding:48px 16px;">

                        <!-- Card -->
                        <table class="email-card" width="520" cellpadding="0" cellspacing="0" role="presentation"
                            style="background-color:#111111; border:1px solid #242424; border-radius:20px; overflow:hidden; max-width:520px; width:100%;">

                            <!-- Gradient accent bar -->
                            <tr>
                                <td style="background:linear-gradient(90deg,#02cd86 0%,#ae98f9 100%); height:3px; font-size:0; line-height:0;">&nbsp;</td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td class="email-body" style="padding:48px 48px 40px;">

                                    <!-- Logo -->
                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                        <tr>
                                            <td style="padding-bottom:44px;">
                                                <table cellpadding="0" cellspacing="0" role="presentation">
                                                    <tr>
                                                        <td style="vertical-align:middle;">{$logoSvg}</td>
                                                        <td style="padding-left:10px; vertical-align:middle;">
                                                            <span style="font-size:17px; font-weight:600; color:#f8fafc; letter-spacing:-0.3px;">CashPilot</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Headline -->
                                    <p style="font-size:11px; font-weight:500; letter-spacing:2px; color:#02cd86; text-transform:uppercase; margin:0 0 8px;">{$headline}</p>
                                    <h1 style="font-size:26px; font-weight:600; color:#f8fafc; letter-spacing:-0.5px; line-height:1.3; margin:0 0 12px;">{$subject}</h1>
                                    <p style="font-size:15px; font-weight:300; color:#a1a1aa; line-height:1.7; margin:0 0 40px; max-width:380px;">{$subline}</p>

                                    <!-- Label -->
                                    <p style="font-size:11px; font-weight:500; letter-spacing:1.5px; color:#71717a; text-transform:uppercase; margin:0 0 10px; text-align:center;">{$codeLabel}</p>

                                    <!-- Code cells -->
                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:32px;">
                                        <tr>
                                            <td align="center">
                                                <table cellpadding="0" cellspacing="0" role="presentation" style="background-color:#1a1a1a; border:1px solid #242424; border-radius:14px; padding:20px 24px;">
                                                    <tr>{$codeCells}</tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Expiry warning -->
                                    <table cellpadding="0" cellspacing="0" role="presentation" style="background-color:#1c1400; border:1px solid #2d2200; border-radius:10px; padding:12px 16px; margin-bottom:40px; width:100%;">
                                        <tr>
                                            <td style="width:20px; vertical-align:top; padding-top:2px;">{$clockSvg}</td>
                                            <td style="padding-left:8px;">
                                                <p style="font-size:13px; color:#d97706; margin:0; line-height:1.55;">
                                                    {$expiry}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Disclaimer -->
                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                        <tr>
                                            <td style="border-top:1px solid #1f1f1f; padding-top:32px;">
                                                <p style="font-size:12px; color:#71717a; line-height:1.7; margin:0;">
                                                    {$ignore}<br/>{$noChanges}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td class="email-footer" style="background-color:#0d0d0d; border-top:1px solid #242424; padding:20px 48px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                        <tr>
                                            <td>
                                                <p style="font-size:11px; color:#71717a; margin:0; line-height:1.7;">
                                                    &copy; {$year} CashPilot &mdash; {$footer}<br/>
                                                    <a href="https://cashpilot.mohiebi.com" style="color:#52525b; text-decoration:none;">cashpilot.mohiebi.com</a>
                                                </p>
                                            </td>
                                            <td align="right" style="vertical-align:middle;">{$footerLogoSvg}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </body>
        </html>
        HTML;
    }

    private static function purposeKey(string $purpose): string
    {
        return $purpose === 'recovery' ? 'recovery' : 'signup';
    }

    private static function localeForPurpose(string $purposeKey, ?string $locale): string
    {
        if ($purposeKey === 'signup') {
            return FrontendLocalization::DEFAULT_LOCALE;
        }

        return FrontendLocalization::normalizeLocale($locale);
    }

    private static function translate(string $key, string $locale): string
    {
        return Lang::get($key, [], $locale);
    }
}
