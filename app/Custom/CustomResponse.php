<?php

namespace App\Custom;

use App\Mail\ActivationProfileMail;
use App\Mail\DownloadExamSummaryMail;
use App\Mail\RecoveryPasswordMail;
use App\Mail\SupportPageMail;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class CustomResponse
{
    public static function getLanguage($lang)
    {
        $path = lang_path('languages.json');
        $jsonContent = file_get_contents($path);
        $jsonLanguages = json_decode($jsonContent, true);
        foreach ($jsonLanguages as $value) {
            if ($value == $lang) {
                return $value;
            }
        }

        return null;
    }

    public static function responseMessage($message, $status, $lang): JsonResponse
    {
        $getLanguage = CustomResponse::getLanguage($lang);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;

        $response = trans('messages.'.$message, [], $language);

        return response()->json(['message' => $response], $status);
    }

    public static function responseBody($body, $status)
    {
        return response()->json($body, $status);
    }

    public static function responseValidation($message, $lang)
    {

        $getLanguage = CustomResponse::getLanguage($lang);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;

        return trans('messages.'.$message, [], $language);
    }

    public static function responseNotActive($message, $lang)
    {
        $getLanguage = CustomResponse::getLanguage($lang);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;

        $message = trans('messages.'.$message, [], $language);
        throw new HttpResponseException(response()->json(['code' => env('CODE_NOT_ACTIVE'), 'message' => $message], Response::HTTP_BAD_REQUEST));
    }

    public static function responseBadWord($messages, $status, $lang)
    {
        $getLanguage = CustomResponse::getLanguage($lang);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;

        $errors = [];
        foreach ($messages as $value) {
            $errors[$value] = trans('messages.'.$value, [], $language);
        }

        $response = [
            'errors' => $errors,
        ];

        return response()->json($response, $status);
    }

    public static function responseDefault($body = null, $code = null)
    {
        foreach ($body as $key => $value) {
            $response[$key] = $value;
        }

        return response()->json($response, $code);
    }

    public static function responseRateLimit($message, $status)
    {
        return response()->json(['message' => $message], $status);
    }

    public static function sendEmail(string $language, string $email, mixed $data, int $option):void
    {
        $getLanguage = CustomResponse::getLanguage($language);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;
        try {
            if ($option == 1) {
                $message = [
                    'codeActivate' => trans('messages.codeActivate', [], $language),
                    'hello' => trans('messages.hello', [], $language),
                    'goodDay' => trans('messages.goodDay', [], $language),
                    'drbank' => trans('messages.drbank', [], $language),
                    'messageActivation' => trans('messages.messageActivation', [], $language),
                    'drbankTeam' => trans('messages.drbankTeam', [], $language),
                    'messageTo' => trans('messages.messageTo', [], $language),
                    'messageHaveQuestion' => trans('messages.messageHaveQuestion', [], $language),
                    'reserved' => trans('messages.reserved', [], $language),
                ];
                Mail::to($email)->send(new ActivationProfileMail($data['name'], $data['last_name'], $message, $email, $data['code_activate']));
            } elseif ($option == 2) {
                $message = [
                    'recoveryEmail' => trans('messages.recoveryEmail', [], $language),
                    'hello' => trans('messages.hello', [], $language),
                    'goodDay' => trans('messages.goodDay', [], $language),
                    'newAccess' => trans('messages.newAccess', [], $language),
                    'messageExpired' => trans('messages.messageExpired', [], $language),
                    'drbank' => trans('messages.drbank', [], $language),
                    'drbankTeam' => trans('messages.drbankTeam', [], $language),
                    'messageTo' => trans('messages.messageTo', [], $language),
                    'messageHaveQuestion' => trans('messages.messageHaveQuestion', [], $language),
                    'reserved' => trans('messages.reserved', [], $language),
                ];
                Mail::to($email)->send(new RecoveryPasswordMail($email, $data['name'], $data['last_name'], $data['token'], $message));
            } elseif ($option == 3) {
                $message = [
                    'subject' => trans('messages.examSummarySubject', [], $language),
                    'hello' => trans('messages.hello', [], $language),
                    'drbank' => trans('messages.drbank', [], $language),
                    'examSummaryIntro' => trans('messages.examSummaryIntro', [], $language),
                    'examSummarySection1' => trans('messages.examSummarySection1', [], $language),
                    'examSummarySection2' => trans('messages.examSummarySection2', [], $language),
                    'drbankTeam' => trans('messages.drbankTeam', [], $language),
                    'messageTo' => trans('messages.messageTo', [], $language),
                    'messageHaveQuestion' => trans('messages.messageHaveQuestion', [], $language),
                    'reserved' => trans('messages.reserved', [], $language),
                    'name' => $data['name'] ?? '',
                ];
                Mail::to($email)->send(new DownloadExamSummaryMail($email, $data['exams'], $message));
            } elseif ($option == 4) {
                $message = [
                    'supportEmail' => trans('messages.supportEmail', [], $language),
                    'hello' => trans('messages.hello', [], $language),
                    'drbank' => trans('messages.drbank', [], $language),
                    'goodDay' => trans('messages.goodDay', [], $language),
                    'reason' => trans('messages.reason', [], $language),
                    'haveGoodDay' => trans('messages.haveGoodDay', [], $language),
                    'detailSupport' => trans('messages.detailSupport', [], $language),
                    'emailSupport' => trans('messages.emailSupport', [], $language),
                    'messageSupport' => trans('messages.messageSupport', [], $language),
                    'drbankTeam' => trans('messages.drbankTeam', [], $language),
                    'messageTo' => trans('messages.messageTo', [], $language),
                    'messageHaveQuestion' => trans('messages.messageHaveQuestion', [], $language),
                    'reserved' => trans('messages.reserved', [], $language),
                ];
                Mail::to($email)->send(new SupportPageMail($data['reason'], $data['description'], $data['full_name'], $data['email'], $message));
            }
        } catch (\Throwable $th) {
            Log::error('Error enviando correo: '.$th->getMessage());
        }
    }

    public static function failValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'errors' => collect($validator->errors()->messages())
                    ->mapWithKeys(function ($messages, $field) {
                        $cleanField = preg_replace('/\.\d+$/', '', $field);

                        return [$cleanField => $messages[0]];
                    }),
            ],
            Response::HTTP_BAD_REQUEST
        ));
    }

    public static function failValidationFirst(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'message' => collect($validator->errors()->messages())->first()[0],
            ],
            Response::HTTP_BAD_REQUEST
        ));
    }

    public static function corsResponse($message, $lang)
    {
        $getLanguage = CustomResponse::getLanguage($lang);
        $language = ! $getLanguage ? env('APP_TRANSLATION') : $getLanguage;

        $response = trans('messages.'.$message, [], $language);

        return response()->json([
            'message' => $response,
        ], Response::HTTP_FORBIDDEN);
    }

    public static function tryError($message = null)
    {
        return response()->json([
            'message' => $message,
        ], 400);
    }
}
