<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('admin');

$stats = [];

foreach ([
    "SELECT COUNT(*) n FROM users WHERE role='customer'",
    "SELECT COUNT(*) n FROM equipment",
    "SELECT COUNT(*) n FROM bookings WHERE booking_status NOT IN ('completed','cancelled')",
    "SELECT COALESCE(SUM(total_amount),0) n FROM bookings WHERE booking_status='completed'"
] as $i => $sql) {
    $stats[$i] = $conn->query($sql)->fetch_assoc()['n'];
}

$recent = $conn->query(
    'SELECT b.*, u.full_name
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     ORDER BY b.created_at DESC
     LIMIT 8'
);

$pageTitle = 'Admin dashboard';

require '../includes/header.php';

?>

<h2 class="fw-bold">Admin dashboard</h2>

<p class="text-secondary">
    GEARLY rental operations overview.
</p>

<div class="row g-3 mb-4">

    <?php foreach ([
        'Customers',
        'Equipment',
        'Active bookings',
        'Completed rental value'
    ] as $i => $label): ?>

        <div class="col-6 col-lg-3">

            <div class="dashboard-card">

                <small class="text-secondary">
                    <?= $label ?>
                </small>

                <h3>
                    <?= $i === 3
                        ? money($stats[$i])
                        : number_format($stats[$i]) ?>
                </h3>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<div class="d-flex gap-2 mb-3 flex-wrap">

    <a class="btn btn-primary" href="equipment.php">
        Manage equipment
    </a>

    <a class="btn btn-outline-primary" href="categories.php">
        Categories
    </a>

    <a class="btn btn-outline-primary" href="bookings.php">
        Manage bookings
    </a>

</div>

<div class="panel">

    <h5>Recent bookings</h5>

    <div class="table-responsive">

        <table class="table">

            <thead>
                <tr>
                    <th>Code</th>
                    <th>Customer</th>
                    <th>Dates</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

                <?php while ($b = $recent->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= h($b['booking_code']) ?>
                        </td>

                        <td>
                            <?= h($b['full_name']) ?>
                        </td>

                        <td>
                            <?= h($b['start_date']) ?>
                            –
                            <?= h($b['end_date']) ?>
                        </td>

                        <td>
                            <?= money($b['total_amount']) ?>
                        </td>

                        <td>
                            <?= h($b['booking_status']) ?>
                        </td>

                        <td>
                            <a
                                class="btn btn-sm btn-outline-primary"
                                href="booking.php?id=<?= $b['booking_id'] ?>"
                            >
                                Open
                            </a>
                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require '../includes/footer.php'; ?>
