<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearchController extends Controller
{
    public function index(string $section = 'research')
    {
        if ($section === 'timetable' && Auth::user()->isDriver()) return redirect('driver.php');
        $ready = Schema::hasTable('usability_responses') && Schema::hasTable('commute_timetable');
        $id = Auth::id();
        $timetable = $ready ? DB::table('commute_timetable')->where('user_id', $id)->orderBy('weekday')->orderBy('starts_at')->get() : collect();
        $survey = $ready ? DB::table('commute_requirement_surveys')->where('user_id', $id)->first() : null;
        $samples = $ready ? DB::table('travel_time_samples')->where('user_id', $id)->orderByDesc('departed_at')->limit(20)->get() : collect();
        $stats = [];
        if ($ready && Auth::user()->isAdmin()) {
            $stats = ['surveys' => DB::table('commute_requirement_surveys')->count(), 'samples' => DB::table('travel_time_samples')->count(), 'sus_count' => DB::table('usability_responses')->count(), 'sus_mean' => DB::table('usability_responses')->avg('sus_score')];
        }
        $evaluationFile = storage_path('app/ml/evaluation.json');
        $evaluation = Auth::user()->isAdmin() && is_file($evaluationFile) ? json_decode(file_get_contents($evaluationFile), true) : null;
        $researchSection=$section;
        $activePage=$section;
        return view('research', compact('researchSection','activePage','ready', 'timetable', 'survey', 'samples', 'stats', 'evaluation'));
    }

    public function save(Request $request)
    {
        abort_unless(Schema::hasTable('usability_responses'), 503, 'Install the schema-only journey migration first.');
        $action = $request->input('action');
        if (in_array($action, ['timetable','delete_timetable'], true)) abort_if(Auth::user()->isDriver(), 403);
        $id = Auth::id();
        if ($action === 'timetable') {
            $data = $request->validate(['title' => 'required|string|max:100', 'weekday' => 'required|integer|between:0,6', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at', 'location' => 'required|string|max:180']);
            $overlap = DB::table('commute_timetable')->where('user_id', $id)->where('weekday', $data['weekday'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists();
            if ($overlap) return back()->withErrors(['timetable' => 'This session overlaps with another timetable entry.'])->withInput();
            DB::table('commute_timetable')->insert($data + ['user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($action === 'delete_timetable') {
            $data = $request->validate(['id' => 'required|integer']);
            \App\Services\TimetableCommutes::removeSession((int)$id,(int)$data['id']);
        } elseif ($action === 'survey') {
            $data = $request->validate(['consent' => 'accepted', 'travel_mode' => 'required|in:car,motorcycle,bus,walk,carpool,other', 'days_per_week' => 'required|integer|between:1,7', 'usual_minutes' => 'required|integer|between:1,600', 'usual_departure' => 'required|date_format:H:i', 'weekly_cost' => 'required|numeric|between:0,99999', 'willingness' => 'required|integer|between:1,5', 'requirements' => 'nullable|string|max:2000']);
            unset($data['consent']);
            DB::table('commute_requirement_surveys')->updateOrInsert(['user_id' => $id], $data + ['updated_at' => now(), 'created_at' => now()]);
        } elseif ($action === 'sample') {
            $data = $request->validate(['route_key' => 'required|string|max:180', 'departed_at' => 'required|date|before:now', 'arrived_at' => 'required|date|after:departed_at|before_or_equal:now', 'distance_km' => 'required|numeric|between:0.05,500']);
            $minutes = (strtotime($data['arrived_at']) - strtotime($data['departed_at'])) / 60;
            if ($minutes < 1 || $minutes > 600) return back()->withErrors(['sample' => 'Record a measured journey between 1 and 600 minutes.'])->withInput();
            $data['route_key'] = trim($data['route_key']);
            if ($data['route_key'] === '') return back()->withErrors(['route_key' => 'Enter a route key.']);
            $data['departed_at'] = date('Y-m-d H:i:s', strtotime($data['departed_at']));
            $data['arrived_at'] = date('Y-m-d H:i:s', strtotime($data['arrived_at']));
            if (DB::table('travel_time_samples')->where('user_id', $id)->where('route_key', $data['route_key'])->where('departed_at', $data['departed_at'])->exists()) return back()->withErrors(['sample' => 'This observation has already been recorded.']);
            DB::table('travel_time_samples')->insert($data + ['actual_minutes' => round($minutes, 2), 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($action === 'usability') {
            $data = $request->validate(['consent' => 'accepted', 'answers' => 'required|array|size:10', 'answers.*' => 'required|integer|between:1,5', 'feedback' => 'nullable|string|max:2000']);
            $answers = array_values($data['answers']); $score = 0;
            foreach ($answers as $i => $answer) $score += $i % 2 === 0 ? $answer - 1 : 5 - $answer;
            DB::table('usability_responses')->updateOrInsert(['user_id' => $id], ['answers' => json_encode($answers), 'sus_score' => $score * 2.5, 'feedback' => $data['feedback'] ?? null, 'updated_at' => now(), 'created_at' => now()]);
        } else abort(422, 'Unknown research action.');
        return redirect(in_array($action,['timetable','delete_timetable']) ? 'timetable' : 'research')->with('researchNotice', 'Saved. Thank you for contributing your own data.');
    }

    public function export(Request $request, string $type)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        $tables = ['travel-times' => ['travel_time_samples', ['route_key', 'departed_at', 'arrived_at', 'distance_km', 'actual_minutes']], 'survey' => ['commute_requirement_surveys', ['travel_mode', 'days_per_week', 'usual_minutes', 'usual_departure', 'weekly_cost', 'willingness', 'requirements']], 'usability' => ['usability_responses', ['answers', 'sus_score', 'feedback']]];
        abort_unless(isset($tables[$type]), 404);
        [$table, $columns] = $tables[$type];
        abort_unless(Schema::hasTable($table), 503, 'Install the research schema first.');
        return response()->streamDownload(function () use ($table, $columns) {
            $out = fopen('php://output', 'w'); fputcsv($out, $columns);
            foreach (DB::table($table)->select($columns)->orderBy('id')->cursor() as $row) {
                $values = array_map(function ($value) {
                    // Keep free-text cells from becoming spreadsheet formulas.
                    return is_string($value) && preg_match('/^[\s]*[=+@-]/', $value) ? "'" . $value : $value;
                }, array_values((array)$row));
                fputcsv($out, $values);
            }
            fclose($out);
        }, $type . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
