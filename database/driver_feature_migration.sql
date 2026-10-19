-- Run once against the existing workshop database to enable driver accounts and ride publishing.
ALTER TABLE users
    ADD COLUMN account_type ENUM('passenger', 'driver') NOT NULL DEFAULT 'passenger' AFTER role;

CREATE TABLE IF NOT EXISTS rides (
    ride_id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
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
    INDEX idx_rides_driver_departure (driver_id, departure_at),
    INDEX idx_rides_status_departure (ride_status, departure_at)
);

CREATE TABLE IF NOT EXISTS ride_bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    seats_requested TINYINT UNSIGNED NOT NULL DEFAULT 1,
    booking_status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ride_bookings_ride FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_bookings_passenger FOREIGN KEY (passenger_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_ride_passenger (ride_id, passenger_id),
    INDEX idx_ride_bookings_driver_status (ride_id, booking_status),
    INDEX idx_ride_bookings_passenger (passenger_id, created_at)
);