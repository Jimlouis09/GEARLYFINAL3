<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('customer');

$id = (int)($_GET['id'] ?? 0);

$q = $conn->prepare('SELECT b.* FROM bookings b WHERE b.booking_id=? AND b.user_id=?');
$q->bind_param('ii', $id, $_SESSION['user_id']);
$q->execute();

$b = $q->get_result()->fetch_assoc();

if (!$b) {
    http_response_code(404);
    exit('Booking not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $method = $_POST['payment_method'] ?? 'GCash';
    $ref = trim($_POST['reference_number'] ?? '');
    $file = null;

    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $error = 'Proof must be JPG, PNG or PDF.';
        } elseif ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            $error = 'Maximum upload size is 5MB.';
        } else {
            $file = 'payment_' . bin2hex(random_bytes(8)) . '.' . $ext;

            move_uploaded_file(
                $_FILES['proof']['tmp_name'],
                __DIR__ . '/../uploads/' . $file
            );
        }
    }

    if (!$error) {
        $p = $conn->prepare("INSERT INTO payments(booking_id,amount,payment_method,reference_number,proof_of_payment) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),payment_method=VALUES(payment_method),reference_number=VALUES(reference_number),proof_of_payment=VALUES(proof_of_payment),payment_status='pending',verified_by=NULL");

        $p->bind_param('idsss', $id, $b['total_amount'], $method, $ref, $file);
        $p->execute();

        flash('Payment proof submitted. Your booking will be confirmed after admin verification.');

        header('Location: booking.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Submit payment';

require '../includes/header.php';

?>

<div class="auth-card">

    <h2 class="fw-bold">Payment submission</h2>

    <p class="text-secondary">
        Booking <?= h($b['booking_code']) ?>
    </p>

    <h3 class="text-primary">
        <?= money($b['total_amount']) ?>
    </h3>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= h($error) ?>
        </div>

    <?php endif; ?>

    <div class="alert alert-info">
        Demo payment: pay using the payment instructions supplied by your administrator, then upload your receipt. No live payment gateway is connected.
    </div>

    <form method="post" enctype="multipart/form-data">

        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <label class="form-label">Payment method</label>

        <select class="form-select mb-3" name="payment_method">
            <option>GCash</option>
            <option>Bank Transfer</option>
            <option>Cash</option>
        </select>

        <label class="form-label">Reference number</label>

        <input
            class="form-control mb-3"
            name="reference_number"
            required
        >

        <label class="form-label">
            Proof of payment (JPG, PNG, PDF; max 5MB)
        </label>

        <input
            class="form-control mb-3"
            type="file"
            name="proof"
            accept=".jpg,.jpeg,.png,.pdf"
        >

        <button class="btn btn-primary w-100">
            Submit for verification
        </button>

    </form>

</div>

<?php require '../includes/footer.php'; ?>