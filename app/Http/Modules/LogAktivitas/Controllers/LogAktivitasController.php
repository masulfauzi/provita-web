<?php

namespace App\Http\Modules\LogAktivitas\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class LogAktivitasController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'uuid'],
            'log_name' => ['nullable', 'string', 'max:50'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $items = Activity::query()
            ->with('causer')
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($filters['log_name'] ?? null, fn ($q, $log) => $q->where('log_name', $log))
            ->when($filters['dari'] ?? null, fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($filters['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'causer' => $activity->causer?->only('username', 'name'),
                'changes' => $activity->attribute_changes,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        return Inertia::render('LogAktivitas::Index', [
            'items' => $items,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
        ]);
    }
}
