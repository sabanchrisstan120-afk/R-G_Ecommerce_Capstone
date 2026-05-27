// Auto-hide flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    const flash = document.querySelector('.flash');
    if (flash) {
        setTimeout(() => flash.remove(), 4000);
    }

    // Confirm on dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });
});
