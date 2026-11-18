<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('admin');

$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    checkCsrf();

    $action = $_POST['action'] ?? '';

    // DELETE EQUIPMENT
    if ($action === 'delete') {

        $id = (int)$_POST['id'];

        try {

            $s = $conn->prepare(
                'DELETE FROM equipment
                WHERE equipment_id = ?'
            );

            $s->bind_param('i', $id);
            $s->execute();

            flash('Equipment deleted.');

        } catch (Throwable $e) {

            flash(
                'Cannot delete equipment already used in bookings. Set it inactive instead.',
                'warning'
            );

        }

        header('Location: equipment.php');
        exit;

    }

    // SAVE EQUIPMENT
    if ($action === 'save') {

    $id = (int)($_POST['id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $cat = (int)($_POST['category_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');  
    $price = (float)($_POST['price'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 1);

    $condition = $_POST['condition_status'] ?? 'good';
    $status = $_POST['status'] ?? 'active';

    // VALIDATE
    if (!$name || $price < 0 || $qty < 1) {

        $error = 'Please enter valid equipment information.';

    } else {

        $imageName = null;
        $uploadError = '';

        // HANDLE IMAGE UPLOAD
        if (
            isset($_FILES['equipment_image']) &&
            $_FILES['equipment_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['equipment_image']['error'] !== UPLOAD_ERR_OK) {

                $uploadError = 'Image upload failed.';

            } elseif ($_FILES['equipment_image']['size'] > 5 * 1024 * 1024) {

                $uploadError = 'Image must not exceed 5MB.';

            } else {

                $tmp = $_FILES['equipment_image']['tmp_name'];
                $imageInfo = @getimagesize($tmp);
                $mime = $imageInfo['mime'] ?? '';

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!$imageInfo || !isset($allowed[$mime])) {

                    $uploadError = 'Only JPG, PNG and WEBP images are allowed.';

                } else {

                    $uploadDir = __DIR__ . '/../uploads/equipment/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $imageName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

                    if (!move_uploaded_file($tmp, $uploadDir . $imageName)) {

                        $uploadError = 'Unable to save uploaded image.';
                        $imageName = null;

                    }

                }

            }

        }

        if ($uploadError) {

            $error = $uploadError;

        } else {

            // UPDATE EXISTING EQUIPMENT
            if ($id) {

                if ($imageName !== null) {

                    $s = $conn->prepare(
                        'UPDATE equipment SET
                            category_id = NULLIF(?, 0),
                            equipment_name = ?,
                            description = ?,
                            price_per_day = ?,
                            total_quantity = ?,
                            image = ?,
                            condition_status = ?,
                            status = ?
                        WHERE equipment_id = ?'
                    );

                    $s->bind_param(
                        'issdisssi',
                        $cat,
                        $name,
                        $desc,
                        $price,
                        $qty,
                        $imageName,
                        $condition,
                        $status,
                        $id
                    );

                } else {

                    $s = $conn->prepare(
                        'UPDATE equipment SET
                            category_id = NULLIF(?, 0),
                            equipment_name = ?,
                            description = ?,
                            price_per_day = ?,
                            total_quantity = ?,
                            condition_status = ?,
                            status = ?
                        WHERE equipment_id = ?'
                    );

                    $s->bind_param(
                        'issdissi',
                        $cat,
                        $name,
                        $desc,
                        $price,
                        $qty,
                        $condition,
                        $status,
                        $id
                    );

                }

            }

            // ADD NEW EQUIPMENT
            else {

                $s = $conn->prepare(
                    'INSERT INTO equipment (
                        category_id,
                        equipment_name,
                        description,
                        price_per_day,
                        total_quantity,
                        image,
                        condition_status,
                        status
                    )
                    VALUES (
                        NULLIF(?, 0),
                        ?, ?, ?, ?, ?, ?, ?
                    )'
                );

                $s->bind_param(
                    'issdisss',
                    $cat,
                    $name,
                    $desc,
                    $price,
                    $qty,
                    $imageName,
                    $condition,
                    $status
                );

            }

            $s->execute();

            flash('Equipment saved.');

            header('Location: equipment.php');
            exit;

        }

    }

}

}


// GET EQUIPMENT FOR EDITING
$edit = null;

if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    $s = $conn->prepare(
        'SELECT *
        FROM equipment
        WHERE equipment_id = ?'
    );

    $s->bind_param('i', $id);
    $s->execute();

    $edit = $s->get_result()->fetch_assoc();

}

// GET ALL CATEGORIES
$cats = $conn->query(
    'SELECT *
    FROM categories
    ORDER BY category_name'
);

// GET ALL EQUIPMENT
$rows = $conn->query(
    'SELECT
        e.*,
        c.category_name
    FROM equipment e
    LEFT JOIN categories c
        ON c.category_id = e.category_id
    ORDER BY e.equipment_id DESC'
);

$pageTitle = 'Manage equipment';

require '../includes/header.php';

?>

<h2 class="fw-bold">
    Equipment management
</h2>

<?php if ($error): ?>

    <div class="alert alert-danger">
        <?= h($error) ?>
    </div>

<?php endif; ?>

<!-- ADD / EDIT EQUIPMENT FORM -->

<div class="panel mb-4">

    <h5>
        <?= $edit ? 'Edit equipment' : 'Add equipment' ?>
    </h5>

    <form method="post" enctype="multipart/form-data">

         <!-- DESCRIPTION -->
         <!-- EQUIPMENT PHOTO -->

<div class="col-12">

    <label class="form-label">
        Upload Equipment Photo
    </label>

    <input
        type="file"
        class="form-control"
        name="equipment_image"
        id="equipment_image"
        accept="image/jpeg,image/png,image/webp"
    >

    <small class="text-muted">
        Accepted formats: JPG, PNG, WEBP. Maximum 5MB.
    </small>

    <div class="mt-3">
        <img
            id="imagePreview"
            src="<?= !empty($edit['image'])
                ? '../uploads/equipment/' . h($edit['image'])
                : '' ?>"
            style="<?= !empty($edit['image'])
                ? 'display:block;'
                : 'display:none;' ?> width:200px; height:150px; object-fit:cover; border-radius:10px;"
        >
    </div>

</div>
        <input
            type="hidden"
            name="csrf"
            value="<?= csrf() ?>"
        >

        <input
            type="hidden"
            name="action"
            value="save"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int)($edit['equipment_id'] ?? 0) ?>"
        >

        <div class="row g-3">

            <!-- EQUIPMENT NAME -->

            <div class="col-md-6">

                <label class="form-label">
                    Equipment name
                </label>

                <input
                    class="form-control"
                    name="name"
                    value="<?= h($edit['equipment_name'] ?? '') ?>"
                    required
                >

            </div>

            <!-- CATEGORY -->

            <div class="col-md-6">

                <label class="form-label">
                    Category
                </label>

                <select
                    class="form-select"
                    name="category_id"
                >

                    <option value="0">
                        Uncategorized
                    </option>

                    <?php while ($c = $cats->fetch_assoc()): ?>

                        <option
                            value="<?= $c['category_id'] ?>"
                            <?= ($edit['category_id'] ?? 0) == $c['category_id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($c['category_name']) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <!-- PRICE PER DAY -->

            <div class="col-md-6">

                <label class="form-label">
                    Price per day (₱)
                </label>

                <input
                    class="form-control"
                    type="number"
                    min="0"
                    step="0.01"
                    name="price"
                    value="<?= h($edit['price_per_day'] ?? '') ?>"
                    required
                >

            </div>

            <!-- TOTAL QUANTITY -->

            <div class="col-md-6">

                <label class="form-label">
                    Total quantity
                </label>

                <input
                    class="form-control"
                    type="number"
                    min="1"
                    name="quantity"
                    value="<?= h($edit['total_quantity'] ?? 1) ?>"
                    required
                >

            </div>

            <!-- DESCRIPTION -->

            <div class="col-12">

                <label class="form-label">
                    Description
                </label>

                <textarea
                    class="form-control"
                    name="description"
                ><?= h($edit['description'] ?? '') ?></textarea>

            </div>

            <!-- EQUIPMENT CONDITION -->

            <div class="col-md-6">

                <label class="form-label">
                    Condition
                </label>

                <select
                    class="form-select"
                    name="condition_status"
                >

                    <?php foreach ([
                        'good',
                        'damaged',
                        'maintenance'
                    ] as $v): ?>

                        <option
                            <?= ($edit['condition_status'] ?? 'good') === $v
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $v ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- EQUIPMENT STATUS -->

            <div class="col-md-6">

                <label class="form-label">
                    Status
                </label>

                <select
                    class="form-select"
                    name="status"
                >

                    <option
                        value="active"
                        <?= ($edit['status'] ?? 'active') === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= ($edit['status'] ?? '') === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

        </div>

        <button class="btn btn-primary mt-3">
            Save equipment
        </button>

        <?php if ($edit): ?>

            <a
                class="btn btn-outline-secondary mt-3"
                href="equipment.php"
            >
                Cancel
            </a>

        <?php endif; ?>

    </form>

</div>

<!-- EQUIPMENT LIST -->

<div class="panel">

    <h5>Equipment list</h5>

    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Daily rate</th>
                    <th>Qty</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

                <?php while ($e = $rows->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= h($e['equipment_name']) ?>
                        </td>

                        <td>
                            <?= h($e['category_name'] ?? '—') ?>
                        </td>

                        <td>
                            <?= money($e['price_per_day']) ?>
                        </td>

                        <td>
                            <?= (int)$e['total_quantity'] ?>
                        </td>

                        <td>
                            <?= h($e['condition_status']) ?>
                        </td>

                        <td>
                            <?= h($e['status']) ?>
                        </td>

                        <td class="text-nowrap">

                            <!-- EDIT BUTTON -->

                            <a
                                class="btn btn-sm btn-outline-primary"
                                href="?edit=<?= $e['equipment_id'] ?>"
                            >
                                Edit
                            </a>

                            <!-- DELETE BUTTON -->

                            <form
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Delete this equipment?')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= csrf() ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $e['equipment_id'] ?>"
                                >

                                <button class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<script>
document.getElementById('equipment_image').addEventListener('change', function(event) {

    const file = event.target.files[0];

    if (file) {

        const preview = document.getElementById('imagePreview');

        preview.src = URL.createObjectURL(file);

        preview.style.display = 'block';

    }

});
</script>

<?php require '../includes/footer.php'; ?>