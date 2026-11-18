<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('customer');

$s = $conn->prepare("SELECT b.*,p.payment_status FROM bookings b LEFT JOIN payments p ON p.booking_id=b.booking_id WHERE b.user_id=? ORDER BY b.created_at DESC");
$s->bind_param('i', $_SESSION['user_id']);
$s->execute();

$rows = $s->get_result();

$pageTitle = 'My rentals';

require '../includes/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h2 class="fw-bold">My rentals</h2>

        <p class="text-secondary">
            Track bookings, payments and rental status.
        </p>
    </div>

    <a href="../index.php#equipment" class="btn btn-primary">
        Browse equipment
    </a>

</div>

<div class="table-responsive bg-white rounded-4 border">

    <table class="table align-middle mb-0">

        <thead>
            <tr>
                <th>Booking</th>
                <th>Rental dates</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>

        <tbody>

            <?php if ($rows->num_rows): ?>

                <?php while ($b = $rows->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <strong>
                                <?= h($b['booking_code']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= h($b['start_date']) ?> – <?= h($b['end_date']) ?>
                        </td>

                        <td>
                            <?= money($b['total_amount']) ?>
                        </td>

                        <td>
                            <?= h($b['payment_status'] ?? 'Not submitted') ?>
                        </td>

                        <td>
                            <span class="badge text-bg-info">
                                <?= h(str_replace('_', ' ', $b['booking_status'])) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                class="btn btn-sm btn-outline-primary"
                                href="booking.php?id=<?= $b['booking_id'] ?>"
                            >
                                Details
                            </a>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="text-center py-5 text-secondary">
                        You have no bookings yet.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php require '../includes/footer.php'; ?>