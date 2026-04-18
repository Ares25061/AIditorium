<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\EditUserRequest;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\SetRoleRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\File;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view-list',User::class);
        $users = User::with(['role'])->paginate($request->per_page ?? 10, ['*'], 'page', $request->page ?? 1);
        if ($users->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }

        return response()->json(['status'=> __('messages.status.success'), 'users' => $users], 200);
    }

    public function store(CreateUserRequest $request)
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $roleId = Role::where('name', 'user')->value('id');
        $validated['role_id'] = $roleId;
        $user = User::create($validated);
        $user->refresh();
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.created', ['item' => __('messages.items.user')]),
            'user' => $user,
        ]);
    }

    public function show(int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }
        $this->authorize('view',$user);
        return response()->json([
            'status'=> __('messages.status.success'),
            'user' => $user,
        ]);
    }

    public function edit(EditUserRequest $request)
    {
        $user = Auth::user();
        if (is_null($user)) {
            return response()->json([
                'status' => __('messages.status.error'),
                'message' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }
        $validated = $request->validated();
        $user->update([
            ...$validated,
            'email_verified_at'=> !empty($validated['email']) ? null : $user->email_verified_at,
        ]);
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.edited', ['item' => __('messages.items.user')]),
            'user' => $user,
        ]);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }
        $this->authorize('update',$user);
        $validated = $request->validated();
        $user->update([
            ...$validated,
            'email_verified_at'=> !empty($validated['email']) ? null : $user->email_verified_at,
            'password' => !empty($validated['password']) ? Hash::make($validated['password']) : $user->password
        ]);
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.updated', ['item' => __('messages.items.user')]),
            'user' => $user,
        ]);
    }


    public function destroy(int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }
        $this->authorize('delete',$user);
        if (!is_null($user->avatar)) {
            $oldFile = File::find($user->avatar);
            if (!is_null($oldFile) && Storage::disk('public')->exists($oldFile->path)) {
                Storage::disk('public')->delete($oldFile->path);
                $oldFile->delete();
            }
        }
        $user->delete();
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.deleted', ['item' => __('messages.items.user')]),
        ]);
    }

    public function register(CreateUserRequest $request)
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $roleId = DB::table('roles')->where('name', 'user')->value('id');
        $validated['role_id'] = $roleId;
        $user = User::create($validated);
        $token = Auth::login($user);
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.registered'),
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }


    public function login(LoginUserRequest $request)
    {
        $validated = $request->validated();
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password']
        ];
        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'status'=> __('messages.status.error'),
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        $user = Auth::user();
        return response()->json([
            'status'=> __('messages.status.success'),
            'token' => $token,
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }


    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.logged_out'),
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status'=> __('messages.status.success'),
            'user' => Auth::user(),
            'authorization' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }


    public function setRole(int $id,SetRoleRequest $request)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }
        $this->authorize('set-role',$user);
        $user->role_id = Role::where('name', $request->role)->value('id');
        $user->save();
        return response()->json([
            'status'=> __('messages.status.success'),
            'message' => __('messages.role_set'),
            'user' => $user
        ]);
    }


}
