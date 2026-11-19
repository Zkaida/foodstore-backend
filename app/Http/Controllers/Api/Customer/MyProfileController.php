<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MyProfileController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function index()
    {
        $customer = Customer::whereId(auth()->guard('api')->user()->id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Data Profile Customer',
            'data'    => $customer,
        ], 200);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'email'    => 'required|email|unique:customers,email,' . auth()->guard('api')->user()->id,
            'password' => 'nullable|min:8|confirmed',
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile = Customer::whereId(auth()->guard('api')->user()->id)->first();

        if ($request->file('image')) {
            if ($profile->getRawOriginal('image')) {
                Storage::disk('public')->delete($profile->getRawOriginal('image'));
            }

            $image = $request->file('image');
            $imagePath = $image->store('customers', 'public');

            $profile->update([
                'image' => $imagePath,
            ]);
        }

        $profile->name = $request->name;
        $profile->email = $request->email;

        if ($request->password) {
            $profile->password = bcrypt($request->password);
        }

        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Update Profile Berhasil!',
            'data'    => $profile,
        ], 200);
    }
}
