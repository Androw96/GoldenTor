<?php

namespace App\Http\Controllers;

use App\Support\PageLayouts;
use App\Support\PageTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

    /** 401/403 response when the request may not edit the site, otherwise null. */
    private function denied(Request $request): ?JsonResponse
    {
        if (! Auth::guard('web')->check()) {
            return response()->json(['message' => 'Bejelentkezés szükséges.'], 401);
        }
        $origin = $request->headers->get('Origin');
        if ($origin && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return response()->json(['message' => 'Érvénytelen kérés eredete.'], 403);
        }

        return null;
    }

    public function saveLayout(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $layouts = $request->input('layouts');
        if (! is_array($layouts) || $layouts === [] || count($layouts) > 20) {
            return response()->json(['message' => 'Hiányzó vagy hibás szerkezeti adat.'], 422);
        }
        try {
            $clean = [];
            foreach ($layouts as $prefix => $layout) {
                $clean[(string) $prefix] = PageLayouts::clean((string) $prefix, $layout);
            }
            PageLayouts::store($clean);
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'prefixes' => array_keys($clean)])->header('Cache-Control', 'no-store');
    }

    public function upload(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ], [
            'image.image' => 'Csak kép tölthető fel (JPG, PNG, WebP, GIF).',
            'image.mimes' => 'Csak JPG, PNG, WebP vagy GIF kép tölthető fel.',
            'image.max' => 'A kép legfeljebb 8 MB lehet.',
        ]);

        $file = $request->file('image');
        $extension = strtolower($file->guessExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return response()->json(['message' => 'Csak JPG, PNG, WebP vagy GIF kép tölthető fel.'], 422);
        }
        $directory = 'assets/uploads/site/' . now('Europe/Budapest')->format('Y/m');
        $name = Str::lower((string) Str::uuid()) . '.' . $extension;
        $path = Storage::disk('site')->putFileAs($directory, $file, $name, ['visibility' => 'public']);
        if (! $path) {
            return response()->json(['message' => 'A kép nem menthető a tárhelyre (jogosultság?).'], 500);
        }

        return response()->json(['ok' => true, 'url' => '/' . ltrim($path, '/')]);
    }

    public function save(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
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
