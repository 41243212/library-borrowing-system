(function () {
    const app = document.querySelector('[data-scan-app]');
    if (!app) {
        return;
    }

    const csrfToken = app.dataset.csrfToken;
    let mode = app.dataset.defaultMode || 'borrow';
    let detector = null;
    let stream = null;
    let pending = false;
    let lastScanAt = 0;
    let lastSubmitAt = 0;
    let lastBarcode = '';

    const video = app.querySelector('[data-scan-video]');
    const status = app.querySelector('[data-scan-status]');
    const cameraButton = app.querySelector('[data-camera-button]');
    const manualForm = app.querySelector('[data-manual-form]');
    const barcodeInput = app.querySelector('[data-barcode-input]');
    const submitButton = app.querySelector('[data-submit-button]');
    const result = app.querySelector('[data-scan-result]');
    const resultTitle = app.querySelector('[data-result-title]');
    const resultMessage = app.querySelector('[data-result-message]');
    const modeButtons = Array.from(app.querySelectorAll('[data-mode-button]'));

    function setStatus(message) {
        status.textContent = message;
    }

    function setMode(nextMode) {
        mode = nextMode;
        modeButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.mode === mode);
        });
        resultTitle.textContent = mode === 'borrow' ? '借書' : '還書';
        result.classList.remove('success', 'error');
    }

    function setPending(isPending) {
        pending = isPending;
        submitButton.disabled = isPending;
        cameraButton.disabled = isPending;
    }

    function showResult(payload, barcode) {
        result.classList.toggle('success', Boolean(payload.ok));
        result.classList.toggle('error', !payload.ok);

        const book = payload.book;
        resultTitle.textContent = book ? book.title : barcode;
        resultMessage.textContent = payload.message || '操作完成。';
    }

    async function submitBarcode(barcode) {
        const cleanBarcode = barcode.trim();
        if (!cleanBarcode || pending) {
            return;
        }

        setPending(true);
        setStatus('處理中...');

        const body = new URLSearchParams({
            csrf_token: csrfToken,
            mode,
            barcode: cleanBarcode,
        });

        try {
            const response = await fetch('/ajax/scan_action.php', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const payload = await response.json();
            showResult(payload, cleanBarcode);
            setStatus(payload.ok ? '完成' : '未完成');

            if (payload.ok && 'vibrate' in navigator) {
                navigator.vibrate(80);
            }
        } catch (error) {
            showResult({ ok: false, message: '連線失敗，請稍後再試。' }, cleanBarcode);
            setStatus('連線失敗');
        } finally {
            setPending(false);
        }
    }

    async function createDetector() {
        if (!('BarcodeDetector' in window)) {
            return null;
        }

        const preferredFormats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39'];
        let formats = preferredFormats;

        if (typeof BarcodeDetector.getSupportedFormats === 'function') {
            const supportedFormats = await BarcodeDetector.getSupportedFormats();
            formats = preferredFormats.filter((format) => supportedFormats.includes(format));
        }

        try {
            return new BarcodeDetector(formats.length > 0 ? { formats } : undefined);
        } catch (error) {
            try {
                return new BarcodeDetector();
            } catch (fallbackError) {
                return null;
            }
        }
    }

    async function scanLoop() {
        if (!detector || !video.srcObject) {
            return;
        }

        const now = Date.now();
        if (!pending && video.readyState >= 2 && now - lastScanAt > 650) {
            lastScanAt = now;
            try {
                const codes = await detector.detect(video);
                const value = codes[0] && codes[0].rawValue ? codes[0].rawValue : '';
                if (value && (value !== lastBarcode || now - lastSubmitAt > 2500)) {
                    lastBarcode = value;
                    lastSubmitAt = now;
                    barcodeInput.value = value;
                    await submitBarcode(value);
                }
            } catch (error) {
                setStatus('無法辨識畫面');
            }
        }

        requestAnimationFrame(scanLoop);
    }

    async function startCamera() {
        if (stream) {
            setStatus('相機已啟動');
            return;
        }

        setPending(true);
        setStatus('啟動相機中...');

        try {
            detector = await createDetector();
            if (!detector) {
                setStatus('此瀏覽器不支援自動掃描，可手動輸入 ISBN');
                return;
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });

            video.srcObject = stream;
            await video.play();
            setStatus('相機已啟動');
            requestAnimationFrame(scanLoop);
        } catch (error) {
            setStatus('相機無法啟動，可手動輸入 ISBN');
        } finally {
            setPending(false);
        }
    }

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.mode));
    });

    cameraButton.addEventListener('click', startCamera);

    manualForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitBarcode(barcodeInput.value);
    });

    setMode(mode);
})();
