<?php

namespace App\Http\Controllers\Api;

use App\Actions\Recurrences\CreateRecurrence;
use App\Actions\Recurrences\StopRecurrence;
use App\Actions\Recurrences\UpdateRecurrence;
use App\Enums\RecurrencePreset;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoResource;
use App\Models\Recurrence;
use App\Models\Todo;
use App\Support\RecurrencePresets;
use App\Support\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurrenceController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function store(
        Request $request,
        Todo $todo,
        CreateRecurrence $createRecurrence,
        UpdateRecurrence $updateRecurrence,
        RecurrencePresets $presets,
    ): array {
        abort_if($todo->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'preset' => ['required_without:rrule', 'nullable', Rule::enum(RecurrencePreset::class)],
            'rrule' => ['required_without:preset', 'nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! RecurrenceSchedule::isValid((string) $value)) {
                    $fail('Ongeldige herhaalregel.');
                }
            }],
            'anchor_date' => ['nullable', 'date'],
        ]);

        $anchor = isset($validated['anchor_date'])
            ? CarbonImmutable::parse($validated['anchor_date'])
            : CarbonImmutable::today();

        $rrule = ! empty($validated['preset'])
            ? $presets->rrule(RecurrencePreset::from($validated['preset']), $anchor)
            : $validated['rrule'];

        if ($todo->recurrence !== null) {
            $updateRecurrence($todo->recurrence, $rrule);
        } else {
            $createRecurrence($todo, $rrule, $anchor);
        }

        return [
            'todo' => TodoResource::make($todo->fresh()->load(['recurrence', 'tags', 'lists', 'subTodos']))->resolve(),
        ];
    }

    public function destroy(Request $request, Recurrence $recurrence, StopRecurrence $stopRecurrence): JsonResponse
    {
        abort_if($recurrence->user_id !== $request->user()->id, 403);

        $stopRecurrence($recurrence);

        return response()->json(status: 204);
    }
}
