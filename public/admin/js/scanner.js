// USB Scanner handler for roll number check-in
(function() {
    var input = null;
    var feedback = null;
    var statusBadge = null;
    var recentList = null;
    var scanCountEl = null;
    var lastScanTimeEl = null;

    var lastScanValue = '';
    var lastScanAt = 0;
    var minIntervalMs = 800; // debounce between scans
    var dupSuppressMs = 3000; // ignore same code for 3s
    var validPattern = /^[A-Za-z0-9-_]{4,32}$/;

    // Sounds (optional; handled if present)
    var successSound = null;
    var errorSound = null;

    function init() {
        input = document.getElementById('scan-input');
        feedback = document.getElementById('scan-feedback');
        statusBadge = document.getElementById('scanner-status');
        recentList = document.getElementById('recent-scans');
        scanCountEl = document.getElementById('scan-count');
        lastScanTimeEl = document.getElementById('last-scan-time');

        // Preload sounds if available at expected path
        try {
            successSound = new Audio('assets/sounds/success.mp3');
            errorSound = new Audio('assets/sounds/error.mp3');
        } catch (e) {
            // ignore if not available
        }

        if (input) input.focus();

        // global shortcut to refocus input
        document.addEventListener('keydown', function(e) {
            if ((e.altKey || e.metaKey) && (e.key === 'i' || e.key === 'I')) {
                e.preventDefault();
                refocus();
            }
        });

        // When form field loses focus, try to refocus after a tick
        if (input) {
            input.addEventListener('blur', function() {
                setTimeout(refocus, 50);
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var value = input.value.trim();
                    input.value = '';
                    if (!value) return;
                    handleScan(value);
                }
            });
        }

        setStatus('Ready');
    }

    function refocus() {
        if (input) {
            input.focus();
            setStatus('Ready');
        }
    }

    function setStatus(text, type) {
        if (!statusBadge) return;
        statusBadge.textContent = text;
        statusBadge.className = 'badge ' + (type === 'error' ? 'bg-label-danger' : type === 'ok' ? 'bg-label-success' : 'bg-label-info');
    }

    function setFeedback(message, type) {
        if (!feedback) return;
        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        var cls = 'alert-info';
        if (type === 'success') cls = 'alert-success';
        else if (type === 'error') cls = 'alert-danger';
        else if (type === 'warning') cls = 'alert-warning';
        feedback.classList.add(cls);
        feedback.textContent = message;
    }

    function play(sound) {
        try { sound && sound.currentTime != null && (sound.currentTime = 0); sound && sound.play && sound.play(); } catch (e) {}
    }

    function handleScan(roll) {
        var now = Date.now();
        if (now - lastScanAt < minIntervalMs) {
            return; // debounce
        }
        if (roll === lastScanValue && (now - lastScanAt) < dupSuppressMs) {
            setFeedback('Duplicate ignored: ' + roll, 'warning');
            setStatus('Duplicate', 'warning');
            play(errorSound);
            return;
        }

        if (!validPattern.test(roll)) {
            setFeedback('Invalid roll number format', 'error');
            setStatus('Invalid', 'error');
            play(errorSound);
            return;
        }

        lastScanValue = roll;
        lastScanAt = now;

        setFeedback('Submitting ' + roll + ' ...', 'info');
        setStatus('Submitting');
        submitRoll(roll)
            .then(function(result) {
                if (result.success) {
                    incrementCounters();
                    addRecent(result, roll);
                    setFeedback(result.message || 'Check-in successful', 'success');
                    setStatus('OK', 'ok');
                    play(successSound);
                } else {
                    setFeedback(result.message || 'Failed', 'error');
                    setStatus('Error', 'error');
                    play(errorSound);
                }
            })
            .catch(function(err) {
                setFeedback('Network or server error', 'error');
                setStatus('Error', 'error');
                play(errorSound);
            })
            .finally(function() {
                refocus();
            });
    }

    function incrementCounters() {
        if (!scanCountEl) return;
        var n = parseInt(scanCountEl.textContent || '0', 10) || 0;
        scanCountEl.textContent = String(n + 1);
        if (lastScanTimeEl) lastScanTimeEl.textContent = new Date().toLocaleTimeString();
    }

    function addRecent(result, roll) {
        if (!recentList) return;
        // Remove empty item
        if (recentList.children.length === 1 && recentList.children[0].classList.contains('text-body-secondary')) {
            recentList.innerHTML = '';
        }
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        var name = (result && result.data && result.data.student_name) || (result && result.student && result.student.name) || '-';
        var status = (result && result.data && result.data.status) || (result && result.status) || 'Check-in';
        li.innerHTML = '<div><strong>' + escapeHtml(roll) + '</strong><div class="small text-body-secondary">' + escapeHtml(name) + '</div></div>' +
                       '<span class="badge bg-label-' + (result.success ? 'success' : 'danger') + '">' + escapeHtml(status) + '</span>';
        recentList.prepend(li);
        // keep last 10
        while (recentList.children.length > 10) recentList.removeChild(recentList.lastChild);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"]/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]);});
    }

    async function submitRoll(roll) {
        var payload = { action: 'check_in', student_id: roll, source: 'usb', timestamp: Date.now() };
        var res = await fetch('../api/checkin_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });
        var data = await res.json().catch(function(){ return { success: false, message: 'Invalid response' }; });
        return data;
    }

    // Initialize once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();


