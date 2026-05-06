<?php
/**
 * ملاحظات توثيقية للصفحة: search-funds.php
 * الغرض: البحث واستعراض الصناديق المتاحة.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_funds(): جلب قائمة الصناديق (الكل أو المنشور فقط). القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
/**
 * Search Funds Page
 * --------------------------------
 * Allows students to search and filter investment funds
 */

$criteria = $_SESSION['fund_search_criteria'] ?? [];
$results = $_SESSION['fund_search_results'] ?? null;

// If no search performed, show all published funds
if ($results === null) {
    $results = all_funds(true);
}

render_head('Search Investment Funds');
render_responsive_shell_start('');
?>

<div class="search-funds-page">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">&#128269; Search Investment Funds</h1>
        <p class="page-subtitle">Find the perfect investment opportunity for you</p>
    </div>

    <!-- Search Form -->
    <div class="search-section">
        <form action="<?= e(action_url('search-funds')) ?>" method="POST" class="search-form">
            <div class="search-row">
                <div class="form-group">
                    <input type="text" id="keyword" name="keyword" value="<?= e($criteria['keyword'] ?? '') ?>" 
                           placeholder="Search by name, description..." class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="risk_level">Risk Level</label>
                    <select id="risk_level" name="risk_level" class="form-select">
                        <option value="">All Levels</option>
                        <option value="low" <?= ($criteria['risk_level'] ?? '') === 'low' ? 'selected' : '' ?>>Low Risk</option>
                        <option value="medium" <?= ($criteria['risk_level'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium Risk</option>
                        <option value="high" <?= ($criteria['risk_level'] ?? '') === 'high' ? 'selected' : '' ?>>High Risk</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="investment_type">Investment Type</label>
                    <select id="investment_type" name="investment_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="Equities (Saudi Stocks - Saudi Exchange)" <?= ($criteria['investment_type'] ?? '') === 'Equities (Saudi Stocks - Saudi Exchange)' ? 'selected' : '' ?>>Equities (Saudi Stocks - Saudi Exchange)</option>
                        <option value="Global Equities" <?= ($criteria['investment_type'] ?? '') === 'Global Equities' ? 'selected' : '' ?>>Global Equities</option>
                        <option value="Fixed Income (Bonds & Sukuk)" <?= ($criteria['investment_type'] ?? '') === 'Fixed Income (Bonds & Sukuk)' ? 'selected' : '' ?>>Fixed Income (Bonds & Sukuk)</option>
                        <option value="Real Estate" <?= ($criteria['investment_type'] ?? '') === 'Real Estate' ? 'selected' : '' ?>>Real Estate</option>
                        <option value="Mutual Funds / ETFs" <?= ($criteria['investment_type'] ?? '') === 'Mutual Funds / ETFs' ? 'selected' : '' ?>>Mutual Funds / ETFs</option>
                        <option value="Venture Capital (Startups)" <?= ($criteria['investment_type'] ?? '') === 'Venture Capital (Startups)' ? 'selected' : '' ?>>Venture Capital (Startups)</option>
                        <option value="Commodities (Gold, Oil, etc.)" <?= ($criteria['investment_type'] ?? '') === 'Commodities (Gold, Oil, etc.)' ? 'selected' : '' ?>>Commodities (Gold, Oil, etc.)</option>
                        <option value="Multi-Asset (Diversified Portfolio)" <?= ($criteria['investment_type'] ?? '') === 'Multi-Asset (Diversified Portfolio)' ? 'selected' : '' ?>>Multi-Asset (Diversified Portfolio)</option>
                    </select>
                </div>
            </div>
            
            <div class="search-row">
                <div class="form-group">
                    <label for="min_amount">Min Investment (SAR)</label>
                    <input type="number" id="min_amount" name="min_amount" value="<?= e($criteria['min_amount'] ?? '') ?>" 
                              placeholder="Minimum amount" class="form-input" min="0" step="1" inputmode="numeric">
                </div>
                
                <div class="form-group">
                    <label for="max_amount">Max Investment (SAR)</label>
                    <input type="number" id="max_amount" name="max_amount" value="<?= e($criteria['max_amount'] ?? '') ?>" 
                              placeholder="Maximum amount" class="form-input" min="0" step="1" inputmode="numeric">
                </div>
                
                <div class="form-group">
                    <label for="min_return">Min Expected Return (%)</label>
                    <input type="number" id="min_return" name="min_return" value="<?= e($criteria['min_return'] ?? '') ?>" 
                              placeholder="Minimum return" class="form-input" min="0" step="0.1" inputmode="decimal">
                </div>
                
                <div class="form-group">
                    <label for="max_duration">Max Duration (months)</label>
                    <input type="number" id="max_duration" name="max_duration" value="<?= e($criteria['max_duration'] ?? '') ?>" 
                           placeholder="Maximum months" class="form-input" min="1" step="1" inputmode="numeric">
                </div>
            </div>
            
            <div class="search-actions">
                <button type="submit" class="btn-gold">&#128269; Search Funds</button>
                <a href="<?= e(route('search-funds')) ?>" class="btn-outline" onclick="return confirm('Clear all filters?')">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div class="results-section">
        <div class="results-header">
            <h2>Search Results</h2>
            <span class="results-count"><?= count($results) ?> funds found</span>
        </div>
        
        <?php if (empty($results)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#128269;</div>
            <h3>No Funds Found</h3>
            <p>Try adjusting your search criteria to find more investment opportunities.</p>
        </div>
        <?php else: ?>
        <div class="funds-grid">
            <?php foreach ($results as $fund): ?>
            <div class="fund-card">
                <div class="fund-header">
                    <h3 class="fund-title"><?= e($fund['FundTitle']) ?></h3>
                    <span class="risk-badge risk-<?= strtolower($fund['RiskLevel']) ?>">
                        <?= e(ucfirst($fund['RiskLevel'])) ?> Risk
                    </span>
                </div>
                
                <div class="fund-meta">
                    <span class="meta-item">
                        <span class="meta-icon">&#128200;</span>
                        <?= e($fund['ExpectedReturnPercentage']) ?>% Return
                    </span>
                    <span class="meta-item">
                        <span class="meta-icon">&#9201;</span>
                        <?= e($fund['HoldingPeriod']) ?> months
                    </span>
                </div>
                
                <p class="fund-description">
                    <?= e(substr($fund['FundDescription'] ?? '', 0, 120)) ?>...
                </p>
                
                <div class="fund-investment">
                    <div class="investment-range">
                        <span>Investment Range:</span>
                        <strong><?= format_sar($fund['FundAmountMinimum']) ?> - <?= format_sar($fund['FundAmountMaximum']) ?></strong>
                    </div>
                </div>
                
                <div class="fund-details">
                    <span class="detail-item">
                        <strong>Type:</strong> <?= e($fund['InvestmentType']) ?>
                    </span>
                    <span class="detail-item">
                        <strong>Returns:</strong> <?= e($fund['ReturnTimingPolicy']) ?>
                    </span>
                </div>
                
                <div class="fund-actions">
                    <a href="<?= e(route('fund', ['slug' => $fund['slug']])) ?>" class="btn-outline">View Details</a>
                    <?php if (is_logged_in_as_student() && strtolower((string) ($fund['FundAccountStatus'] ?? 'draft')) === 'published'): ?>
                    <a href="<?= e(route('subscribe', ['slug' => $fund['slug']])) ?>" class="btn-gold">Subscribe</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php
// Clear search results from session
unset($_SESSION['fund_search_criteria'], $_SESSION['fund_search_results']);

render_responsive_shell_end();
render_end();
?>




