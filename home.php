<?php
/**
 * ملاحظات توثيقية للصفحة: home.php
 * الغرض: الواجهة الرئيسية والتنقل العام.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_footer(): إظهار تذييل الصفحة عند الحاجة. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
render_head('Homepage');
?>
<div class="home-page">
    <!-- Hero Section with Palm Background -->
    <section class="home-hero">
        <div class="hero-background">
            <img src="assets/images/palm.jpg" alt="Palm trees">
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-center-content">
            <img src="assets/images/logo.png" alt="Hassad logo" class="hero-logo">
            <h1 class="hero-brand-ar">حصاد</h1>
            <h2 class="hero-brand-en">HASSAD</h2>
            <p class="hero-tagline">Grow your investment with<br>clarity and confidence</p>
            <div class="hero-buttons">
                <a class="hero-btn hero-btn-outline" href="<?= e(route('login')) ?>">Login</a>
                <a class="hero-btn hero-btn-filled" href="<?= e(route('register-student')) ?>">Create Account</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="home-features">
        <h2 class="features-title">Hassad will help you with:</h2>
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-badge feature-badge-green">
                    <span class="feature-icon-img">&#128202;</span>
                    Smart Tracking
                </div>
                <p class="feature-desc">Track your investment in real time with clear and simple insights.</p>
            </div>
            <div class="feature-item">
                <div class="feature-badge feature-badge-beige">
                    <span class="feature-icon-img">&#128203;</span>
                    Transparent Report
                </div>
                <p class="feature-desc">Access detailed reports that show your performance with full transparency.</p>
            </div>
            <div class="feature-item">
                <div class="feature-badge feature-badge-green">
                    <span class="feature-icon-img">&#128200;</span>
                    Sustainable Growth
                </div>
                <p class="feature-desc">Grow your portfolio steadily with strategic focused on long-term success.</p>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php render_footer(); ?>
</div>
<?php render_end(); ?>


