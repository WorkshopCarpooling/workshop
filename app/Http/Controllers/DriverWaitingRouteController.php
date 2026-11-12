<?php

namespace App\Http\Controllers;

use App\Services\{DriverWaitingRoutes, Journey};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverWaitingRouteController extends Controller
{
    public function store(Request $request)
    {
        $journey = Journey::fields($request);
        $data = $request->validate(['vehicle_id'=>'required|integer','departure_at'=>'prohibited','arrive_by'=>'prohibited']);
        $route = DriverWaitingRoutes::open((int)Auth::id(), $journey, (int)$data['vehicle_id']);
        return response()->json(['waiting'=>DriverWaitingRoutes::summary($route)],201)->header('Cache-Control','no-store, private');
    }
    public function heartbeat(Request $request)
    {
        $data = $request->validate(['id'=>'required|integer','version'=>'required|uuid','resume'=>'sometimes|boolean']);
        return response()->json(['waiting'=>DriverWaitingRoutes::summary(DriverWaitingRoutes::heartbeat((int)Auth::id(),(int)$data['id'],$data['version'],(bool)($data['resume']??false)))])->header('Cache-Control','no-store, private');
    }
    public function close(Request $request)
    {
        $data = $request->validate(['id'=>'required|integer','version'=>'required|uuid']);
        DriverWaitingRoutes::close((int)Auth::id(),(int)$data['id'],$data['version']);
        return response()->json(['waiting'=>null])->header('Cache-Control','no-store, private');
    }
}
