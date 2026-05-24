<?php

namespace App\Http\Controllers;

use App\Support\DateParsing\DutchDateParser;
use Illuminate\Http\Request;

/**
 * Live parse preview for the quick-add field: returns the typed sentence tiled
 * into highlighted segments. Read-only (GET, no side effects), so the front end
 * can call it on every keystroke without CSRF concerns.
 */
class QuickAddPreviewController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, DutchDateParser $parser): array
    {
        return $parser->annotate((string) $request->query('title', ''));
    }
}
