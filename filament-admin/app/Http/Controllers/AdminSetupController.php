<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSetupController extends Controller
{
    public function show(): View
    {
        abort_if(AdminUser::query()->exists(), 404);

        return view('setup');
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

        $expected = (string) config('goldentor.admin_key');
        if ($expected === '' || ! hash_equals($expected, (string) $data['admin_key'])) {
            throw ValidationException::withMessages(['admin_key' => 'Hibás admin kód.']);
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
