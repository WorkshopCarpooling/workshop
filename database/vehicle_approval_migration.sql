-- Run once against the existing workshop database to add manual vehicle review.
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

ALTER TABLE rides
    ADD COLUMN vehicle_id INT NULL AFTER driver_id,
    ADD INDEX idx_rides_vehicle (vehicle_id),
    ADD CONSTRAINT fk_rides_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL;
