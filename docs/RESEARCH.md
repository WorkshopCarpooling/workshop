# Campus commuting research workflow

The application supports the three project objectives, but a research claim requires real observations. No sample accounts, rides, survey responses or training observations are created by the new migration or training script.

## Install the empty research schema

From this project directory, with MySQL running:

```powershell
php artisan commute:install-schema
```

This idempotent command adds nullable journey fields to existing rides/bookings and creates empty research tables. It preserves existing records and performs no INSERT, UPDATE or DELETE. It also does not write a migrations bookkeeping row. The same migration may safely be applied later by Laravel's ordinary migration runner. Fresh schema and fixed-route SQL now create structure without inserting sample routes. Existing legacy survey records remain in their original table.

Dates and `datetime-local` fields use Asia/Kuala_Lumpur. Existing timestamps are not rewritten. If you previously stored UTC timestamps, decide how to convert those records before using them as research observations.

## Objective 1: requirements, patterns and measured travel times

Register and approve your own accounts. Add weekly class/work sessions under **Timetable & research**. The commuter survey collects mode, commuting frequency, usual duration, departure, weekly cost, willingness and free-text needs. Users consent before submitting surveys/usability responses. Only administrators can export research data; exports omit user IDs and account names. Free text may still identify people.

Record actual departure, arrival and measured road distance after each journey. Repeat observations of the same route in the same direction using its stable route key from the driver's route page. Changes to coordinates or ordered stops produce different keys. Never record simulated tracking as measured travel. The existing tracking page remains a clearly labelled simulation.

## Objective 2: matching, A* pathfinding and LSTM prediction

Drivers choose start/end names from search results or place map pins, then publish future rides with an approved vehicle. Passenger pickup defaults to device location, with a manual fallback. Passengers choose destination, earliest departure and seat count. Selecting a timetable session suggests departure 70 minutes before its next start (or leaving now if that time has passed) and copies the building name; confirm the destination pin separately and check the ride's estimated arrival. Passenger search has no arrival-deadline field and adds no hidden deadline. Weekly timetable entries do not create bookings automatically. Planning still respects arrival deadlines saved on existing bookings.

Matching rejects unavailable seats, departures before the requested time or more than two hours later, detours over max(5 km, 40% of the current route), and infeasible arrival deadlines. It rechecks feasibility before booking and before the driver accepts. Arrival times include a two-minute allowance per intermediate stop. Matching uses A* stop-order planning with estimated distances and travel times, so road conditions and walking/parking time need extra allowance.

Compatibility score: route proximity 0–50 points, departure proximity 0–35, seat feasibility 15. Without a departure preference the departure component is 35; without a deadline no arrival constraint is applied. Timetable selection influences the submitted departure preference and destination, without imposing a hidden arrival deadline. This is a documented weighted score, not a learned classifier.

The route planner uses A* search over states containing the current stop, visited-stop mask and elapsed time. It searches all feasible pickup/drop-off orders, keeps the driver's endpoints fixed and checks each passenger's arrival deadline. Edge cost is straight-line kilometres × 1.28; time uses the existing 42 km/h estimate with a minimum one-minute leg and two-minute intermediate-stop allowance. The admissible heuristic is a minimum spanning tree over remaining stops and the destination plus the cheapest connection from the current stop. Multiple non-dominated distance/time labels are retained per visited-mask/current-stop state so deadline-feasible alternatives are not incorrectly discarded. Completed A* searches find the shortest feasible order under these estimates, not the shortest street-level driving route. This is classical AI search, not machine learning. See the [original A* paper](https://ai.stanford.edu/~nilsson/OnlinePubs-Nils/PublishedPapers/astar.pdf).

Search is bounded to seven passenger pairs, 20,000 state expansions and 50,000 generated labels (limits in `config/carpool.php`; hard ceilings apply). A limit returns a labelled feasible A* incumbent if available; otherwise the existing precedence/deadline-aware insertion planner is used. Limited and fallback results never claim optimality. If no feasible fallback is found the journey is unavailable, which does not prove no route exists. `source`, `optimal`, `expandedStates`, `fallbackReason` and `distanceBasis` identify the actual planner and its limits in the route result. The displayed baseline is all passenger pickups followed by their drop-offs in booking order. Google Routes computes the road route through the suggested stops when a valid API key is configured. Without it, estimates and schematic connections are explicitly labelled. Free mapping uses Leaflet/OpenStreetMap, and place search uses known campus locations then Photon, with cached on-demand requests. Google search results and routes use the Google map when a key is configured.

LSTM training is intentionally deferred until you have collected data. Export **travel times** as an administrator, then use Python 3.10–3.12 in a separate virtual environment:

```powershell
python -m venv .venv-ml
.venv-ml\Scripts\python -m pip install -r ml/requirements.txt
.venv-ml\Scripts\python ml/train_lstm.py path\to\travel-times.csv --output storage/app/ml
php tests/verify_lstm_parity.php
```

The model takes eight sequential windows with distance, cyclic hour/day and the previous completed journey duration. A minimum of 50 usable windows is an operational guard, not evidence of adequate statistical power. Collect substantially more repeated trips across days and peak/off-peak periods. The training code rejects invalid observations and deduplicates shared journey observations by route/departure.

The PHP inference service loads the exported weights without a Python server. It uses LSTM only for routes seen in training with enough preceding completed observations. Missing/invalid models or insufficient history use a clearly labelled historical mean or distance/speed baseline. The LSTM estimate is shown on the driver's route page; matching uses the documented A* stop-order search and fixed compatibility score; LSTM does not choose the path. Current prediction uses Google road distance when available. Without road routing, its approximate distance input is labelled; exclude those predictions from any claim based on measured road-distance features.

## Objective 3: evaluation

- Functionality: run `php tests/astar_pathfinding.php` (including exhaustive small-instance optimum comparisons) and `php tests/commute_features.php`. Manually test registration and role approval; both pickup/drop-off search and map pins; vehicle approval; publishing; deadline and seat matching; requests; acceptance/decline; timetable validation; trip transitions; surveys; export restrictions; mobile navigation and logout. Try two requests competing for the final seat. Use your own accounts; the automated tests do not insert database records.
- Route efficiency: compare suggested and unoptimized baseline distance for identical endpoints/passengers. The displayed metrics use estimated edge distances; only completed A* searches establish an optimum under that cost model. For the dissertation, measure both orders through the same road-routing provider at the same departure, including stop dwell time; the UI does not claim measured road-distance savings.
- Travel-time accuracy: the script uses a single chronological 80/20 boundary. Training observations must be completed before that boundary. The last 15% of training windows is validation; preprocessing fits the earlier training subset. It reports MAE, RMSE and MAPE against a training-only route-mean baseline on future holdout targets. Rolling history may use earlier completed holdout observations as they become available, but no holdout targets fit weights/scalers. Retain the exported CSV hash, dates, sample counts, exclusions, hyperparameters and `evaluation.json`. Report the real results even if the baseline performs better.
- Usability: collect ten-item SUS responses after users attempt defined tasks. Odd items contribute answer−1; even items contribute 5−answer; sum ×2.5. Administrator reports show response count and mean score. Report participant composition, task completion, errors and qualitative feedback; do not describe SUS as a percentage of task success.

No trained model or fabricated evaluation result ships with the project.

Implementation references: [Leaflet documentation](https://leafletjs.com/reference), [Photon geocoder](https://github.com/komoot/photon), [Google intermediate waypoints](https://developers.google.com/maps/documentation/routes/intermed_waypoints), [Keras LSTM](https://keras.io/api/layers/recurrent_layers/lstm/).
