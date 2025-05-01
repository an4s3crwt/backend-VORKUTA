<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
   public function register(Request $request){
    $request->validate([
        'name' => 'required',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6|confirmed',
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    $token = JWTAuth::fromUser($user);

    return response()->json(['user' => $user, 'jwt_token' => $token], 201);

   }


public function login(Request $request){
    $request->validate([
        'email'=> 'required|email',
        'password' =>'required|min:6',
    ]);
    if(!$token = JWTAuth::attempt($request->only('email', 'password'))){
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return response()->json(['jwt_token' => $token]);

}
    public function me(Request $request)
    {
       return response()->json($request->user());
    }





}
