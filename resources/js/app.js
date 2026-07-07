/* ════════════════════════════════════════════════════════════════
 *  فایل اصلی JavaScript پنل ادمین
 * ════════════════════════════════════════════════════════════════ */

/**
 * مدیریت Dropdown‌ها
 * برای باز/بسته شدن منوها با کلیک بیرون از آن‌ها
 *
 * نحوه استفاده در Blade:
 *   <div data-dropdown>
 *       <button onclick="toggleDropdown('myMenu')">...</button>
 *       <div id="myMenu" class="hidden" data-dropdown-menu>...</div>
 *   </div>
 */
window.toggleDropdown = function (menuId) {
    const menu = document.getElementById(menuId);
    if (menu) {
        menu.classList.toggle("hidden");
    }
};

document.addEventListener("click", function (event) {
    document.querySelectorAll("[data-dropdown]").forEach(function (dropdown) {
        const menu = dropdown.querySelector("[data-dropdown-menu]");
        if (menu && !dropdown.contains(event.target)) {
            menu.classList.add("hidden");
        }
    });
});

/**
 * بستن خودکار پیام‌های موفقیت/خطا بعد از چند ثانیه
 */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-auto-dismiss]").forEach(function (alert) {
        const seconds = parseInt(alert.dataset.autoDismiss, 10) || 5;
        setTimeout(function () {
            alert.style.transition = "opacity 0.4s";
            alert.style.opacity = "0";
            setTimeout(function () {
                alert.remove();
            }, 400);
        }, seconds * 1000);
    });
});
