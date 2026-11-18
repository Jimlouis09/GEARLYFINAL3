<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('customer');

$id = (int)($_GET['id'] ?? 0);

$s = $conn->prepare("SELECT * FROM equipment WHERE equipment_id=? AND status='active' AND condition_status='good'");
$s->bind_param('i', $id);
$s->execute();

$e = $s->get_result()->fetch_assoc();

if (!$e) {
    flash('Equipment is unavailable.', 'danger');
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $address = trim($_POST['delivery_address'] ?? '');
    $today = date('Y-m-d');

    if (!$start || !$end || $start < $today || $end < $start || !$address) {
        $error = 'Choose valid rental dates and provide a delivery address.';
    } else {
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
        $total = $days * $qty * (float)$e['price_per_day'];

        $conn->begin_transaction();

        try {
            $lock = $conn->prepare(
                'SELECT equipment_id FROM equipment WHERE equipment_id=? FOR UPDATE'
            );
            $lock->bind_param('i', $id);
            $lock->execute();

            // Retrieve and consume the SELECT result before running another query.
            $lockResult = $lock->get_result();
            $lockResult->fetch_assoc();
            $lockResult->free();
            $lock->close();

            $q = $conn->prepare("SELECT COALESCE(SUM(bi.quantity),0) AS booked FROM booking_items bi JOIN bookings b ON b.booking_id=bi.booking_id WHERE bi.equipment_id=? AND b.booking_status NOT IN ('cancelled','completed') AND b.start_date<=? AND b.end_date>=?");
            $q->bind_param('iss', $id, $end, $start);
            $q->execute();

            $booked = (int)$q->get_result()->fetch_assoc()['booked'];

            if ($booked + $qty > (int)$e['total_quantity']) {
                throw new Exception('Not enough equipment is available for those dates.');
            }

            $code = 'GR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $b = $conn->prepare("INSERT INTO bookings(booking_code,user_id,start_date,end_date,delivery_address,total_amount,booking_status) VALUES(?,?,?,?,?,?,'pending_payment')");
            $b->bind_param('sisssd', $code, $_SESSION['user_id'], $start, $end, $address, $total);
            $b->execute();

            $bid = $conn->insert_id;

            $it = $conn->prepare('INSERT INTO booking_items(booking_id,equipment_id,quantity,price_per_day,rental_days,subtotal) VALUES(?,?,?,?,?,?)');

            $rate = (float)$e['price_per_day'];
            $sub = $total;

            $it->bind_param('iiidid', $bid, $id, $qty, $rate, $days, $sub);
            $it->execute();

            $d = $conn->prepare("INSERT INTO deliveries(booking_id,delivery_address) VALUES(?,?)");
            $d->bind_param('is', $bid, $address);
            $d->execute();

            $conn->commit();

            header('Location: payment.php?id=' . $bid);
            exit;

        } catch (Throwable $ex) {
            $conn->rollback();
            $error = $ex->getMessage();
        }
    }
}

$pageTitle = 'Book equipment';

require '../includes/header.php';

?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">

                <a href="../index.php" class="small">← Back to catalog</a>

                <h2 class="fw-bold mt-3">
                    Rent <?= h($e['equipment_name']) ?>
                </h2>

                <p class="text-secondary">
                    Daily rate:
                    <strong class="text-primary">
                        <?= money($e['price_per_day']) ?>
                    </strong>
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post">

                    <input type="hidden" name="csrf" value="<?= csrf() ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start date</label>
                            <input
                                class="form-control"
                                type="date"
                                name="start_date"
                                min="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">End date</label>
                            <input
                                class="form-control"
                                type="date"
                                name="end_date"
                                min="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input
                            class="form-control"
                            type="number"
                            name="quantity"
                            min="1"
                            max="<?= (int)$e['total_quantity'] ?>"
                            value="1"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Delivery address</label>
                        <textarea
                            class="form-control"
                            name="delivery_address"
                            required
                        ></textarea>
                    </div>

                    <div class="alert alert-info small">
                        Rental days are counted inclusively (start date and end date). Final availability is checked again when you submit.
                    </div>

                    <button class="btn btn-primary w-100">
                        Continue to payment
                    </button>

                </form>

            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>