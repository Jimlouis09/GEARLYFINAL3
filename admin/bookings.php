<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    checkCsrf();

    $id = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Get booking and customer information
    $q = $conn->prepare(
        'SELECT
            b.*,
            u.full_name,
            u.email
        FROM bookings b
        JOIN users u ON u.user_id = b.user_id
        WHERE b.booking_id = ?'
    );

    $q->bind_param('i', $id);
    $q->execute();

    $b = $q->get_result()->fetch_assoc();

    if (!$b) {
        flash('Booking not found.', 'danger');
        header('Location: bookings.php');
        exit;
    }

    // VERIFY PAYMENT
    if ($action === 'verify_payment') {

        $p = $conn->prepare(
            "UPDATE payments
            SET payment_status = 'verified',
                verified_by = ?
            WHERE booking_id = ?"
        );

        $p->bind_param('ii', $_SESSION['user_id'], $id);
        $p->execute();

        $conn->query(
            "UPDATE bookings
            SET booking_status = 'confirmed'
            WHERE booking_id = $id"
        );

        // Get booked equipment
        $items = $conn->prepare(
            'SELECT
                bi.*,
                e.equipment_name
            FROM booking_items bi
            JOIN equipment e ON e.equipment_id = bi.equipment_id
            WHERE bi.booking_id = ?'
        );

        $items->bind_param('i', $id);
        $items->execute();

        $contractItems = $items->get_result();

        // Generate rental contract
        $contract = "GEARLY RENTAL AGREEMENT
Contract: GEARLY-$id-" . date('Ymd') . "
Booking: {$b['booking_code']}
Customer: {$b['full_name']} ({$b['email']})
Rental period: {$b['start_date']} to {$b['end_date']}
Delivery address: {$b['delivery_address']}
Items:
";

        while ($it = $contractItems->fetch_assoc()) {

            $contract .= "- {$it['equipment_name']} x{$it['quantity']} for {$it['rental_days']} day(s): PHP "
                . number_format($it['subtotal'], 2) . "
";

        }

        $contract .= "
Rental amount: PHP "
            . number_format($b['total_amount'], 2) . "
Customer agrees to return the equipment in the condition received. Late returns and damage may incur additional charges based on the recorded rental conditions.
This electronically generated contract is for the GEARLY capstone demonstration.";

        // Save contract
        $num = 'GR-CON-' . $id . '-' . date('Ymd');

        $c = $conn->prepare(
            'INSERT INTO rental_contracts
                (booking_id, contract_number, contract_content)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                contract_content = VALUES(contract_content)'
        );

        $c->bind_param('iss', $id, $num, $contract);
        $c->execute();

        // Generate pickup verification code
        $code = (string)random_int(100000, 999999);

        $hash = password_hash($code, PASSWORD_DEFAULT);

        $exp = date('Y-m-d H:i:s', time() + 1800);

        $v = $conn->prepare(
            "INSERT INTO verification_codes
                (booking_id, purpose, code_hash, expires_at)
            VALUES (?, 'pickup', ?, ?)"
        );

        $v->bind_param('iss', $id, $hash, $exp);
        $v->execute();

        // Send pickup code through email
        $sent = sendCodeEmail(
            $b['email'],
            $b['full_name'],
            $code,
            'pickup'
        );

        $_SESSION['last_demo_code'] = $sent ? '' : $code;

        flash(
            'Payment verified, contract generated and pickup code created.'
            . ($sent
                ? ' Email sent.'
                : ' Email could not be sent by PHP mail(); demo code: ' . $code)
        );

    }
    // REGENERATE PICKUP VERIFICATION CODE
    elseif ($action === 'issue_pickup') {

        // Only allow pickup-code regeneration after payment is confirmed
        if ($b['booking_status'] !== 'confirmed') {
            flash('Pickup code can only be regenerated before pickup verification.', 'warning');
        } else {

            // Expire previous unused pickup codes
            $old = $conn->prepare(
                "UPDATE verification_codes
                 SET expires_at = NOW()
                 WHERE booking_id = ?
                   AND purpose = 'pickup'
                   AND verified_at IS NULL"
            );

            $old->bind_param('i', $id);
            $old->execute();
            $old->close();

            // Generate a fresh six-digit code
            $code = (string) random_int(100000, 999999);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $exp = date('Y-m-d H:i:s', time() + 1800);

            $v = $conn->prepare(
                "INSERT INTO verification_codes
                    (booking_id, purpose, code_hash, expires_at)
                 VALUES (?, 'pickup', ?, ?)"
            );

            $v->bind_param('iss', $id, $hash, $exp);
            $v->execute();
            $v->close();

            // Email the new pickup code
            $sent = sendCodeEmail(
                $b['email'],
                $b['full_name'],
                $code,
                'pickup'
            );

            flash(
                $sent
                    ? 'A new pickup verification code has been emailed. The previous unused code is no longer valid.'
                    : 'New pickup code was created, but the email could not be sent. Check the PHP error log.',
                $sent ? 'success' : 'danger'
            );
        }
    }
    
    // REJECT PAYMENT
    elseif ($action === 'reject_payment') {

        $p = $conn->prepare(
            "UPDATE payments
            SET payment_status = 'rejected',
                verified_by = ?
            WHERE booking_id = ?"
        );

        $p->bind_param('ii', $_SESSION['user_id'], $id);
        $p->execute();

        flash(
            'Payment rejected. Customer may resubmit proof.',
            'warning'
        );

    }

    // UPDATE DELIVERY STATUS
    elseif ($action === 'delivery') {

        $status = $_POST['delivery_status'] ?? 'pending';

        $d = $conn->prepare(
            'UPDATE deliveries
            SET delivery_status = ?,
                delivered_at = IF(? = "delivered", NOW(), delivered_at)
            WHERE booking_id = ?'
        );

        $d->bind_param('ssi', $status, $status, $id);
        $d->execute();

        flash('Delivery status updated.');

    }

    // GENERATE RETURN VERIFICATION CODE
    elseif ($action === 'issue_return') {

        $code = (string)random_int(100000, 999999);

        $hash = password_hash($code, PASSWORD_DEFAULT);

        $exp = date('Y-m-d H:i:s', time() + 1800);

        $v = $conn->prepare(
            "INSERT INTO verification_codes
                (booking_id, purpose, code_hash, expires_at)
            VALUES (?, 'return', ?, ?)"
        );

        $v->bind_param('iss', $id, $hash, $exp);
        $v->execute();

        // Send return code through email
        $sent = sendCodeEmail(
            $b['email'],
            $b['full_name'],
            $code,
            'return'
        );

        flash(
            'Return code created.'
            . ($sent
                ? ' Email sent.'
                : ' Email could not be sent; demo code: ' . $code)
        );

    }

    // VERIFY PICKUP OR RETURN CODE
    elseif ($action === 'verify_code') {

        $purpose = $_POST['purpose'] ?? 'pickup';

        $code = trim($_POST['code'] ?? '');

        $v = $conn->prepare(
            'SELECT *
            FROM verification_codes
            WHERE booking_id = ?
                AND purpose = ?
                AND verified_at IS NULL
            ORDER BY code_id DESC
            LIMIT 1'
        );

        $v->bind_param('is', $id, $purpose);
        $v->execute();

        $record = $v->get_result()->fetch_assoc();

        // Check verification code
        if (
            !$record ||
            strtotime($record['expires_at']) < time() ||
            !password_verify($code, $record['code_hash'])
        ) {

            flash(
                'Invalid or expired verification code.',
                'danger'
            );

        } else {

            $conn->begin_transaction();

            try {

                // Mark verification code as used
                $u = $conn->prepare(
                    'UPDATE verification_codes
                    SET verified_at = NOW()
                    WHERE code_id = ?'
                );

                $u->bind_param('i', $record['code_id']);
                $u->execute();

                // Record pickup or return event
                $event = $conn->prepare(
                    'INSERT INTO rental_events
                        (booking_id, event_type, verified_by, condition_notes)
                    VALUES (?, ?, ?, ?)'
                );

                $notes = trim($_POST['notes'] ?? '');

                $event->bind_param(
                    'isis',
                    $id,
                    $purpose,
                    $_SESSION['user_id'],
                    $notes
                );

                $event->execute();

                // Update booking status
                $newStatus = $purpose === 'pickup'
                    ? 'rented'
                    : 'returned';

                $up = $conn->prepare(
                    'UPDATE bookings
                    SET booking_status = ?
                    WHERE booking_id = ?'
                );

                $up->bind_param('si', $newStatus, $id);
                $up->execute();

                // Calculate overdue charges after return
                if ($purpose === 'return') {

                    $late = max(
                        0,
                        (int)floor(
                            (
                                strtotime(date('Y-m-d')) -
                                strtotime($b['end_date'])
                            ) / 86400
                        )
                    );

                    if ($late > 0) {

                        $base = $conn->prepare(
                            'SELECT SUM(subtotal) total
                            FROM booking_items
                            WHERE booking_id = ?'
                        );

                        $base->bind_param('i', $id);
                        $base->execute();

                        $daily = (float)$base->get_result()
                            ->fetch_assoc()['total']
                            / max(
                                1,
                                (int)(
                                    (
                                        new DateTime($b['start_date'])
                                    )->diff(
                                        new DateTime($b['end_date'])
                                    )->days + 1
                                )
                            );

                        $fee = $late * $daily;

                        $desc = "$late overdue day(s), automatically computed at one regular daily rental rate per day.";

                        $ch = $conn->prepare(
                            "INSERT INTO rental_charges
                                (booking_id, charge_type, description, amount)
                            VALUES (?, 'overdue', ?, ?)"
                        );

                        $ch->bind_param('isd', $id, $desc, $fee);
                        $ch->execute();

                    }

                    // Mark booking as completed
                    $conn->query(
                        "UPDATE bookings
                        SET booking_status = 'completed'
                        WHERE booking_id = $id"
                    );

                }

                $conn->commit();

                flash(
                    $purpose === 'pickup'
                        ? 'Pickup verified. Rental is now active.'
                        : 'Return verified. Rental completed and overdue fee calculated if applicable.'
                );

            } catch (Throwable $e) {

                $conn->rollback();

                flash(
                    'Could not record verification: ' . $e->getMessage(),
                    'danger'
                );

            }

        }

    }

    // RECORD DAMAGE OR OTHER CHARGES
    elseif ($action === 'damage') {

        $amount = max(0, (float)($_POST['amount'] ?? 0));

        $desc = trim($_POST['description'] ?? '');

        if ($amount > 0 && $desc) {

            $ch = $conn->prepare(
                "INSERT INTO rental_charges
                    (booking_id, charge_type, description, amount)
                VALUES (?, 'damage', ?, ?)"
            );

            $ch->bind_param('isd', $id, $desc, $amount);
            $ch->execute();

            flash('Damage charge recorded.');

        } else {

            flash(
                'Enter a valid amount and damage description.',
                'danger'
            );

        }

    }

    header('Location: booking.php?id=' . $id);
    exit;

}

// GET ALL BOOKING RECORDS
$rows = $conn->query(
    'SELECT
        b.*,
        u.full_name,
        p.payment_status
    FROM bookings b
    JOIN users u ON u.user_id = b.user_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    ORDER BY b.created_at DESC'
);

$pageTitle = 'Bookings';

require '../includes/header.php';

?>

<h2 class="fw-bold">Booking management</h2>

<p class="text-secondary">
    Review payment proof, manage delivery, verify pickup and return.
</p>

<div class="panel">

    <div class="table-responsive">

        <table class="table align-middle">

            <thead>

                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th>Rental dates</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

                <?php while ($b = $rows->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= h($b['booking_code']) ?>
                        </td>

                        <td>
                            <?= h($b['full_name']) ?>
                        </td>

                        <td>
                            <?= h($b['start_date']) ?>

                            <br>

                            <?= h($b['end_date']) ?>
                        </td>

                        <td>
                            <?= money($b['total_amount']) ?>
                        </td>

                        <td>
                            <?= h($b['payment_status'] ?? '—') ?>
                        </td>

                        <td>
                            <?= h($b['booking_status'] === 'pending_payment'
                                ? 'pending'
                                : $b['booking_status']) ?>
                        </td>

                        <td>

                            <a
                                class="btn btn-sm btn-primary"
                                href="booking.php?id=<?= $b['booking_id'] ?>"
                            >
                                Manage
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '../includes/footer.php'; ?>
