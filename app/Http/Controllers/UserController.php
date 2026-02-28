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
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('view-list',User::class);
        $users = User::with(['role'])->paginate($request->per_page ?? 10, ['*'], 'page', $request->page ?? 1);
        if ($users->isEmpty()) {
            return response()->json(['error' => 'Users not found'], 404);
        }

        return response()->json(['status'=> 'success','users' => $users], 200);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateUserRequest $request)
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $roleId = Role::where('name', 'user')->value('id');
        $validated['role_id'] = $roleId;
        $user = User::create($validated);
        $user->refresh();
        return response()->json([
            'status'=> 'success',
            'message' => 'User created!',
            'user' => $user,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $this->authorize('view',$user);
        return response()->json([
            'status'=> 'success',
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EditUserRequest $request)
    {
        $user = Auth::user();
        if (is_null($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
        $validated = $request->validated();
        $user->update([
            ...$validated,
            'email_verified_at'=> !empty($validated['email']) ? null : $user->email_verified_at,
        ]);
        return response()->json([
            'status'=> 'success',
            'message' => 'User edited!',
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $this->authorize('update',$user);
        $validated = $request->validated();
        $user->update([
            ...$validated,
            'email_verified_at'=> !empty($validated['email']) ? null : $user->email_verified_at,
            'password' => !empty($validated['password']) ? Hash::make($validated['password']) : $user->password
        ]);
        return response()->json([
            'status'=> 'success',
            'message' => 'User updated!',
            'user' => $user,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $this->authorize('delete',$user);
        if (!is_null($user->avatar)) {
            $oldFile = File::find($user->avatar);
            if (!is_null($oldFile) && Storage::exists($oldFile->path)) {
                Storage::delete($oldFile->path);
                $oldFile->delete();
            }
        }
        $user->delete();
        return response()->json([
            'status'=> 'success',
            'message' => 'User deleted',
        ]);
    }

    /**
     * Регистрация пользователя.
     * @unauthenticated
     */
    public function register(CreateUserRequest $request)
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        $roleId = DB::table('roles')->where('name', 'user')->value('id');
        $validated['role_id'] = $roleId;
        $user = User::create($validated);
        $token = Auth::login($user);
        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    /**
     * Вход пользователя.
     * @unauthenticated
     */
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
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    /**
     * Выход из аккаунта пользователя.
     */
    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'logged out',
        ]);
    }

    /**
     * Обновление токена пользователя.
     */
    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorization' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }

    /**
     * Выдать роль пользователю (только для админа).
     */
    public function setRole(int $id,SetRoleRequest $request)
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $this->authorize('set-role',$user);
        $user->role_id = Role::where('name', $request->role)->value('id');
        $user->save();
        return response()->json([
            'status'=> 'success',
            'message' => 'User set role!',
            'user' => $user
        ]);
    }


}
