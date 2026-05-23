<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Recurrences\CreateRecurrence;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use App\Support\DutchDateParser;
use App\Support\QuickAddFeedback;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class QuickAddTodo
{
    public function __construct(
        private readonly CreateTodo $createTodo,
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly CreateRecurrence $createRecurrence,
        private readonly DutchDateParser $parser,
        private readonly QuickAddFeedback $feedback,
    ) {}

    /**
     * Create a todo from a free-text quick-add line. The line is parsed for Dutch
     * date/recurrence phrases ("morgen", "volgende week dinsdag", "elke werkdag");
     * the cleaned remainder becomes the title. An explicit date or recurrence
     * always wins. Without one, the todo lands on $contextList (the page the user
     * is on — today / master / a custom list); with no context it falls back to
     * the workday rule (today, or next Monday in the weekend). Always also
     * attached to master via CreateTodo.
     *
     * The returned `feedback` is the single source of truth for the confirmation
     * shown on every client (web/iOS/Mac toast, Siri).
     *
     * @return array{todo: Todo, target_date: ?CarbonImmutable, feedback: array{message: string, description: string}}
     */
    public function __invoke(User $user, string $title, ?TodoList $contextList = null): array
    {
        return DB::transaction(function () use ($user, $title, $contextList) {
            $parsed = $this->parser->parse($title);
            $cleanTitle = $parsed['title'];

            if ($parsed['recurrence'] !== null) {
                $anchor = $parsed['recurrence']['anchor'];
                $dailyList = ($this->getOrCreateDailyList)($user, $anchor);
                $todo = ($this->createTodo)($user, ['title' => $cleanTitle], $dailyList);
                ($this->createRecurrence)($todo, $parsed['recurrence']['rrule'], $anchor);
                $todo = $todo->fresh();
                $targetDate = $anchor;
            } elseif ($parsed['date'] !== null) {
                $dailyList = ($this->getOrCreateDailyList)($user, $parsed['date']);
                $todo = ($this->createTodo)($user, ['title' => $cleanTitle], $dailyList);
                $targetDate = $parsed['date'];
            } elseif ($contextList !== null) {
                // No explicit date: follow the current page.
                $todo = ($this->createTodo)($user, ['title' => $cleanTitle], $contextList);
                $targetDate = $this->contextDate($contextList);
            } else {
                // No context either: fall back to the workday rule.
                $targetDate = Workday::quickAddTargetDate();
                $dailyList = ($this->getOrCreateDailyList)($user, $targetDate);
                $todo = ($this->createTodo)($user, ['title' => $cleanTitle], $dailyList);
            }

            return [
                'todo' => $todo,
                'target_date' => $targetDate,
                'feedback' => $this->feedback->build($todo, $targetDate),
            ];
        });
    }

    private function contextDate(TodoList $list): ?CarbonImmutable
    {
        return $list->date !== null ? CarbonImmutable::instance($list->date) : null;
    }
}
