<?php

namespace App\Http\Controllers;

use App\Support\PageTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Endpoints used by site-text.js for editing texts directly on the public pages. */
class InlineTextController extends Controller
{
    public function session(): JsonResponse
    {
        $editor = Auth::guard('web')->check();

        return response()
            ->json(['editor' => $editor, 'csrf' => $editor ? csrf_token() : null])
            ->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request): JsonResponse
    {
        if (! Auth::guard('web')->check()) {
            return response()->json(['message' => 'Bejelentkezés szükséges.'], 401);
        }
        $origin = $request->headers->get('Origin');
        if ($origin && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return response()->json(['message' => 'Érvénytelen kérés eredete.'], 403);
        }

        $data = $request->validate([
            'page' => ['nullable', 'string', 'max:120'],
            'changes' => ['required', 'array', 'max:600'],
            'changes.*' => ['array'],
            'changes.*.value' => ['nullable', 'string', 'max:' . PageTexts::MAX_LENGTH],
            'changes.*.original' => ['nullable', 'string', 'max:' . PageTexts::MAX_LENGTH],
            'changes.*.rich' => ['nullable', 'boolean'],
            'changes.*.reset' => ['nullable', 'boolean'],
        ]);

        $invalid = array_values(array_filter(array_keys($data['changes']), fn ($key) => ! preg_match(PageTexts::KEY_PATTERN, (string) $key)));
        if ($invalid) {
            return response()->json(['message' => 'Érvénytelen szövegazonosító: ' . implode(', ', array_slice($invalid, 0, 5))], 422);
        }

        $result = PageTexts::apply($data['changes'], (string) ($data['page'] ?? ''), Auth::guard('web')->user()?->email);

        return response()->json(['ok' => true] + $result)->header('Cache-Control', 'no-store');
    }
}
