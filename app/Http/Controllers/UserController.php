<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    //// get all user
    public function getAllUsers()
    {
        $users = User::all(); // get all user from database

        return response()->json([
            'message' => 'List all user',
            'users' => $users
        ], 200);
    }
}
