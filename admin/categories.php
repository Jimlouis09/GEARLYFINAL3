<?php

require '../config/database.php';
require '../includes/auth.php';

requireRole('admin');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    checkCsrf();

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    // Save category if name is provided
    if ($name) {

        $s = $conn->prepare(
            'INSERT IGNORE INTO categories
                (category_name, description)
            VALUES (?, ?)'
        );

        $s->bind_param('ss', $name, $desc);
        $s->execute();

        flash('Category saved.');

    }

    header('Location: categories.php');
    exit;

}

// Retrieve categories and count equipment
$rows = $conn->query(
    'SELECT
        c.*,
        COUNT(e.equipment_id) equipment_count
    FROM categories c
    LEFT JOIN equipment e
        ON e.category_id = c.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name'
);

$pageTitle = 'Categories';

require '../includes/header.php';

?>

<h2 class="fw-bold">
    Equipment categories
</h2>

<div class="panel mb-4">

    <form method="post" class="row g-2">

        <input
            type="hidden"
            name="csrf"
            value="<?= csrf() ?>"
        >

        <div class="col-md-4">

            <input
                class="form-control"
                name="name"
                placeholder="Category name"
                required
            >

        </div>

        <div class="col-md-6">

            <input
                class="form-control"
                name="description"
                placeholder="Description"
            >

        </div>

        <div class="col-md-2">

            <button class="btn btn-primary w-100">
                Add
            </button>

        </div>

    </form>

</div>

<div class="panel">

    <table class="table">

        <tr>
            <th>Category</th>
            <th>Description</th>
            <th>Equipment</th>
        </tr>

        <?php while ($c = $rows->fetch_assoc()): ?>

            <tr>

                <td>
                    <?= h($c['category_name']) ?>
                </td>

                <td>
                    <?= h($c['description']) ?>
                </td>

                <td>
                    <?= (int)$c['equipment_count'] ?>
                </td>

            </tr>

        <?php endwhile; ?>

    </table>

</div>

<?php require '../includes/footer.php'; ?>
