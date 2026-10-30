CREATE DATABASE IF NOT EXISTS workshop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE workshop;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NULL,
    role ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
    account_type ENUM('passenger', 'driver') NOT NULL DEFAULT 'passenger',
    gender ENUM('male', 'female', 'other') NULL,
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    account_status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_type VARCHAR(80) NOT NULL,
    vehicle_color VARCHAR(60) NOT NULL,
    vehicle_plate VARCHAR(20) NOT NULL,
    verification_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicles_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_vehicles_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uq_vehicles_user_plate (user_id, vehicle_plate),
    INDEX idx_vehicles_review (verification_status, created_at)
);

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

CREATE TABLE IF NOT EXISTS rides (
    ride_id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    vehicle_id INT NULL,
    route_id INT NULL,
    pickup_point VARCHAR(180) NOT NULL,
    destination VARCHAR(180) NOT NULL,
    departure_at DATETIME NOT NULL,
    available_seats TINYINT UNSIGNED NOT NULL,
    contribution_per_seat DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    vehicle_description VARCHAR(120) NOT NULL,
    vehicle_type VARCHAR(80) NOT NULL DEFAULT '',
    vehicle_color VARCHAR(60) NOT NULL DEFAULT '',
    vehicle_plate VARCHAR(20) NOT NULL DEFAULT '',
    notes VARCHAR(500) NULL,
    ride_status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rides_driver FOREIGN KEY (driver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_rides_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
    CONSTRAINT fk_rides_campus_route FOREIGN KEY (route_id) REFERENCES campus_routes(route_id) ON DELETE SET NULL,
    INDEX idx_rides_driver_departure (driver_id, departure_at),
    INDEX idx_rides_status_departure (ride_status, departure_at)
);
CREATE TABLE IF NOT EXISTS ride_route_stops (
    ride_id INT NOT NULL,
    stop_id INT NOT NULL,
    PRIMARY KEY (ride_id, stop_id),
    CONSTRAINT fk_ride_route_stops_ride FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_route_stops_stop FOREIGN KEY (stop_id) REFERENCES campus_route_stops(stop_id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS ride_bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    pickup_stop_id INT NULL,
    seats_requested TINYINT UNSIGNED NOT NULL DEFAULT 1,
    fare_per_seat DECIMAL(7,2) NULL,
    journey_distance_km DECIMAL(10,3) NULL,
    distance_source VARCHAR(50) NULL,
    fare_policy VARCHAR(50) NULL,
    pickup_label VARCHAR(180) NULL,
    pickup_latitude DECIMAL(10,7) NULL,
    pickup_longitude DECIMAL(10,7) NULL,
    booking_status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ride_bookings_ride FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_bookings_passenger FOREIGN KEY (passenger_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_bookings_pickup_stop FOREIGN KEY (pickup_stop_id) REFERENCES campus_route_stops(stop_id) ON DELETE SET NULL,
    UNIQUE KEY uq_ride_passenger (ride_id, passenger_id),
    INDEX idx_ride_bookings_driver_status (ride_id, booking_status),
    INDEX idx_ride_bookings_passenger (passenger_id, created_at)
);
