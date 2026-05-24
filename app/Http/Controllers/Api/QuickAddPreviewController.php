<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DateParsing\DutchDateParser;
use Illuminate\Http\Request;

/**
 * Live parse preview for the iOS/Mac quick-add field. Mirrors the web endpoint:
 * GET with a `title` query param, returns the segment annotation.
 */
class QuickAddPreviewController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, DutchDateParser $parser): array
    {
        return $parser->annotate((string) $request->query('title', ''), null, $request->boolean('parse', true));
    }
}
