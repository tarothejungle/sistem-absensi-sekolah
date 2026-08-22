document.addEventListener('DOMContentLoaded', function () {
    const closeButton = document.getElementById('close-preview');

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            window.close();
        });
    }
});
