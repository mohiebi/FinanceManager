<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Support\CalendarDates;
use App\Support\CurrencyPreference;
use App\Support\FrontendLocalization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the authenticated user\'s preferences and the current date: language, calendar system (gregorian or jalali), preferred currency, and today in both calendars. Call this FIRST in a conversation so you can interpret the user\'s dates and present results in their calendar. Never convert Jalali dates yourself — pass them to tools as-is; the server converts them.')]
class GetUserContextTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $today = Carbon::today();

        return Response::structured([
            'name' => $user->name,
            'locale' => $user->locale,
            'language' => FrontendLocalization::languageName($user->locale),
            'language_handling' => 'Write every reply to this user in the language named by `language`, whatever language they write in, unless they explicitly ask for another one. Category, bill, asset, and goal names are reproduced exactly as stored, never translated.',
            'calendar' => $calendar,
            'default_currency' => CurrencyPreference::resolveFor($user)->value,
            'today_gregorian' => $today->toDateString(),
            'today_jalali' => CalendarDates::toJalali($today),
            'current_month_gregorian' => [
                'from' => $today->copy()->startOfMonth()->toDateString(),
                'to' => $today->copy()->endOfMonth()->toDateString(),
            ],
            'current_month_jalali' => CalendarDates::currentJalaliMonthRange(),
            'date_handling' => 'Date arguments accept either calendar in YYYY-MM-DD form: Jalali years (1100-1599) are detected and converted server-side. When the user speaks in Jalali dates, pass them unchanged. Present dates back to the user in their preferred calendar using the *_jalali fields in tool results.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
