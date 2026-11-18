<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('admin');

$id = (int)($_GET['id'] ?? 0);

$q = $conn->prepare(
    'SELECT
        b.*,
        u.full_name,
        u.email,
        u.phone,
        p.payment_id,
        p.payment_status,
        p.payment_method,
        p.reference_number,
        p.proof_of_payment,
        d.delivery_status
    FROM bookings b
    JOIN users u ON u.user_id = b.user_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    LEFT JOIN deliveries d ON d.booking_id = b.booking_id
    WHERE b.booking_id = ?'
);

$q->bind_param('i', $id);
$q->execute();

$bookingResult = $q->get_result();
$b = $bookingResult->fetch_assoc();
$bookingResult->free();
$q->close();

if (!$b) {
    exit('Booking not found.');
}

// Get booking items immediately

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
$itemsResult = $items->get_result();
$items->close();

// Get additional charges immediately

$charges = $conn->prepare(
    'SELECT *
    FROM rental_charges
    WHERE booking_id = ?'
);

$charges->bind_param('i', $id);
$charges->execute();
$chargesResult = $charges->get_result();
$charges->close();

// Get additional contract immediately

$contract = $conn->prepare(
    'SELECT *
    FROM rental_contracts
    WHERE booking_id = ?'
);

$contract->bind_param('i', $id);
$contract->execute();

$contractResult = $contract->get_result();
$ct = $contractResult->fetch_assoc();
$contractResult->free();
$contract->close();

$pageTitle = 'Manage booking';

require '../includes/header.php';

?>

<div class="d-flex justify-content-between">

    <h2 class="fw-bold">
        Manage <?= h($b['booking_code']) ?>
    </h2>

    <a href="bookings.php" class="btn btn-outline-secondary">
        Back
    </a>

</div>

<div class="row g-3 mt-1">

    <div class="col-lg-7">

        <div class="panel">

            <h5>Customer and rental</h5>

            <p>

                <strong>
                    <?= h($b['full_name']) ?>
                </strong>

                <br>

                <?= h($b['email']) ?>
                ·
                <?= h($b['phone']) ?>

                <br>

                <?= h($b['delivery_address']) ?>

            </p>

            <p>

                <?= h($b['start_date']) ?>
                to
                <?= h($b['end_date']) ?>

                ·

                <strong>
                    <?= money($b['total_amount']) ?>
                </strong>

            </p>

            <table class="table">

                <tr>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Days</th>
                    <th>Subtotal</th>
                </tr>

                <?php
                $ir = $itemsResult;

                while ($it = $ir->fetch_assoc()):
                ?>

                    <tr>

                        <td>
                            <?= h($it['equipment_name']) ?>
                        </td>

                        <td>
                            <?= $it['quantity'] ?>
                        </td>

                        <td>
                            <?= $it['rental_days'] ?>
                        </td>

                        <td>
                            <?= money($it['subtotal']) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            </table>

        </div>

        <div class="panel mt-3">

            <h5>Payment verification</h5>

            <p>
                Status:

                <strong>
                    <?= h($b['payment_status'] ?? 'Not submitted') ?>
                </strong>
            </p>

            <p>
                <?= h($b['payment_method'] ?? '') ?>

                · Reference:

                <?= h($b['reference_number'] ?? '') ?>
            </p>

            <?php if ($b['proof_of_payment']): ?>

                <a
                    target="_blank"
                    href="../uploads/<?= h($b['proof_of_payment']) ?>"
                >
                    View uploaded proof
                </a>

            <?php endif; ?>

            <?php if ($b['payment_status'] === 'pending'): ?>

                <div class="d-flex gap-2 mt-3">

                    <form method="post" action="bookings.php">

                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= csrf() ?>"
                        >

                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= $id ?>"
                        >

                        <button
                            class="btn btn-success"
                            name="action"
                            value="verify_payment"
                        >
                            Verify payment & generate contract/code
                        </button>

                    </form>

                    <form method="post" action="bookings.php">

                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= csrf() ?>"
                        >

                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= $id ?>"
                        >

                        <button
                            class="btn btn-outline-danger"
                            name="action"
                            value="reject_payment"
                        >
                            Reject
                        </button>

                    </form>

                </div>

            <?php endif; ?>

        </div>

        <?php if ($ct): ?>

            <div class="panel mt-3">

                <h5>
                    Generated contract:
                    <?= h($ct['contract_number']) ?>
                </h5>

                <pre class="contract-text"><?= h($ct['contract_content']) ?></pre>

                <button
                    class="btn btn-outline-primary btn-sm"
                    onclick="window.print()"
                >
                    Print contract
                </button>

            </div>

        <?php endif; ?>

    </div>

    <div class="col-lg-5">

        <div class="panel mb-3">

            <h5>Delivery management</h5>

            <p>
                Current:
                <?= h($b['delivery_status'] ?? 'pending') ?>
            </p>

            <form method="post" action="bookings.php">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= csrf() ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= $id ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="delivery"
                >

                <select
                    class="form-select mb-2"
                    name="delivery_status"
                >

                    <?php foreach ([
                        'pending',
                        'preparing',
                        'out_for_delivery',
                        'delivered',
                        'cancelled'
                    ] as $st): ?>

                        <option
                            <?= ($b['delivery_status'] ?? 'pending') === $st
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $st ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button class="btn btn-primary">
                    Update delivery
                </button>

            </form>

        </div>

        <div class="panel mb-3">

            <h5>Pickup / return verification</h5>
            
            <?php if ($b['booking_status'] === 'confirmed'): ?>

            <form method="post" action="bookings.php" class="mb-3">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= csrf() ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= $id ?>"
                >

                <button
                    class="btn btn-outline-success btn-sm"
                    name="action"
                    value="issue_pickup"
                >
                    Regenerate and email pickup code
                </button>

            </form>

            <?php endif; ?>

            <form
                method="post"
                action="bookings.php"
                class="mb-3"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= csrf() ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= $id ?>"
                >

                <button
                    class="btn btn-outline-primary btn-sm"
                    name="action"
                    value="issue_return"
                >
                    Generate and email return code
                </button>

            </form>

            <p class="small text-secondary">

                Enter the six-digit code sent to the customer's email.
                Codes expire after 30 minutes.

            </p>

            <form method="post" action="bookings.php">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= csrf() ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= $id ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="verify_code"
                >

                <select
                    class="form-select mb-2"
                    name="purpose"
                >

                    <option value="pickup">
                        Pickup
                    </option>

                    <option value="return">
                        Return
                    </option>

                </select>

                <input
                    class="form-control mb-2"
                    name="code"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="6-digit code"
                    required
                >

                <textarea
                    class="form-control mb-2"
                    name="notes"
                    placeholder="Condition notes / inspection observations"
                ></textarea>

                <button class="btn btn-success w-100">
                    Verify and record event
                </button>

            </form>

        </div>

        <div class="panel">

            <h5>Record damage / other charge</h5>

            <form method="post" action="bookings.php">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= csrf() ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= $id ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="damage"
                >

                <input
                    class="form-control mb-2"
                    type="number"
                    min="0.01"
                    step="0.01"
                    name="amount"
                    placeholder="Amount (₱)"
                    required
                >

                <textarea
                    class="form-control mb-2"
                    name="description"
                    placeholder="Damage description"
                    required
                ></textarea>

                <button class="btn btn-outline-danger">
                    Add damage charge
                </button>

            </form>

        </div>

        <div class="panel mt-3">

            <h5>Charges</h5>

            <?php

            $cr = $chargesResult;
            $sum = 0;

            while ($c = $cr->fetch_assoc()):

                $sum += $c['amount'];

            ?>

                <p>

                    <?= h($c['charge_type']) ?>

                    ·

                    <?= h($c['description']) ?>

                    <strong>
                        <?= money($c['amount']) ?>
                    </strong>

                </p>

            <?php endwhile; ?>

            <strong>
                Total additional charges:
                <?= money($sum) ?>
            </strong>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>
