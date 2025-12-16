<!doctype html>
<html lang="ru" x-data="{ activeProduct: null }" x-init="$watch('activeProduct', value => { if (!value) return; setTimeout(() => $refs.closeBtn?.focus(), 0); })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Офлайн-продукты</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        [x-cloak] { display: none; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-top: 24px; }
        .product-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 18px; box-shadow: var(--shadow); display: flex; flex-direction: column; gap: 10px; }
        .product-card h3 { margin: 0; }
        .product-card p { color: var(--text-muted); margin: 0; }
        .btn-primary { background: var(--accent); color: #fff; border: none; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .btn-primary:hover { background: var(--accent-strong); }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: grid; place-items: center; padding: 18px; z-index: 40; }
        .modal { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); width: min(640px, 100%); box-shadow: var(--shadow); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .modal-body { padding: 20px; color: var(--text-muted); }
        .btn-icon { border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 20px; line-height: 1; }
        .btn-icon:hover { color: var(--text-main); }
    </style>
</head>
<body class="app-main">
<div class="app-container">
    <h1 class="h4">Офлайн-продукты</h1>
    <p class="text-muted">Нажмите «Подробнее», чтобы открыть модальное окно с описанием продукта.</p>

    <?php $products = [
        ['id' => 1, 'title' => 'Квест-комната', 'description' => 'Полноценный сценарный квест с оборудованием и реквизитом.'],
        ['id' => 2, 'title' => 'Мобильная игра', 'description' => 'Тематическая игра с ведущим — отличное решение для корпоратива.'],
        ['id' => 3, 'title' => 'Образовательный модуль', 'description' => 'Серия интерактивных заданий для школ и кружков.'],
    ]; ?>

    <div class="products-grid">
        <?php foreach ($products as $product): ?>
            <article class="product-card">
                <h3 class="h5"><?= htmlspecialchars($product['title']) ?></h3>
                <p><?= htmlspecialchars($product['description']) ?></p>
                <div>
                    <button type="button" class="btn-primary" @click="activeProduct = <?= (int)$product['id'] ?>">Подробнее</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <template x-if="activeProduct !== null">
        <div class="modal-backdrop" x-show="activeProduct !== null" x-transition.opacity x-cloak @click.self="activeProduct = null" @keydown.escape.window="activeProduct = null">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="modal-header">
                    <h2 class="h5 mb-0">Детали продукта</h2>
                    <button type="button" class="btn-icon" @click="activeProduct = null" aria-label="Закрыть" x-ref="closeBtn">✕</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Вы открыли карточку продукта с ID: <strong x-text="activeProduct"></strong>. Здесь может быть детальное описание.</p>
                </div>
            </div>
        </div>
    </template>
</div>
</body>
</html>
