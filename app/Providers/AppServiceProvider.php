<?php

namespace App\Providers;

use App\Custom\CustomResponse;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('apple', \SocialiteProviders\Apple\Provider::class);
        });
        $this->configureRateLimiting();
        DB::listen(function ($query) {
            Log::debug($query->sql, [
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        });
    }

    public static function getMessage($request)
    {
        $lang = $request->query('lang');
        $getLanguage = CustomResponse::getLanguage($lang);
        $language = !$getLanguage ? env('APP_TRANSLATION') : $getLanguage;
        return trans('messages.rateLimit', [], $language);
    }
    public static function arrayRateLimit(): array
    {
        return [
            "register" => env('RATELIMIT_REGISTER', 5),
            "login" => env('RATELIMIT_LOGIN', 5),
            "refresh" => env('RATELIMIT_REFRESH', 5),
            "activation" => env('RATELIMIT_ACTIVATION', 5),
            "recovery" => env('RATELIMIT_RECOVERY', 5),
            "recovery-validation" => env('RATELIMIT_RECOVERY_VALIDATION', 2),
            "recovery-password" => env('RATELIMIT_RECOVERY_PASSWORD', 2),
            "resend-activation" => env('RATELIMIT_RESEND_ACTIVATION', 5),
            "me" => env('RATELIMIT_ME', 10),
            "logout" => env('RATELIMIT_LOGOUT', 10),
            "delete" => env('RATELIMIT_DELETE', 10),
            "notifications" => env('RATELIMIT_NOTIFICATIONS', 10),
            "update" => env('RATELIMIT_UPDATE', 10),
            "change-password" => env('RATELIMIT_CHANGE_PASSWORD', 10),
            "links" => env('RATELIMIT_LINKS', 10),
            "support" => env('RATELIMIT_SUPPORT', 10),
            "category" => env('RATELIMIT_CATEGORY', 10),
            "questions" => env('RATELIMIT_QUESTIONS', 10),
            "external-info" => env('RATELIMIT_EXTERNAL_INFO', 10),
            "notification" => env('RATELIMIT_NOTIFICATION', 10),
            "notification-detail" => env('RATELIMIT_NOTIFICATION_DETAIL', 10),
            "info" => env('RATELIMIT_INFO', 5),
            "social" => env('RATELIMIT_SOCIAL', 5),
            "exam-type" => env('RATE_LIMIT_EXAM_TYPE', 10),
            "specialty" => env('RATE_LIMIT_SPECIALTY', 10),
            "year" => env('RATE_LIMIT_YEAR', 10),
            "by-year" => env('RATE_LIMIT_BY_YEAR', 10),
            "theme" => env('RATELIMIT_THEME', 5),
            "question-theme" => env('RATELIMIT_QUESTION_THEME', 5),
            "report" => env('RATELIMIT_REPORT', 5),
            "history" => env("RATELIMIT_HISTORY", 5),
            "register-ranking" => env("RATELIMIT_REGISTER_RANKING", 5),
            "list-ranking" => env("RATELIMIT_LIST_RANKING", 5),
            "history-user" => env('RATELIMIT_HISTORY_USER', 5),
            "area" => env('RATELIMIT_AREA', 5),
            "exam-status"=> env('RATELIMIT_EXAM_STATUS', 5),
            "exam"=> env('RATELIMIT_EXAM', 5),
            "exam-user"=> env('RATELIMIT_EXAM_USER', 5),
            "exam-download-summary"=> env('RATELIMIT_EXAM_DOWNLOAD_SUMMARY', 1)
        ];
    }
    public static function configureRateLimiting()
    {
        foreach (self::arrayRateLimit() as $key => $value) {
            RateLimiter::for($key, function (Request $request) use ($value) {
                return Limit::perMinute(maxAttempts: intval($value))->by($request->ip())->response(function ($request) {
                    $message = AppServiceProvider::getMessage($request);
                    return CustomResponse::responseRateLimit($message, 429);
                });
            });
        }
    }
}
