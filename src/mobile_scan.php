<?php
require __DIR__ . '/config.php';

$user = require_login();
$defaultMode = $user['role'] === 'admin' ? 'return' : 'borrow';

$pageTitle = '手機借還書';
require __DIR__ . '/partials/header.php';
?>

<section
    class="mobile-scan-app"
    data-scan-app
    data-csrf-token="<?= h(csrf_token()) ?>"
    data-default-mode="<?= h($defaultMode) ?>"
>
    <div class="mobile-scan-heading">
        <div>
            <h1>掃描借還書</h1>
            <p class="muted">登入帳號：<?= h($user['username']) ?></p>
        </div>
        <a class="button secondary" href="/index.php">返回</a>
    </div>

    <div class="scan-mode" aria-label="借還書模式">
        <button type="button" data-mode-button data-mode="borrow" class="<?= $defaultMode === 'borrow' ? 'active' : '' ?>">
            借書
        </button>
        <button type="button" data-mode-button data-mode="return" class="<?= $defaultMode === 'return' ? 'active' : '' ?>">
            還書
        </button>
    </div>

    <section class="scan-camera">
        <video data-scan-video autoplay muted playsinline></video>
        <div class="scan-frame" aria-hidden="true"></div>
        <button type="button" class="scan-camera-button" data-camera-button>啟動相機</button>
    </section>

    <div class="scan-status" data-scan-status aria-live="polite">等待相機啟動</div>

    <form class="manual-scan-form" data-manual-form>
        <label>
            ISBN / 條碼
            <input
                name="barcode"
                inputmode="numeric"
                autocomplete="off"
                placeholder="9789865025674"
                data-barcode-input
                required
            >
        </label>
        <button type="submit" data-submit-button>送出</button>
    </form>

    <section class="scan-result" data-scan-result aria-live="polite">
        <span class="scan-result-kicker">目前模式</span>
        <strong data-result-title><?= $defaultMode === 'borrow' ? '借書' : '還書' ?></strong>
        <p data-result-message>掃描書本條碼後會更新結果。</p>
    </section>
</section>

<script src="/assets/mobile-scan.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
