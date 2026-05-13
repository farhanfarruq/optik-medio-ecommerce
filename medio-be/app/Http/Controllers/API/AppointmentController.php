<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\StoreBranch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * GET /api/branches
     * Daftar cabang aktif.
     */
    public function branches(): JsonResponse
    {
        $branches = StoreBranch::where('is_active', true)
            ->orderBy('city')
            ->get(['id', 'name', 'code', 'address', 'city', 'province',
                   'phone', 'maps_url', 'latitude', 'longitude', 'operating_hours',
                   'appointment_capacity']);

        return response()->json($branches);
    }

    /**
     * GET /api/branches/{id}/availability?date=YYYY-MM-DD
     * Cek ketersediaan slot untuk tanggal tertentu.
     */
    public function availability(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $branch = StoreBranch::where('is_active', true)->findOrFail($id);
        $date   = Carbon::parse($request->date);

        $available = $branch->availableCapacity($date);

        // Slot waktu yang tersedia (setiap 30 menit, 09:00-17:00)
        $bookedTimes = Appointment::where('branch_id', $id)
            ->where('appointment_date', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->pluck('appointment_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->toArray();

        $allSlots = [];
        $start = Carbon::createFromTimeString('09:00');
        $end   = Carbon::createFromTimeString('17:00');
        while ($start < $end) {
            $allSlots[] = $start->format('H:i');
            $start->addMinutes(30);
        }

        $availableSlots = array_values(array_diff($allSlots, $bookedTimes));

        return response()->json([
            'branch_id'       => $id,
            'date'            => $date->toDateString(),
            'capacity'        => $branch->appointment_capacity,
            'available'       => $available,
            'available_slots' => $availableSlots,
            'is_closed'       => $available === 0,
        ]);
    }

    /**
     * GET /api/appointments
     * Daftar appointment user yang login.
     */
    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::with('branch:id,name,address,city,phone')
            ->where('user_id', $request->user()->id)
            ->latest('appointment_date')
            ->paginate(10);

        return response()->json($appointments);
    }

    /**
     * POST /api/appointments
     * Buat appointment baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id'        => 'required|exists:store_branches,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'service_type'     => 'required|in:eye_test,pickup,fitting,consultation,lens_replacement',
            'customer_name'    => 'required|string|max:100',
            'customer_phone'   => 'required|string|max:20',
            'notes'            => 'nullable|string|max:500',
            'order_id'         => 'nullable|exists:orders,id',
        ]);

        $branch = StoreBranch::where('is_active', true)->findOrFail($request->branch_id);
        $date   = Carbon::parse($request->appointment_date);

        // Cek kapasitas
        if ($branch->availableCapacity($date) <= 0) {
            return response()->json([
                'message' => 'Slot untuk tanggal ini sudah penuh. Pilih tanggal lain.',
            ], 422);
        }

        // Cek slot waktu tidak bentrok
        $slotTaken = Appointment::where('branch_id', $request->branch_id)
            ->where('appointment_date', $date->toDateString())
            ->where('appointment_time', $request->appointment_time . ':00')
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();

        if ($slotTaken) {
            return response()->json([
                'message' => 'Slot waktu ini sudah diambil. Pilih waktu lain.',
            ], 422);
        }

        $appointment = Appointment::create([
            'appointment_number' => Appointment::generateNumber(),
            'user_id'            => $request->user()->id,
            'branch_id'          => $request->branch_id,
            'appointment_date'   => $date->toDateString(),
            'appointment_time'   => $request->appointment_time . ':00',
            'service_type'       => $request->service_type,
            'status'             => 'pending',
            'customer_name'      => $request->customer_name,
            'customer_phone'     => $request->customer_phone,
            'notes'              => $request->notes,
            'order_id'           => $request->order_id,
        ]);

        return response()->json([
            'message'     => 'Appointment berhasil dibuat!',
            'appointment' => $appointment->load('branch:id,name,address,city,phone'),
        ], 201);
    }

    /**
     * GET /api/appointments/{id}
     * Detail appointment milik user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::with('branch:id,name,address,city,phone,maps_url')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($appointment);
    }

    /**
     * DELETE /api/appointments/{id}
     * Batalkan appointment (hanya jika masih pending/confirmed).
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('user_id', $request->user()->id)->findOrFail($id);

        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            return response()->json([
                'message' => 'Appointment tidak dapat dibatalkan pada status ini.',
            ], 422);
        }

        $appointment->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->input('reason', 'Dibatalkan oleh pelanggan'),
        ]);

        return response()->json(['message' => 'Appointment berhasil dibatalkan.']);
    }

    /**
     * POST /api/prescriptions/validate
     * Validasi resep mata dan dapatkan rekomendasi lensa.
     */
    public function validatePrescription(Request $request): JsonResponse
    {
        $request->validate([
            'od'        => 'nullable|array',
            'os'        => 'nullable|array',
            'pd_single' => 'nullable|numeric',
            'pd_right'  => 'nullable|numeric',
            'pd_left'   => 'nullable|numeric',
        ]);

        $service = new \App\Services\PrescriptionValidationService();
        $result  = $service->analyze($request->all());

        return response()->json($result);
    }
}
