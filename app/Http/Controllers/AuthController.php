<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //Register
    public function register(Request $request)
    {   
        //Validate 
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_@ ]+$/', // Chỉ cho phép chữ cái không dấu, số, dấu cách, _ và @
                function ($attribute, $value, $fail) use ($request) {
                    if ($value === $request->email) {
                        $fail('The name cannot be the same as the email.');
                    }
                }
            ],
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), //Hash password
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        //Validate 
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'password' => 'required'
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
    
        // At least Name or Email
        if (!$request->filled('name') && !$request->filled('email')) {
            return response()->json(['message' => 'You must provide either a name or an email.'], 400);
        }
    
        //Priority Name than Email
        $user = null;
        if ($request->filled('name')) {
            $user = User::where('name', $request->name)->first();
        } elseif ($request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        }
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
    
        return response()->json([
            'message' => 'Login successful',
            'user' => $user
        ], 200);
    }
}