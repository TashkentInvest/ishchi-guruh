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
            'users'   => User::count(),
            'pending' => User::where('status', 'pending')->count(),
        ];

        $pendingUsers = User::where('status', 'pending')->latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'pendingUsers'));
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

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->paginate(20)->withQueryString();
        $allRoles = self::roleOptions();
        $allStatuses = self::statusOptions();

        return view('admin.users', compact('users', 'allRoles', 'allStatuses'));
    }

    // Create user (POST)
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:users,email',
            'pinfl'    => 'nullable|string|max:20|unique:users,pinfl',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:' . implode(',', array_keys(self::roleOptions())),
        ]);

        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return back()->with('success', 'Фойдаланувчи яратилди.');
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
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'pinfl'    => 'nullable|string|max:20|unique:users,pinfl,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role'     => 'required|in:' . implode(',', array_keys(self::roleOptions())),
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users')->with('success', $user->name . ' янгиланди.');
    }

    // Approve user
    public function approveUser(User $user)
    {
        $user->update(['status' => 'approved']);
        return back()->with('success', $user->name . ' tasdiqlandi. Endi tizimdan foydalana oladi.');
    }

    // Reject user
    public function rejectUser(User $user)
    {
        $user->update(['status' => 'rejected']);
        return back()->with('success', $user->name . ' rad etildi.');
    }

    // Delete user
    public function destroyUser(User $user)
    {
        abort_if($user->id === Auth::id(), 403, 'Ўзингизни ўчира олмайсиз');

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users')->with('success', $name . ' ўчирилди.');
    }

    // Shared helpers
    public static function roleOptions(): array
    {
        return [
            'admin' => 'Администратор (IT)',
            'user'  => 'Фойдаланувчи',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending'  => 'Kutilmoqda',
            'approved' => 'Tasdiqlangan',
            'rejected' => 'Rad etilgan',
        ];
    }
}
