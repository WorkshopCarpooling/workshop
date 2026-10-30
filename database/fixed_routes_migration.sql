-- Run once against the existing workshop database.
-- Adds legacy fixed-route structure only. No example routes or stops are inserted.
USE workshop;

CREATE TABLE IF NOT EXISTS campus_routes (
    route_id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL UNIQUE,
    route_name VARCHAR(150) NOT NULL,
    direction ENUM('to_campus', 'from_campus') NOT NULL,
    origin_name VARCHAR(150) NOT NULL,
    destination_name VARCHAR(150) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campus_route_stops (
    stop_id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    stop_order TINYINT UNSIGNED NOT NULL,
    stop_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_route_stops_route FOREIGN KEY (route_id) REFERENCES campus_routes(route_id) ON DELETE CASCADE,
    UNIQUE KEY uq_route_stop_order (route_id, stop_order),
    UNIQUE KEY uq_route_stop_name (route_id, stop_name)
);

ALTER TABLE rides
    ADD COLUMN route_id INT NULL AFTER vehicle_id,
    ADD INDEX idx_rides_route_departure (route_id, departure_at),
    ADD CONSTRAINT fk_rides_campus_route FOREIGN KEY (route_id) REFERENCES campus_routes(route_id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS ride_route_stops (
    ride_id INT NOT NULL,
    stop_id INT NOT NULL,
    PRIMARY KEY (ride_id, stop_id),
    CONSTRAINT fk_ride_route_stops_ride FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_route_stops_stop FOREIGN KEY (stop_id) REFERENCES campus_route_stops(stop_id) ON DELETE CASCADE
);

ALTER TABLE ride_bookings
    ADD COLUMN pickup_stop_id INT NULL AFTER passenger_id,
    ADD INDEX idx_bookings_pickup_stop (pickup_stop_id),
    ADD CONSTRAINT fk_ride_bookings_pickup_stop FOREIGN KEY (pickup_stop_id) REFERENCES campus_route_stops(stop_id) ON DELETE SET NULL;
