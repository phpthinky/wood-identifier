<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WoodController extends Controller
{
    //
    public function upload(Request $request)
    {
        // code...
           if ($request->hasFile('image')) {
        $path = $request->file('image')
                        ->store('test', 'public');
        return response()->json([
            'success' => true,
            'path'    => $path
        ],200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image received'
        ],400);

    }
}
