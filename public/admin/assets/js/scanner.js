document.addEventListener('DOMContentLoaded', function () {
    var scanInput = document.getElementById('scan-input');
    if (scanInput) {
        scanInput.focus();
        scanInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                scanInput.value = '';
            }
        });
    }
});


