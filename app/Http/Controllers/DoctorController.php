<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\DoctorAvailabilityRequest;
use App\Models\AppointmentSlotHold;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DoctorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/doctors",
     *     summary="Obtener listado de doctores",
     *     tags={"Doctor"},
     *     description="Obtener el listado de doctores activos junto con su especialidad.",
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
     *         description="Listado de doctores obtenido exitosamente",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Identificador del doctor",
     *                     example=11
     *                 ),
     *                 @OA\Property(
     *                     property="doctor_name",
     *                     type="string",
     *                     description="Nombre completo del doctor",
     *                     example="Juan Pérez"
     *                 ),
     *                 @OA\Property(
     *                     property="specialty",
     *                     type="string",
     *                     description="Especialidad médica del doctor",
     *                     example="Cardiología"
     *                 )
     *             ),
     *
     *             example={
     *                 {
     *                     "doctor_name": "Juan Pérez",
     *                     "specialty": "Cardiología"
     *                 },
     *                 {
     *                     "doctor_name": "María González",
     *                     "specialty": "Neurología"
     *                 },
     *                 {
     *                     "doctor_name": "Carlos Ramírez",
     *                     "specialty": "Pediatría"
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
    public function listDoctors(Request $request)
    {
        try {
            $doctors = Doctor::where('is_active', 1)->get([
                'id',
                DB::raw('CONCAT(first_name, " ", last_name) as doctor_name'),
                'specialty',
            ]);

            return CustomResponse::responseBody($doctors, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en listado de doctores: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/doctor/{doctor}/availability",
     *     operationId="getDoctorAvailability",
     *     summary="Consultar horarios disponibles de un doctor para una fecha",
     *     tags={"Doctor"},
     *     description="Genera horarios en la zona America/Lima. Excluye citas registradas y reservas temporales activas; la respuesta no debe almacenarse en caché.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="doctor",
     *         in="path",
     *         required=true,
     *         description="Identificador del doctor",
     *
     *         @OA\Schema(type="integer", example=11)
     *     ),
     *
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Fecha seleccionada en horario de Perú.",
     *
     *         @OA\Schema(type="string", format="date", example="2026-09-11")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Disponibilidad obtenida exitosamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="doctor_id", type="integer", example=11),
     *             @OA\Property(property="date", type="string", format="date", example="2026-09-14"),
     *             @OA\Property(property="day_of_week", type="integer", description="1=lunes y 7=domingo", example=1),
     *             @OA\Property(property="day_name", type="string", example="lunes"),
     *             @OA\Property(property="timezone", type="string", example="America/Lima"),
     *             @OA\Property(property="duration_minutes", type="integer", example=30),
     *             @OA\Property(property="available", type="boolean", example=true),
     *             @OA\Property(
     *                 property="slots",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="start_time", type="string", example="09:00"),
     *                     @OA\Property(property="end_time", type="string", example="09:30"),
     *                     @OA\Property(property="scheduled_at", type="string", format="date-time", example="2026-09-14 09:00:00")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Parámetros inválidos"),
     *     @OA\Response(response=401, description="Token no válido"),
     *     @OA\Response(response=404, description="Doctor no encontrado o inactivo"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     */
    public function availability(DoctorAvailabilityRequest $request, Doctor $doctor)
    {
        try {
            if (! $doctor->is_active) {
                return CustomResponse::responseBody([
                    'message' => 'El doctor no está disponible.',
                ], Response::HTTP_NOT_FOUND);
            }

            $timezone = 'America/Lima';
            $durationMinutes = 60;
            $nowPeru = CarbonImmutable::now($timezone);
            $date = CarbonImmutable::createFromFormat(
                'Y-m-d',
                (string) $request->string('date'),
                $timezone
            )->startOfDay();

            $availabilityRanges = DB::table('doctor_availabilities')
                ->where('doctor_id', $doctor->id)
                ->where('is_active', 1)
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week');

            $appointments = DB::table('academic_advisories')
                ->where('doctor_id', $doctor->id)
                ->whereBetween('scheduled_at', [$date, $date->endOfDay()])
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->get(['scheduled_at', 'duration_minutes'])
                ->map(function ($appointment) use ($timezone) {
                    $start = CarbonImmutable::parse($appointment->scheduled_at, $timezone);

                    return [
                        'start' => $start,
                        'end' => $start->addMinutes((int) $appointment->duration_minutes),
                    ];
                });

            $activeHolds = AppointmentSlotHold::where('doctor_id', $doctor->id)
                ->whereBetween('scheduled_at', [$date, $date->endOfDay()])
                ->where('expires_at', '>', $nowPeru)
                ->get(['scheduled_at'])
                ->map(function ($hold) use ($timezone, $durationMinutes) {
                    $start = CarbonImmutable::parse($hold->scheduled_at, $timezone);

                    return [
                        'start' => $start,
                        'end' => $start->addMinutes($durationMinutes),
                    ];
                });

            $blockedSlots = $appointments->concat($activeHolds);

            $slots = [];

            foreach ($availabilityRanges->get($date->dayOfWeekIso, collect()) as $range) {
                $slotStart = $date->setTimeFromTimeString($range->start_time);
                $rangeEnd = $date->setTimeFromTimeString($range->end_time);

                while ($slotStart->addMinutes($durationMinutes)->lessThanOrEqualTo($rangeEnd)) {
                    $slotEnd = $slotStart->addMinutes($durationMinutes);
                    $isInPast = $slotStart->lessThanOrEqualTo($nowPeru);
                    $isTaken = $blockedSlots->contains(fn ($appointment) => $slotStart->lessThan($appointment['end']) && $slotEnd->greaterThan($appointment['start'])
                    );

                    if (! $isInPast && ! $isTaken) {
                        $slots[$slotStart->format('H:i')] = [
                            'start_time' => $slotStart->format('H:i'),
                            'end_time' => $slotEnd->format('H:i'),
                            'scheduled_at' => $slotStart->format('Y-m-d H:i:s'),
                        ];
                    }

                    $slotStart = $slotEnd;
                }
            }

            $slots = array_values($slots);

            return CustomResponse::responseBody([
                'doctor_id' => $doctor->id,
                'date' => $date->toDateString(),
                'day_of_week' => $date->dayOfWeekIso,
                'day_name' => $date->locale('es')->dayName,
                'timezone' => $timezone,
                'duration_minutes' => $durationMinutes,
                'available' => count($slots) > 0,
                'slots' => $slots,
            ], Response::HTTP_OK)->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
        } catch (\Throwable $th) {
            Log::error('Error consultando disponibilidad del doctor: '.$th->getMessage());

            return CustomResponse::responseMessage(
                'serverError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $request->query('lang')
            );
        }
    }
}
