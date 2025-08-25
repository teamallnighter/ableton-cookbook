<?php

  namespace App\Http\Controllers\Api;

  use App\Http\Controllers\Controller;
  use App\Models\User;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Hash;
  use Illuminate\Validation\ValidationException;

  class AuthController extends Controller
  {
      public function login(Request $request)
      {
          $request->validate([
              'email' => 'required|email',
              'password' => 'required',
          ]);

          $user = User::where('email', $request->email)->first();

          if (!$user || !Hash::check($request->password, $user->password)) {
              throw ValidationException::withMessages([
                  'email' => ['The provided credentials are incorrect.'],
              ]);
          }

          $user->tokens()->where('name', 'desktop-app')->delete();

          $token = $user->createToken('desktop-app', ['*'])->plainTextToken;

          return response()->json([
              'user' => $user,
              'token' => $token,
          ]);
      }

      public function logout(Request $request)
      {
          $request->user()->currentAccessToken()->delete();
          return response()->json(['message' => 'Logged out successfully']);
      }

      public function user(Request $request)
      {
          return response()->json($request->user());
      }
  }
