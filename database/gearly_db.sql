    CREATE DATABASE IF NOT EXISTS gearly_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

    USE gearly_db;

    SET FOREIGN_KEY_CHECKS = 0;

    DROP TABLE IF EXISTS
        rental_events,
        rental_charges,
        deliveries,
        verification_codes,
        rental_contracts,
        payments,
        booking_items,
        bookings,
        equipment,
        categories,
        users;

    SET FOREIGN_KEY_CHECKS = 1;

    CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(30),
        address TEXT,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    CREATE TABLE equipment (
        equipment_id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NULL,
        equipment_name VARCHAR(150) NOT NULL,
        description TEXT,
        price_per_day DECIMAL(10,2) NOT NULL,
        total_quantity INT NOT NULL DEFAULT 1,
        image VARCHAR(255),
        condition_status ENUM('good', 'damaged', 'maintenance') DEFAULT 'good',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(category_id) REFERENCES categories(category_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;

    CREATE TABLE bookings (
        booking_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_code VARCHAR(50) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        delivery_address TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        booking_status ENUM('pending', 'pending_payment', 'confirmed', 'ready_for_pickup', 'rented', 'returned', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(user_id)
    ) ENGINE=InnoDB;

    CREATE TABLE booking_items (
        booking_item_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        equipment_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price_per_day DECIMAL(10,2) NOT NULL,
        rental_days INT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
        FOREIGN KEY(equipment_id) REFERENCES equipment(equipment_id)
    ) ENGINE=InnoDB;

    CREATE TABLE payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        reference_number VARCHAR(100),
        proof_of_payment VARCHAR(255),
        payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        verified_by INT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id),
        FOREIGN KEY(verified_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;

    CREATE TABLE rental_contracts (
        contract_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL UNIQUE,
        contract_number VARCHAR(100) NOT NULL UNIQUE,
        contract_content MEDIUMTEXT,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id)
    ) ENGINE=InnoDB;

    CREATE TABLE verification_codes (
        code_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        purpose ENUM('pickup', 'return') NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id),
        INDEX(booking_id, purpose)
    ) ENGINE=InnoDB;

    CREATE TABLE deliveries (
        delivery_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL UNIQUE,
        delivery_address TEXT NOT NULL,
        delivery_status ENUM('pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
        delivered_at DATETIME NULL,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id)
    ) ENGINE=InnoDB;

    CREATE TABLE rental_charges (
        charge_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        charge_type ENUM('overdue', 'damage', 'other') NOT NULL,
        description TEXT,
        amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id)
    ) ENGINE=InnoDB;

    CREATE TABLE rental_events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        event_type ENUM('pickup', 'return') NOT NULL,
        verified_by INT NULL,
        event_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        condition_notes TEXT,
        FOREIGN KEY(booking_id) REFERENCES bookings(booking_id),
        FOREIGN KEY(verified_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB;

    INSERT INTO categories(category_name, description)
    VALUES
        ('Power Tools', 'Electric and handheld tools'),
        ('Construction', 'Construction and site equipment'),
        ('Photography', 'Camera and lighting equipment'),
        ('Audio', 'Sound and event equipment'),
        ('Outdoor', 'Camping and outdoor equipment');