"""Train from the admin travel-times CSV. Never generates samples or writes to MySQL."""
import argparse
import csv
import hashlib
import json
import math
from collections import defaultdict
from datetime import datetime
from pathlib import Path


def features(distance, departure, previous):
    hour = departure.hour + departure.minute / 60
    day = (departure.weekday() + 1) % 7
    return [distance, math.sin(2*math.pi*hour/24), math.cos(2*math.pi*hour/24),
            math.sin(2*math.pi*day/7), math.cos(2*math.pi*day/7), previous]


def load_rows(path):
    grouped = defaultdict(list)
    seen = set()
    with path.open(encoding="utf-8-sig", newline="") as stream:
        for line, row in enumerate(csv.DictReader(stream), 2):
            try:
                route = row["route_key"].strip()
                departure = datetime.fromisoformat(row["departed_at"])
                arrival = datetime.fromisoformat(row["arrived_at"])
                distance = float(row["distance_km"])
                actual = (arrival-departure).total_seconds()/60
                if not route or not math.isfinite(distance) or not 0.05 <= distance <= 500 or not 1 <= actual <= 600:
                    raise ValueError("invalid range")
                identity = (route, departure)
                if identity in seen:
                    continue  # Multiple passengers must not duplicate the same route/departure.
                seen.add(identity)
                grouped[route].append({"departure": departure, "arrival": arrival, "distance": distance, "actual": actual})
            except (KeyError, ValueError) as error:
                raise ValueError(f"CSV line {line}: invalid measured observation ({error})") from error
    for rows in grouped.values():
        rows.sort(key=lambda row: row["departure"])
    return grouped


def windows(grouped, length):
    output = []
    for route, rows in grouped.items():
        # At prediction time only journeys already completed can be history.
        for current in rows:
            history = [r for r in rows if r["departure"] < current["departure"] and r["arrival"] < current["departure"]]
            if len(history) < length:
                continue
            tail = history[-length:]
            sequence = [features(tail[i]["distance"], tail[i]["departure"], tail[i-1]["actual"]) for i in range(1, length)]
            sequence.append(features(current["distance"], current["departure"], tail[-1]["actual"]))
            output.append((current["departure"], current["arrival"], route, sequence, current["actual"]))
    return sorted(output, key=lambda item: item[0])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("csv", type=Path)
    parser.add_argument("--output", type=Path, default=Path("storage/app/ml"))
    parser.add_argument("--sequence", type=int, default=8)
    parser.add_argument("--epochs", type=int, default=100)
    args = parser.parse_args()
    if not 2 <= args.sequence <= 32:
        parser.error("--sequence must be between 2 and 32")
    grouped = load_rows(args.csv)
    records = windows(grouped, args.sequence)
    if len(records) < 50:
        parser.error("Collect at least 50 usable sequential prediction windows (plus history). No model was created.")
    # A single time boundary across all routes. Holdout targets and their labels
    # never fit scalers, baseline means, weights or early stopping.
    boundary = records[int(len(records)*0.8)][0]
    train = [r for r in records if r[0] < boundary and r[1] < boundary]
    test = [r for r in records if r[0] >= boundary]
    if len(train) < 30 or len(test) < 10:
        parser.error("Need at least 30 training and 10 future holdout windows at distinct times.")
    import numpy as np
    import tensorflow as tf
    tf.keras.utils.set_random_seed(42)
    x_train = np.asarray([r[3] for r in train], dtype=np.float32)
    y_train = np.asarray([r[4] for r in train], dtype=np.float32)
    x_test = np.asarray([r[3] for r in test], dtype=np.float32)
    y_test = np.asarray([r[4] for r in test], dtype=np.float32)
    # Split a chronological validation tail before fitting preprocessing.
    fit_count = int(len(train)*0.85)
    feature_mean = x_train[:fit_count].mean(axis=(0, 1))
    feature_std = np.maximum(x_train[:fit_count].std(axis=(0, 1)), 1e-6)
    target_mean = float(y_train[:fit_count].mean())
    target_std = max(float(y_train[:fit_count].std()), 1e-6)
    normalized_x = (x_train-feature_mean)/feature_std
    normalized_y = (y_train-target_mean)/target_std
    model = tf.keras.Sequential([tf.keras.Input(shape=(args.sequence, 6)), tf.keras.layers.LSTM(16), tf.keras.layers.Dense(1)])
    model.compile(optimizer="adam", loss="mse")
    model.fit(normalized_x[:fit_count], normalized_y[:fit_count], validation_data=(normalized_x[fit_count:], normalized_y[fit_count:]), epochs=args.epochs, batch_size=min(16, fit_count), shuffle=False,
              callbacks=[tf.keras.callbacks.EarlyStopping(monitor="val_loss", patience=12, restore_best_weights=True)], verbose=2)
    predicted = model.predict((x_test-feature_mean)/feature_std, verbose=0).ravel()*target_std+target_mean
    route_means = {key: float(np.mean([r["actual"] for r in rows if r["arrival"] < boundary]))
                   for key, rows in grouped.items() if any(r["arrival"] < boundary for r in rows)}
    baseline = np.asarray([route_means.get(r[2], float(y_train.mean())) for r in test])
    def metrics(values):
        error = values-y_test
        return {"mae": float(np.abs(error).mean()), "rmse": float(np.sqrt((error**2).mean())), "mape": float((np.abs(error)/y_test).mean()*100)}
    kernel, recurrent, bias = model.layers[0].get_weights()
    dense, dense_bias = model.layers[1].get_weights()
    artifact = {"format": "rideutem-lstm-v1", "sequence_length": args.sequence, "kernel": kernel.tolist(), "recurrent_kernel": recurrent.tolist(), "bias": bias.tolist(),
                "dense_kernel": dense[:,0].tolist(), "dense_bias": float(dense_bias[0]), "feature_mean": feature_mean.tolist(), "feature_std": feature_std.tolist(),
                "target_mean": target_mean, "target_std": target_std, "route_keys": sorted({r[2] for r in train})}
    report = {"trained_at": datetime.now().isoformat(timespec="seconds"), "dataset_sha256": hashlib.sha256(args.csv.read_bytes()).hexdigest(),
              "split": "chronological 80/20; training validation tail 15%; rolling observed history", "holdout_start": boundary.isoformat(), "train_samples": len(train), "test_samples": len(test),
              "baseline": metrics(baseline), "lstm": metrics(predicted), "sequence_length": args.sequence}
    args.output.mkdir(parents=True, exist_ok=True)
    (args.output/"lstm.json").write_text(json.dumps(artifact, allow_nan=False), encoding="utf-8")
    (args.output/"evaluation.json").write_text(json.dumps(report, indent=2, allow_nan=False), encoding="utf-8")
    # This real held-out window lets the PHP test confirm the exported gate math.
    (args.output/"parity.json").write_text(json.dumps({"sequence": x_test[0].tolist(), "expected_minutes": float(predicted[0])}), encoding="utf-8")
    model.save(args.output/"model.keras")
    print(json.dumps(report, indent=2))
    print("Model and chronological evaluation exported. No database records were written.")


if __name__ == "__main__":
    main()
