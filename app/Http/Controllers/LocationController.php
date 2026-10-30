<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    public function reverse(Request $request)
    {
        $data=$request->validate(['lat'=>'required|numeric|between:-90,90','lng'=>'required|numeric|between:-180,180']);
        $lat=(float)$data['lat'];$lng=(float)$data['lng'];
        try {
            $place=Cache::remember('pin-name:'.sha1(sprintf('%.5f,%.5f',$lat,$lng)),86400,function()use($lat,$lng){
                $key=config('services.google_maps.api_key');
                if($key) {
                    try {
                        $data=Http::timeout(4)->get('https://maps.googleapis.com/maps/api/geocode/json',['latlng'=>$lat.','.$lng,'language'=>'en','key'=>$key])->throw()->json();
                        $label=trim($data['results'][0]['formatted_address']??'');
                        if($label!=='') return ['label'=>mb_substr($label,0,180),'source'=>'google'];
                    } catch(\Throwable $error) {}
                }
                $data=Http::timeout(5)->get('https://photon.komoot.io/reverse',['lat'=>$lat,'lon'=>$lng,'limit'=>1,'radius'=>1,'lang'=>'en'])->throw()->json();
                $place=$data['features'][0]['properties']??[];
                $street=trim(($place['housenumber']??'').' '.($place['street']??''));
                $parts=array_unique(array_filter([$place['name']??'',$street,$place['district']??'',$place['city']??$place['town']??$place['village']??'',$place['state']??'',$place['country']??'']));
                $label=implode(', ',$parts);
                if($label==='') throw new \RuntimeException('No place name for this pin.');
                return ['label'=>mb_substr($label,0,180),'source'=>'openstreetmap'];
            });
            return response()->json($place)->header('Cache-Control','no-store, private');
        } catch(\Throwable $error) {
            return response()->json(['message'=>'Place name unavailable. Your pinned position is still selected.'],503)->header('Cache-Control','no-store, private');
        }
    }

    public function search(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:180']);
        $query = trim($data['q']);
        $results = [];
        foreach (RoutesController::getCampusLocations() as $name => $point) {
            if (stripos($name, $query) !== false) $results[] = ['label' => $name] + $point;
        }
        if ($results) return response()->json(['results' => $results]);
        try {
            $results = Cache::remember('place:' . sha1(mb_strtolower($query)), 86400, function () use ($query) {
                $key = config('services.google_maps.api_key');
                if ($key) {
                    $response = Http::timeout(7)->get('https://maps.googleapis.com/maps/api/geocode/json', ['address' => $query, 'components' => 'country:MY', 'key' => $key])->throw()->json();
                    return array_map(fn ($p) => ['label' => $p['formatted_address'], 'lat' => $p['geometry']['location']['lat'], 'lng' => $p['geometry']['location']['lng']], $response['results'] ?? []);
                }
                $response = Http::timeout(7)->get('https://photon.komoot.io/api/', ['q' => $query, 'lat' => 2.27, 'lon' => 102.28, 'limit' => 5])->throw()->json();
                return array_values(array_map(function ($p) {
                    $v = $p['properties'];
                    return ['label' => implode(', ', array_unique(array_filter([$v['name'] ?? '', $v['street'] ?? '', $v['city'] ?? '', $v['state'] ?? '', $v['country'] ?? '']))), 'lat' => $p['geometry']['coordinates'][1], 'lng' => $p['geometry']['coordinates'][0]];
                }, array_filter($response['features'] ?? [], fn ($p) => ($p['properties']['countrycode'] ?? '') === 'MY')));
            });
            return response()->json(['results' => $results]);
        } catch (\Throwable $error) {
            return response()->json(['message' => 'Place search unavailable. Please use a map pin.'], 503);
        }
    }
}
