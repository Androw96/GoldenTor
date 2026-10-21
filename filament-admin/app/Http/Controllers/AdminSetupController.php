<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminSetupController extends Controller
{
    public function show(): Response
    {
        abort_if(AdminUser::query()->exists(), 404);

        return response()->view('setup')->header('Cache-Control', 'no-store, private, max-age=0');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(AdminUser::query()->exists(), 404);

        $data = $request->validate([
            'admin_key' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);

        $expected = (string) config('goldentor.admin_setup_key');
        if ($expected === '' || ! hash_equals($expected, (string) $data['admin_key'])) {
            throw ValidationException::withMessages(['admin_key' => 'Hibás kezdőkód.']);
        }

        $admin = AdminUser::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        Auth::login($admin, remember: true);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
