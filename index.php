<?php

require 'config/database.php';
require 'includes/auth.php';

$items = $conn->query("SELECT e.*,c.category_name FROM equipment e LEFT JOIN categories c ON c.category_id=e.category_id WHERE e.status='active' AND e.condition_status='good' ORDER BY e.created_at DESC");

$pageTitle = 'Rent equipment';

require 'includes/header.php';

?>

<section class="hero-section rounded-4 mb-5"
    style="
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
    ">

    <div class="hero-content"
        style="
            width: 100%;
            max-width: 750px;
            margin: 0 auto;
            text-align: center;
        ">

        <span class="badge bg-primary-subtle text-primary mb-3">
            EQUIPMENT RENTAL PLATFORM
        </span>

        <h1 class="display-4 fw-bold text-center">
            Rent what you need.<br>
            When you need it.
        </h1>

        <p class="hero-description mx-auto mt-3 text-center"
            style="
                max-width: 560px;
                margin-left: auto;
                margin-right: auto;
            ">
            Discover useful equipment, choose your rental dates, and have your order delivered to your doorstep.
        </p>

        <a class="btn btn-primary btn-lg mt-3" href="#equipment">
            Explore equipment
        </a>

    </div>

</section>

<div
    id="equipment"
    class="d-flex justify-content-between align-items-end mb-3"
>

    <div>
        <h2 class="fw-bold">
            Available equipment
        </h2>

        <p class="text-secondary mb-0">
            Browse our rental catalog.
        </p>
    </div>

</div>

<div class="row g-4">

    <?php if ($items->num_rows): ?>

        <?php while ($e = $items->fetch_assoc()): ?>

            <div class="col-sm-6 col-lg-4">

                <div class="card equipment-card h-100">

                    <?php if ($e['image']): ?>

                        <img
                            class="equipment-image"
                            src="uploads/equipment/<?= h($e['image']) ?>"
                            alt="<?= h($e['equipment_name']) ?>"
                        >

                    <?php else: ?>

                        <div class="equipment-placeholder">
                            GEARLY EQUIPMENT
                        </div>

                    <?php endif; ?>

                    <div class="card-body">

                        <small class="text-primary">
                            <?= h($e['category_name'] ?? 'General') ?>
                        </small>

                        <h5 class="fw-bold mt-2">
                            <?= h($e['equipment_name']) ?>
                        </h5>

                        <p class="text-secondary small">
                            <?= h($e['description']) ?>
                        </p>

                        <div class="d-flex justify-content-between align-items-center">

                            <strong class="text-primary fs-5">
                                <?= money($e['price_per_day']) ?>

                                <small class="text-secondary fs-6">
                                    / day
                                </small>
                            </strong>

                            <?php if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>

                                <a
                                    class="btn btn-primary btn-sm"
                                    href="customer/book.php?id=<?= $e['equipment_id'] ?>"
                                >
                                    Rent now
                                </a>

                            <?php else: ?>

                                <a
                                    class="btn btn-outline-primary btn-sm"
                                    href="login.php"
                                >
                                    Login to rent
                                </a>

                            <?php endif; ?>

                        </div>

                        <small class="text-secondary">
                            Quantity: <?= (int)$e['total_quantity'] ?>
                        </small>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="col-12">

            <div class="alert alert-info">
                No equipment has been added yet. The administrator can add equipment from the admin dashboard.
            </div>

        </div>

    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>