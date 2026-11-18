<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('customer');

$id = (int)($_GET['id'] ?? 0);

$q = $conn->prepare('SELECT b.*,p.payment_status,p.payment_method,p.reference_number,p.proof_of_payment,d.delivery_status,rc.contract_number,rc.contract_content FROM bookings b LEFT JOIN payments p ON p.booking_id=b.booking_id LEFT JOIN deliveries d ON d.booking_id=b.booking_id LEFT JOIN rental_contracts rc ON rc.booking_id=b.booking_id WHERE b.booking_id=? AND b.user_id=?');
$q->bind_param('ii', $id, $_SESSION['user_id']);
$q->execute();

$b = $q->get_result()->fetch_assoc();

if (!$b) {
    exit('Booking not found.');
}

$items = $conn->prepare('SELECT bi.*,e.equipment_name FROM booking_items bi JOIN equipment e ON e.equipment_id=bi.equipment_id WHERE bi.booking_id=?');
$items->bind_param('i', $id);
$items->execute();

$itemRows = $items->get_result();

$charges = $conn->prepare('SELECT * FROM rental_charges WHERE booking_id=?');
$charges->bind_param('i', $id);
$charges->execute();

$chargeRows = $charges->get_result();

$pageTitle = 'Booking details';

require '../includes/header.php';

?>

<div class="d-flex justify-content-between">

    <div>
        <h2 class="fw-bold">
            Booking <?= h($b['booking_code']) ?>
        </h2>

        <span class="badge text-bg-primary">
            <?= h(str_replace('_', ' ', $b['booking_status'])) ?>
        </span>
    </div>

    <a class="btn btn-outline-secondary" href="dashboard.php">
        Back
    </a>

</div>

<div class="row g-4 mt-1">

    <div class="col-lg-7">

        <div class="panel">

            <h5>Rental details</h5>

            <p>
                <?= h($b['start_date']) ?> through <?= h($b['end_date']) ?>
                · <?= h($b['delivery_address']) ?>
            </p>

            <table class="table">

                <tr>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Days</th>
                    <th>Subtotal</th>
                </tr>

                <?php while ($i = $itemRows->fetch_assoc()): ?>

                    <tr>
                        <td><?= h($i['equipment_name']) ?></td>
                        <td><?= (int)$i['quantity'] ?></td>
                        <td><?= (int)$i['rental_days'] ?></td>
                        <td><?= money($i['subtotal']) ?></td>
                    </tr>

                <?php endwhile; ?>

            </table>

            <h4 class="text-primary">
                Rental total: <?= money($b['total_amount']) ?>
            </h4>

        </div>

        <?php if ($b['contract_content']): ?>

            <div class="panel mt-3">

                <h5>
                    Rental contract <?= h($b['contract_number']) ?>
                </h5>

                <pre class="contract-text"><?= h($b['contract_content']) ?></pre>

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

            <h5>Payment</h5>

            <p>
                Status:
                <strong>
                    <?= h($b['payment_status'] ?? 'Not submitted') ?>
                </strong>
            </p>

            <?php if (!$b['payment_status'] || $b['payment_status'] === 'rejected'): ?>

                <a class="btn btn-primary" href="payment.php?id=<?= $id ?>">
                    Submit payment
                </a>

            <?php endif; ?>

        </div>

        <div class="panel mb-3">

            <h5>Delivery</h5>

            <p>
                <?= h(str_replace('_', ' ', $b['delivery_status'] ?? 'pending')) ?>
            </p>

        </div>

        <div class="panel">

            <h5>Additional charges</h5>

            <?php
            $sum = 0;

            while ($c = $chargeRows->fetch_assoc()):
                $sum += $c['amount'];
            ?>

                <p>
                    <?= h($c['charge_type']) ?>:
                    <?= money($c['amount']) ?>
                    – <?= h($c['description']) ?>
                </p>

            <?php endwhile; ?>

            <strong>
                Additional total: <?= money($sum) ?>
            </strong>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>