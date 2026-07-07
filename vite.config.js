import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            // ورودی‌های اصلی: CSS و JS جدا
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
            fonts: [
                // فونت فارسی Vazirmatn به‌صورت خود-میزبان (Self-hosted)
                // → نیازی به CDN ندارید، فایل فونت در build نهایی قرار می‌گیرد
                bunny("Vazirmatn", { weights: [400, 500, 600, 700] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
