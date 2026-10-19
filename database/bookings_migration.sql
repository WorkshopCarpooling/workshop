CREATE TABLE IF NOT EXISTS ride_bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    seats_requested TINYINT UNSIGNED NOT NULL DEFAULT 1,
    pickup_label VARCHAR(180) NULL,
    pickup_latitude DECIMAL(10,7) NULL,
    pickup_longitude DECIMAL(10,7) NULL,
    booking_status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ride_bookings_ride FOREIGN KEY (ride_id) REFERENCES rides(ride_id) ON DELETE CASCADE,
    CONSTRAINT fk_ride_bookings_passenger FOREIGN KEY (passenger_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_ride_passenger (ride_id, passenger_id),
    INDEX idx_ride_bookings_driver_status (ride_id, booking_status),
    INDEX idx_ride_bookings_passenger (passenger_id, created_at)
);
