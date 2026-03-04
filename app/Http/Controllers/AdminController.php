<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    // Dashboard overview
    public function index()
    {
        $stats = [
            'users' => User::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    // User management list
    public function users(Request $request)
    {
        $query = User::latest();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('pinfl', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate(20)->withQueryString();
        $allRoles = self::roleOptions();

        return view('admin.users', compact('users', 'allRoles'));
    }

    // Create user (POST)
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'pinfl' => 'nullable|string|max:20|unique:users,pinfl',
            'password' => 'required|string|min:6',
            'role' => 'required|in:' . implode(',', array_keys(self::roleOptions())),
        ]);

        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return back()->with('success', 'Foydalanuvchi yaratildi.');
    }

    // Edit user (GET)
    public function editUser(User $user)
    {
        $allRoles = self::roleOptions();
        return view('admin.user-edit', compact('user', 'allRoles'));
    }

    // Update user (PATCH)
    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'pinfl' => 'nullable|string|max:20|unique:users,pinfl,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:' . implode(',', array_keys(self::roleOptions())),
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users')->with('success', $user->name . ' yangilandi.');
    }

    // Delete user
    public function destroyUser(User $user)
    {
        abort_if($user->id === Auth::id(), 403, 'O\'zingizni o\'chira olmaysiz');

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users')->with('success', $name . ' o\'chirildi.');
    }

    // Shared helpers
    public static function roleOptions(): array
    {
        return [
            'admin' => 'Administrator (IT)',
            'user' => 'Foydalanuvchi',
        ];
    }
}
