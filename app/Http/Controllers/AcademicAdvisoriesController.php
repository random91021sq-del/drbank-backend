<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\HoldMeetingSlotRequest;
use App\Http\Requests\RegisterMeetingRequest;
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
use UnexpectedValueException;

/* Controlador encargado de gestionar el proceso de asesorias académicas */
class AcademicAdvisoriesController extends Controller
{
    /* Zona horaria para las operaciones */
    private const PERU_TIMEZONE = 'America/Lima';

    /* Duración de cada asesoria */
    private const SLOT_DURATION_MINUTES = 60;

    /* Tiempo de reserva temporal al seleccionar un horario */
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

    /*
    * Lista las asesorias académicas agendadas por el estudiante autenticado.
    */
    public function listAcademicAdvisories(Request $request)
    {
        $language = $request->query('lang');

        try {
            /*
            * Obtiene el ID del cliente autenticado
            */
            $client_id = auth('sanctum')->user()->id_client;
            /*
            * Busca las asesorias pertenecientes al usuario autenticado con el doctor correspondiente
            */
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
            /*
            * Retorna la lista de asesorias académicas
            */
            return CustomResponse::responseBody($academicAdvisories, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en listado de asesorías académicas: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
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
    /*
    * Bloquea temporalmente un horario de asesoria
    */
    public function holdMeetingSlot(HoldMeetingSlotRequest $request)
    {
        $language = $request->query('lang');

        try {
            /*
            * Obtiene al usuario autenticado
            */
            $client = auth('sanctum')->user();
            /*
            * Obtiene la hora y fecha actual en zona horaria peruana
            */
            $nowPeru = CarbonImmutable::now(self::PERU_TIMEZONE);
            /*
            * Convierte la fecha y hora seleccionada por el estudiante a una zona horaria peruana
            */
            $slotStart = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $request->scheduled_at,
                self::PERU_TIMEZONE
            );
            /*
            * Calcula la hora de finalización del horario
            */
            $slotEnd = $slotStart->addMinutes(self::SLOT_DURATION_MINUTES);
            /*
            * Impide reservar horarios que ya pasaron o exactamente en el momento actual 
            */
            if ($slotStart->lessThanOrEqualTo($nowPeru)) {
                return CustomResponse::responseMessage('appointmentSlotPast',Response::HTTP_BAD_REQUEST,$language);
            }
            /*
            * Valida que el rango de horario seleccionado este 
            * dentro del rango de disponibilidad del médico
            */
            $isWithinAvailability = DB::table('doctor_availabilities')
                ->where('doctor_id', $request->doctor_id)
                ->where('day_of_week', $slotStart->dayOfWeekIso) /*Devuelve el día de la semana en formato ISO Ej: 1->Lunes*/
                ->where('is_active', 1)
                ->where('start_time', '<=', $slotStart->format('H:i:s'))
                ->where('end_time', '>=', $slotEnd->format('H:i:s'))
                ->exists();
            /*
            *  Impide reservar horarios que no estén dentro de la disponibilidad del médico
            */
            if (! $isWithinAvailability) {
                return CustomResponse::responseMessage('appointmentSlotUnavailable',Response::HTTP_BAD_REQUEST,$language);
            }
            /*
            * Se inicia una transacción para realizar de forma segura 
            * la comprobación y registro del bloqueo temporal
            */
            return DB::transaction(function () use ($client, $request, $slotStart, $slotEnd, $nowPeru, $language) {
                /*
                * Remueve cualquier bloqueo temporal que haya expirado para el mismo doctor y horario
                */
                AppointmentSlotHold::where('doctor_id', $request->doctor_id)
                    ->where('scheduled_at', $slotStart)
                    ->where('expires_at', '<=', $nowPeru)
                    ->delete();
                /*
                * Obtiene las asesorias agendadas para el mismo doctor y horario, excluyendo las canceladas
                */
                $appointments = AcademicAdvisories::where('doctor_id', $request->doctor_id)
                    ->whereBetween('scheduled_at', [$slotStart->startOfDay(), $slotStart->endOfDay()])
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->lockForUpdate() /* Bloquea la fila para evitar problemas de actualización del registro*/
                    ->get(['scheduled_at', 'duration_minutes']);
                /*
                * Valida si el horario solicitado se cruza con alguna asesoria existente
                */
                $isTaken = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) { /*Colección de citas */
                    /* Parsea la fecha y hora de la cita*/
                    $appointmentStart = CarbonImmutable::parse($appointment->scheduled_at, self::PERU_TIMEZONE);
                    /*
                    * Determina la fecha de finalización de la cita 
                    */
                    $appointmentEnd = $appointmentStart->addMinutes((int) $appointment->duration_minutes);
                    /*
                    * Verifica si el nuevo horario que el usuario 
                    * quiere reservar se cruza con una cita que ya existe
                    */
                    return $slotStart->lessThan($appointmentEnd) && $slotEnd->greaterThan($appointmentStart);
                });
                /*
                * Verifica si el horario ya fue reservado por otro estudiante
                 */
                if ($isTaken || AppointmentSlotHold::where('doctor_id', $request->doctor_id)
                    ->where('scheduled_at', $slotStart)
                    ->where('expires_at', '>', $nowPeru)
                    ->exists()) {
                    return CustomResponse::responseMessage('appointmentSlotTaken',Response::HTTP_CONFLICT,$language);
                }
                /*
                * Registra la reserva temporal del horario
                */
                $hold = AppointmentSlotHold::create([
                    'doctor_id' => $request->doctor_id,
                    'client_id' => $client->id_client,
                    'scheduled_at' => $slotStart,
                    'expires_at' => $nowPeru->addMinutes(self::HOLD_MINUTES),
                    'token' => (string) Str::uuid(),
                ]);
                /*
                * Retorna el token de la reserva temporal 
                * y la fecha de expiración
                */
                return CustomResponse::responseBody([
                    'hold_token' => $hold->token,
                    'expires_at' => $hold->expires_at->format('Y-m-d H:i:s'),
                ], Response::HTTP_CREATED);
            });
        } catch (QueryException $th) {
            Log::warning('Conflicto reservando horario de asesoría: '.$th->getMessage());

            return CustomResponse::responseMessage('appointmentSlotTaken',Response::HTTP_CONFLICT,$language);
        } catch (\Throwable $th) {
            Log::error('Error reservando horario de asesoría: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
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
    /*
    * Libera una reserva temporal de horario de asesoria
    */
    public function releaseMeetingSlot(Request $request, string $hold_token)
    {
        $language = $request->query('lang');

        /*
        * Valida que el token recibido sea un UUID válido
        */
        if (! Str::isUuid($hold_token)) {
            return CustomResponse::responseMessage(
                'invalidAppointmentHoldToken',
                Response::HTTP_BAD_REQUEST,
                    $language
            );
        }

        try {
            /*
            * Obtiene el ID del usuario autenticado
            */
            $clientId = auth('sanctum')->user()->id_client;
            /*
            * Busca la reserva temporal correspondiente al token y al usuario
            */
            AppointmentSlotHold::where('token', $hold_token)
                ->where('client_id', $clientId)
                ->delete();
            /*
            * Retorna mensaje exitoso de la liberación
            */
            return CustomResponse::responseMessage(
                'appointmentHoldReleased',
                Response::HTTP_OK,
                    $language
            );
        } catch (\Throwable $th) {
            Log::error('Error liberando reserva de horario: '.$th->getMessage());

            return CustomResponse::responseMessage(
                'serverError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                    $language
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
    /*
    * Función encargada de registrar una asesoria académica
    */
    public function registerMeeting(RegisterMeetingRequest $request)
    {
        $language = $request->query('lang');

        try {
            /*
            * Obtiene al usuario autenticado
            */
            $client = auth('sanctum')->user();
            /*
            Obtiene el correo electrónico del doctor
            */
            $doctor = Doctor::select('email')->findOrFail($request->doctor_id);
            /*
            * Convierte la fecha y hora programada a una zona horaria peruana 
            */
            $scheduledAt = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $request->scheduled_at,
                self::PERU_TIMEZONE
            );
            /*
            * Inicia la transacción en la BD
            */
            DB::beginTransaction();
            /*
            * Busca la reserva temporal correspondiente al usuario
            */
            $hold = AppointmentSlotHold::where('token', $request->hold_token)
                ->where('client_id', $client->id_client)
                ->where('doctor_id', $request->doctor_id)
                ->where('scheduled_at', $scheduledAt)
                ->where('expires_at', '>', CarbonImmutable::now(self::PERU_TIMEZONE))
                ->lockForUpdate() /*Bloquea el registro encontrado para que no se pueda modificar*/
                ->first();
            /*
            *  Valida si no existe la reserva temporal(no existe,ya expiró)
            */
            if (! $hold) {
                DB::rollBack();

                return CustomResponse::responseMessage(
                    'appointmentHoldExpired',
                    Response::HTTP_CONFLICT,
                    $language
                );
            }
            /*
            * Se registra la asesoria académica en la BD
            */
            $academicAdvisory = AcademicAdvisories::create([
                'client_id' => $client->id_client,
                'doctor_id' => $request->doctor_id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => self::SLOT_DURATION_MINUTES,
                'reason' => $request->reason,
                'status' => $request->status ?? 'pending',
                'meeting_url' => $request->meeting_url ?? null,
                'google_calendar_event_id' => $request->google_calendar_event_id ?? null,
            ]);
            /*
            * Se elimina la reserva temporal de la asesoria registrada
            */
            $hold->delete();
            DB::commit(); // Se confirma la transacción en la BD

            $messageKey = 'academicAdvisoryRegistered';

            try {
                /*
                * Se envia la información de la asesoria al webhook de Make
                */
                $response = Http::timeout(45)
                    ->post('https://hook.us2.make.com/avcqe21c17l8157shgv661dhryk2pvi4', [
                        'student_email' => $client->email,
                        'doctor_email' => $doctor->email,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'title' => $request->title,
                        'academic-advisores' => $academicAdvisory->id,
                    ]);
                //Si Make falla se genera una excepción y se captura en el catch
                $response->throw();
                //Se obtiene el mensaje de respuesta del webhook
                $message = $response->json('message')?? 'La asesoría fue registrada correctamente';
                //Verifica que el mensaje sea una cadena de texto no vacía
                if (! is_string($message) || trim($message) === '') {
                    throw new UnexpectedValueException('El webhook no devolvió un mensaje válido.');
                }
            } catch (\Throwable $webhookError) {
                Log::error('La asesoría fue registrada, pero falló la integración con Make.', [
                    'academic_advisory_id' => $academicAdvisory->id,
                    'message' => $webhookError->getMessage(),
                ]);

                $messageKey = 'academicAdvisoryIntegrationFailed';
            }
            /*
            * Retorna el mensaje de éxito o error de la integración con Make
            */
            return CustomResponse::responseMessage(
                $messageKey,
                Response::HTTP_CREATED,
                $language
            );
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::info('Error en registro de asesoría académica: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
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
    
    /*
    * Actualiza el enlace de reunión y el identificador del evento de Google Calendar de una asesoría existente.
    */
    public function updateMeeting(Request $request, int $id)
    {
        $language = $request->query('lang');

        try {
            /*
            * Busca la asesoria academica por su identificador
            */
            $academicAdvisory = AcademicAdvisories::findOrFail($id);
            /*
            * Actualiza los campos de la asesoria academica con los datos recibidos en la solicitud
            */
            $academicAdvisory->update([
                'meeting_url' => $request->meeting_url,
                'google_calendar_event_id' => $request->google_calendar_event_id,
            ]);
            /*
            * Retorna un mensaje de éxito indicando que la asesoria fue actualizada correctamente
            */
            return CustomResponse::responseMessage('meetingUpdated', Response::HTTP_OK, $language);
        } catch (ModelNotFoundException $th) {
            Log::warning('Asesoría académica no encontrada para actualización.', [
                'academic_advisory_id' => $id,
            ]);

            return CustomResponse::responseMessage(
                'academicAdvisoryNotFound',
                Response::HTTP_NOT_FOUND,
                    $language
            );
        } catch (\Throwable $th) {
            Log::info('Error en actualización de asesoría académica: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
