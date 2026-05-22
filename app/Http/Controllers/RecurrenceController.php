<?php

namespace App\Http\Controllers;

use App\Actions\Recurrences\CreateRecurrence;
use App\Actions\Recurrences\StopRecurrence;
use App\Actions\Recurrences\UpdateRecurrence;
use App\Enums\RecurrencePreset;
use App\Models\Recurrence;
use App\Models\Todo;
use App\Support\RecurrencePresets;
use App\Support\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurrenceController extends Controller
{
    public function store(
        Request $request,
        Todo $todo,
        CreateRecurrence $createRecurrence,
        UpdateRecurrence $updateRecurrence,
        RecurrencePresets $presets,
    ): RedirectResponse {
        $this->ensureOwnsTodo($request, $todo);

        $validated = $this->validateRequest($request);
        $anchor = $this->resolveAnchor($validated);

        $rrule = ! empty($validated['preset'])
            ? $presets->rrule(RecurrencePreset::from($validated['preset']), $anchor)
            : $validated['rrule'];

        // Already recurring? Adjust the existing schedule rather than spawning a
        // second recurrence, so settings can be tweaked each time it comes by.
        if ($todo->recurrence !== null) {
            $updateRecurrence($todo->recurrence, $rrule);
        } else {
            $createRecurrence($todo, $rrule, $anchor);
        }

        return back();
    }

    public function destroy(Request $request, Recurrence $recurrence, StopRecurrence $stopRecurrence): RedirectResponse
    {
        abort_if($recurrence->user_id !== $request->user()->id, 403);

        $stopRecurrence($recurrence);

        return back();
    }

    /**
     * @return array{preset?: ?string, rrule?: ?string, anchor_date?: ?string}
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'preset' => ['required_without:rrule', 'nullable', Rule::enum(RecurrencePreset::class)],
            'rrule' => ['required_without:preset', 'nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! RecurrenceSchedule::isValid((string) $value)) {
                    $fail('Ongeldige herhaalregel.');
                }
            }],
            'anchor_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array{anchor_date?: ?string}  $validated
     */
    private function resolveAnchor(array $validated): CarbonImmutable
    {
        return isset($validated['anchor_date'])
            ? CarbonImmutable::parse($validated['anchor_date'])
            : CarbonImmutable::today();
    }

    private function ensureOwnsTodo(Request $request, Todo $todo): void
    {
        abort_if($todo->user_id !== $request->user()->id, 403);
    }
}
