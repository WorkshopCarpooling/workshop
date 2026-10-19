-- Run once against an existing workshop database to save passenger pickup pins with seat requests.
ALTER TABLE ride_bookings
    ADD COLUMN pickup_label VARCHAR(180) NULL AFTER seats_requested,
    ADD COLUMN pickup_latitude DECIMAL(10,7) NULL AFTER pickup_label,
    ADD COLUMN pickup_longitude DECIMAL(10,7) NULL AFTER pickup_latitude;
