-- Run once against an existing database that already has the rides table.
ALTER TABLE rides
    ADD COLUMN vehicle_type VARCHAR(80) NOT NULL DEFAULT '' AFTER vehicle_description,
    ADD COLUMN vehicle_color VARCHAR(60) NOT NULL DEFAULT '' AFTER vehicle_type,
    ADD COLUMN vehicle_plate VARCHAR(20) NOT NULL DEFAULT '' AFTER vehicle_color;
