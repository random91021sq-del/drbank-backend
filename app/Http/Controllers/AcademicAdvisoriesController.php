<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Models\AcademicAdvisories;
use App\Models\AppointmentSlotHold;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AcademicAdvisoriesController extends Controller
{
    private const PERU_TIMEZONE = 'America/Lima';

    private const SLOT_DURATION_MINUTES = 60;

    private const HOLD_MINUTES = 5;

    /**
     * @OA\Get(
     *     path="/api/v1/student/academic-advisores",
     *     summary="Obtener listado de asesorías agendadas",
     *     tags={"Student"},
     *     description="Obtener el listado de asesorías académicas agendadas por el estudiante.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="string",
     *             example="es"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Listado de asesorías obtenido exitosamente",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="scheduled_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="Fecha y hora programada para la asesoría",
     *                     example="2026-09-15 09:00:00"
     *                 ),
     *                 @OA\Property(
     *                     property="duration_minutes",
     *                     type="integer",
     *                     description="Duración de la asesoría en minutos",
     *                     example=30
     *                 ),
     *                 @OA\Property(
     *                     property="reason",
     *                     type="string",
     *                     description="Motivo por el cual se solicitó la asesoría",
     *                     example="Tengo dificultades para comprender las arritmias cardíacas."
     *                 ),
     *                 @OA\Property(
     *                     property="meeting_url",
     *                     type="string",
     *                     nullable=true,
     *                     description="URL de la reunión virtual. Puede ser null si aún no ha sido generada.",
     *                     example=null
     *                 )
     *             ),
     *
     *             example={
     *                 {
     *                     "scheduled_at": "2026-09-15 09:00:00",
     *                     "duration_minutes": 30,
     *                     "reason": "Tengo dificultades para comprender las arritmias cardíacas.",
     *                     "meeting_url": null
     *                 }
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron asesorías",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No se encontraron registros"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el límite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Se superó el límite de peticiones"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error, inténtelo nuevamente"
     *             )
     *         )
     *     )
     * )
     */
    public function listAcademicAdvisories(Request $request)
    {
        try {
            $client_id = auth('sanctum')->user()->id_client;
            $academicAdvisories = AcademicAdvisories::where('client_id', $client_id)
                ->join('doctors', 'academic_advisories.doctor_id', '=', 'doctors.id')
                ->get([
                    'academic_advisories.scheduled_at',
                    'academic_advisories.duration_minutes',
                    'academic_advisories.reason',
                    'academic_advisories.meeting_url',
                    DB::raw('CONCAT(doctors.first_name, " ", doctors.last_name) as doctor_name'),
                    'doctors.specialty as doctor_specialty',
                ]);

            return CustomResponse::responseBody($academicAdvisories, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en listado de asesorías académicas: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/student/academic-advisores/hold",
     *     operationId="holdAcademicAdvisorySlot",
     *     summary="Reservar temporalmente un horario",
     *     tags={"Student"},
     *     description="Bloquea durante cinco minutos un horario de 60 minutos para que otro estudiante no pueda seleccionarlo.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"doctor_id", "scheduled_at"},
     *
     *             @OA\Property(property="doctor_id", type="integer", example=11),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2026-09-15 09:00:00")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Horario reservado temporalmente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="hold_token", type="string", format="uuid"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2026-09-15 08:55:00")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Horario inválido"),
     *     @OA\Response(response=401, description="Token no válido"),
     *     @OA\Response(response=409, description="Horario reservado o registrado por otro estudiante"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function holdMeetingSlot(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        try {
            $client = auth('sanctum')->user();
            $nowPeru = CarbonImmutable::now(self::PERU_TIMEZONE);
            $slotStart = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $validated['scheduled_at'],
                self::PERU_TIMEZONE
            );
            $slotEnd = $slotStart->addMinutes(self::SLOT_DURATION_MINUTES);

            if ($slotStart->lessThanOrEqualTo($nowPeru)) {
                return CustomResponse::responseBody([
                    'message' => 'El horario seleccionado ya pasó.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $isWithinAvailability = DB::table('doctor_availabilities')
                ->where('doctor_id', $validated['doctor_id'])
                ->where('day_of_week', $slotStart->dayOfWeekIso)
                ->where('is_active', 1)
                ->where('start_time', '<=', $slotStart->format('H:i:s'))
                ->where('end_time', '>=', $slotEnd->format('H:i:s'))
                ->exists();

            if (! $isWithinAvailability) {
                return CustomResponse::responseBody([
                    'message' => 'El horario seleccionado no está dentro de la disponibilidad del doctor.',
                ], Response::HTTP_BAD_REQUEST);
            }

            return DB::transaction(function () use ($client, $validated, $slotStart, $slotEnd, $nowPeru) {
                AppointmentSlotHold::where('doctor_id', $validated['doctor_id'])
                    ->where('scheduled_at', $slotStart)
                    ->where('expires_at', '<=', $nowPeru)
                    ->delete();

                $appointments = AcademicAdvisories::where('doctor_id', $validated['doctor_id'])
                    ->whereBetween('scheduled_at', [$slotStart->startOfDay(), $slotStart->endOfDay()])
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->lockForUpdate()
                    ->get(['scheduled_at', 'duration_minutes']);

                $isTaken = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) {
                    $appointmentStart = CarbonImmutable::parse($appointment->scheduled_at, self::PERU_TIMEZONE);
                    $appointmentEnd = $appointmentStart->addMinutes((int) $appointment->duration_minutes);

                    return $slotStart->lessThan($appointmentEnd) && $slotEnd->greaterThan($appointmentStart);
                });

                if ($isTaken || AppointmentSlotHold::where('doctor_id', $validated['doctor_id'])
                    ->where('scheduled_at', $slotStart)
                    ->where('expires_at', '>', $nowPeru)
                    ->exists()) {
                    return CustomResponse::responseBody([
                        'message' => 'El horario acaba de ser reservado por otro estudiante.',
                    ], Response::HTTP_CONFLICT);
                }

                $hold = AppointmentSlotHold::create([
                    'doctor_id' => $validated['doctor_id'],
                    'client_id' => $client->id_client,
                    'scheduled_at' => $slotStart,
                    'expires_at' => $nowPeru->addMinutes(self::HOLD_MINUTES),
                    'token' => (string) Str::uuid(),
                ]);

                return CustomResponse::responseBody([
                    'hold_token' => $hold->token,
                    'expires_at' => $hold->expires_at->format('Y-m-d H:i:s'),
                ], Response::HTTP_CREATED);
            });
        } catch (QueryException $th) {
            Log::warning('Conflicto reservando horario de asesoría: '.$th->getMessage());

            return CustomResponse::responseBody([
                'message' => 'El horario acaba de ser reservado por otro estudiante.',
            ], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            Log::error('Error reservando horario de asesoría: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/student/academic-advisores/hold/{hold_token}",
     *     operationId="releaseAcademicAdvisorySlot",
     *     summary="Liberar una reserva temporal",
     *     tags={"Student"},
     *     description="Libera anticipadamente el horario reservado por el estudiante autenticado. La operación es idempotente.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="hold_token",
     *         in="path",
     *         required=true,
     *         description="Token UUID de la reserva temporal",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reserva liberada o inexistente",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Reserva liberada correctamente."))
     *     ),
     *
     *     @OA\Response(response=400, description="Token inválido"),
     *     @OA\Response(response=401, description="Token de autenticación no válido"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function releaseMeetingSlot(Request $request, string $hold_token)
    {
        if (! Str::isUuid($hold_token)) {
            return CustomResponse::responseBody([
                'message' => 'El token de reserva no es válido.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $clientId = auth('sanctum')->user()->id_client;

            AppointmentSlotHold::where('token', $hold_token)
                ->where('client_id', $clientId)
                ->delete();

            return CustomResponse::responseBody([
                'message' => 'Reserva liberada correctamente.',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Error liberando reserva de horario: '.$th->getMessage());

            return CustomResponse::responseMessage(
                'serverError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $request->query('lang')
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/student/academic-advisores",
     *     operationId="registerAcademicAdvisoryMeeting",
     *     summary="Registrar una asesoría académica",
     *     tags={"Student"},
     *     description="Registra la cita del estudiante autenticado y envía la información necesaria para crear la reunión y el evento de calendario.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"hold_token", "doctor_id", "scheduled_at", "reason", "start_date", "end_date", "title"},
     *
     *             @OA\Property(property="hold_token", type="string", format="uuid", description="Token entregado al reservar temporalmente el horario."),
     *             @OA\Property(property="doctor_id", type="integer", example=11),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2026-09-15 09:00:00"),
     *             @OA\Property(property="duration_minutes", type="integer", readOnly=true, example=60),
     *             @OA\Property(property="reason", type="string", example="Necesito reforzar el tema de arritmias cardíacas."),
     *             @OA\Property(property="status", type="string", default="pending", example="pending"),
     *             @OA\Property(property="meeting_url", type="string", format="uri", nullable=true, example=null),
     *             @OA\Property(property="google_calendar_event_id", type="string", nullable=true, example=null),
     *             @OA\Property(property="start_date", type="string", format="date-time", description="Inicio enviado al servicio de calendario.", example="2026-09-15T09:00:00-05:00"),
     *             @OA\Property(property="end_date", type="string", format="date-time", description="Fin enviado al servicio de calendario.", example="2026-09-15T09:30:00-05:00"),
     *             @OA\Property(property="title", type="string", example="Asesoría académica")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Asesoría registrada exitosamente",
     *
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string", example="Reunión creada satisfactoriamente"))
     *     ),
     *
     *     @OA\Response(response=400, description="Datos inválidos"),
     *     @OA\Response(response=401, description="Token no válido"),
     *     @OA\Response(response=409, description="Reserva temporal inválida o vencida"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function registerMeeting(Request $request)
    {
        $validated = $request->validate([
            'hold_token' => ['required', 'uuid'],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'reason' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'meeting_url' => ['nullable', 'url'],
            'google_calendar_event_id' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'title' => ['required', 'string'],
        ]);

        try {
            $client = auth('sanctum')->user();
            $doctor = Doctor::select('email')->findOrFail($validated['doctor_id']);
            $scheduledAt = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $validated['scheduled_at'],
                self::PERU_TIMEZONE
            );

            DB::beginTransaction();

            $hold = AppointmentSlotHold::where('token', $validated['hold_token'])
                ->where('client_id', $client->id_client)
                ->where('doctor_id', $validated['doctor_id'])
                ->where('scheduled_at', $scheduledAt)
                ->where('expires_at', '>', CarbonImmutable::now(self::PERU_TIMEZONE))
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                DB::rollBack();

                return CustomResponse::responseBody([
                    'message' => 'La reserva temporal no existe o ya venció.',
                ], Response::HTTP_CONFLICT);
            }

            $academicAdvisory = AcademicAdvisories::create([
                'client_id' => $client->id_client,
                'doctor_id' => $validated['doctor_id'],
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => self::SLOT_DURATION_MINUTES,
                'reason' => $validated['reason'],
                'status' => $validated['status'] ?? 'pending',
                'meeting_url' => $validated['meeting_url'] ?? null,
                'google_calendar_event_id' => $validated['google_calendar_event_id'] ?? null,
            ]);

            // The record must be committed before Make calls updateMeeting from
            // another HTTP request; otherwise that connection cannot see the ID.
            $hold->delete();
            DB::commit();

            try {
                $response = Http::timeout(45)
                    ->post('https://hook.us2.make.com/avcqe21c17l8157shgv661dhryk2pvi4', [
                        'student_email' => $client->email,
                        'doctor_email' => $doctor->email,
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'],
                        'title' => $validated['title'],
                        'academic-advisores' => $academicAdvisory->id,
                    ]);

                $response->throw();
                $message = $response->json('message');

                if (! is_string($message) || trim($message) === '') {
                    throw new \RuntimeException('El webhook no devolvió un mensaje válido.');
                }
            } catch (\Throwable $webhookError) {
                Log::error('La asesoría fue registrada, pero falló la integración con Make.', [
                    'academic_advisory_id' => $academicAdvisory->id,
                    'message' => $webhookError->getMessage(),
                ]);

                $message = 'La asesoría fue registrada correctamente';
            }

            return CustomResponse::responseBody([
                'message' => $message,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::info('Error en registro de asesoría académica: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/student/academic-advisores/{id}",
     *     operationId="updateAcademicAdvisoryMeeting",
     *     summary="Actualizar los datos de reunión de una asesoría",
     *     tags={"Student"},
     *     description="Actualiza el enlace de reunión y el identificador del evento de Google Calendar de una asesoría existente.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identificador de la asesoría académica",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"meeting_url", "google_calendar_event_id"},
     *
     *             @OA\Property(property="meeting_url", type="string", format="uri", example="https://meet.google.com/abc-defg-hij"),
     *             @OA\Property(property="google_calendar_event_id", type="string", example="6dh8exampleeventid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Asesoría actualizada exitosamente",
     *
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string", example="Reunión actualizada correctamente"))
     *     ),
     *
     *     @OA\Response(response=400, description="Datos inválidos"),
     *     @OA\Response(response=401, description="Token no válido"),
     *     @OA\Response(response=404, description="Asesoría no encontrada"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function updateMeeting(Request $request, int $id)
    {
        try {
            $academicAdvisory = AcademicAdvisories::findOrFail($id);
            $academicAdvisory->update([
                'meeting_url' => $request->meeting_url,
                'google_calendar_event_id' => $request->google_calendar_event_id,
            ]);

            return CustomResponse::responseMessage('meetingUpdated', Response::HTTP_OK, $request->query('lang'));
        } catch (ModelNotFoundException $th) {
            Log::warning('Asesoría académica no encontrada para actualización.', [
                'academic_advisory_id' => $id,
            ]);

            return CustomResponse::responseBody([
                'message' => 'La asesoría académica no existe.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            Log::info('Error en actualización de asesoría académica: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }
}
