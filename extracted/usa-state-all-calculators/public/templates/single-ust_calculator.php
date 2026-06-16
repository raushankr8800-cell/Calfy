<?php
/**
 * Single post template fallback for USA State Tax Calculators
 */

$ust_is_embed = (isset($_GET['embed']) && $_GET['embed'] === '1');
if ($ust_is_embed) {
    ?><!DOCTYPE html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body <?php body_class('usc-embed-mode'); ?>><?php
} else {
    get_header();
}

$post_id = get_the_ID();
$calc_type = get_post_meta($post_id, '_ust_calc_type', true);
$state_slug = get_post_meta($post_id, '_ust_state_slug', true);

// Always detect type from slug to auto-heal
$post_slug = get_post_field('post_name', $post_id);
$detected_type = 'income-tax';
$other_slugs = ['federal-income-tax-calculator', 'state-income-tax-calculator', 'income-tax-refund-calculator', 'tax-withholding-calculator', 'tax-bracket-calculator', 'estimated-tax-calculator', 'capital-gains-tax-calculator', 'self-employment-tax-calculator', 'payroll-tax-calculator', 'sales-tax-calculator', 'property-tax-estimator', 'effective-property-tax-rate-calculator', 'state-tax-comparison-calculator', 'bonus-tax-calculator'];
if (in_array($post_slug, $other_slugs)) {
    $detected_type = 'other';
} elseif (strpos($post_slug, 'property-tax') !== false) {
    $detected_type = 'property-tax';
} elseif (strpos($post_slug, 'sales-tax') !== false) {
    $detected_type = 'sales-tax';
}

if ($calc_type !== $detected_type) {
    $calc_type = $detected_type;
    update_post_meta($post_id, '_ust_calc_type', $calc_type);
}

if (empty($state_slug)) {
    if ($detected_type === 'other') {
        $state_slug = $post_slug;
    } else {
        $state_slug = $post_slug;
        $prefixes = ['income-tax-calculator-', 'property-tax-calculator-', 'sales-tax-calculator-'];
        $suffixes = ['-income-tax-calculator', '-property-tax-calculator', '-sales-tax-calculator'];
        $state_slug = str_replace($prefixes, '', $state_slug);
        $state_slug = str_replace($suffixes, '', $state_slug);
    }
    update_post_meta($post_id, '_ust_state_slug', $state_slug);
}

$calc_html  = get_post_meta($post_id, '_ust_calc_html', true);
$calc_css   = get_post_meta($post_id, '_ust_calc_css', true);
$calc_js    = get_post_meta($post_id, '_ust_calc_js', true);
$faqs       = get_post_meta($post_id, '_ust_faqs', true);
$states     = ust_get_states_data();
$state_info = isset($states[$state_slug]) ? $states[$state_slug] : null;

// Populate defaults if empty
if ($state_info && (empty($faqs) || !is_array($faqs))) {
    if ($calc_type === 'income-tax') {
        $faqs = ust_get_income_tax_faqs($state_info);
    } elseif ($calc_type === 'property-tax') {
        $faqs = ust_get_property_tax_faqs($state_info);
    } else {
        $faqs = ust_get_sales_tax_faqs($state_info);
    }
    update_post_meta($post_id, '_ust_faqs', $faqs);
}

$seo_title = get_post_meta($post_id, '_ust_seo_title', true);
if ($state_info && empty($seo_title)) {
    if ($calc_type === 'income-tax') {
        $seo_title = sprintf(__('%s Income Tax Calculator - Calfy', 'usa-state-tax-calculators'), $state_info['name']);
    } elseif ($calc_type === 'property-tax') {
        $seo_title = sprintf(__('%s Property Tax Calculator - Calfy', 'usa-state-tax-calculators'), $state_info['name']);
    } else {
        $seo_title = sprintf(__('%s Sales Tax Calculator - Calfy', 'usa-state-tax-calculators'), $state_info['name']);
    }
    update_post_meta($post_id, '_ust_seo_title', $seo_title);
}

$seo_desc = get_post_meta($post_id, '_ust_seo_desc', true);
if ($state_info && empty($seo_desc)) {
    if ($calc_type === 'income-tax') {
        $seo_desc = sprintf(__('Estimate your annual take-home salary, federal income taxes, FICA withholdings, and %s state tax brackets using our free income tax calculator.', 'usa-state-tax-calculators'), $state_info['name']);
    } elseif ($calc_type === 'property-tax') {
        $seo_desc = sprintf(__('Calculate your monthly and annual property taxes in %s. Select your county, apply senior/homestead exemptions, and view 5-year projections.', 'usa-state-tax-calculators'), $state_info['name']);
    } else {
        $seo_desc = sprintf(__('Calculate the combined state and local sales tax for purchases in %s. Apply tax-exempt status for groceries or medicine and view benchmarking costs.', 'usa-state-tax-calculators'), $state_info['name']);
    }
    update_post_meta($post_id, '_ust_seo_desc', $seo_desc);
}

$post_content = get_post_field('post_content', $post_id);
if ($state_info && empty($post_content)) {
    if ($calc_type === 'income-tax') {
        $new_content = ust_get_income_tax_default_content($state_info);
    } elseif ($calc_type === 'property-tax') {
        $new_content = ust_get_property_tax_default_content($state_info);
    } else {
        $new_content = ust_get_sales_tax_default_content($state_info);
    }
    wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
}

$template_ver = get_post_meta($post_id, '_ust_template_version', true);
$expected_ver = '31-' . get_option('usac_data_rev', '0');
if (empty($calc_html) || empty($calc_js) || $template_ver !== $expected_ver) {
    $defaults = ust_get_default_templates($calc_type, $state_slug);
    $calc_html = $defaults['html'];
    $calc_css  = $defaults['css'];
    $calc_js   = $defaults['js'];
    update_post_meta($post_id, '_ust_calc_html', $calc_html);
    update_post_meta($post_id, '_ust_calc_css', $calc_css);
    update_post_meta($post_id, '_ust_calc_js', $calc_js);
    update_post_meta($post_id, '_ust_template_version', $expected_ver);
}

$state_name = isset($states[$state_slug]) ? $states[$state_slug]['name'] : 'USA';

if (!empty($calc_css)) {
    echo '<style>' . $calc_css . '</style>';
}

// Critical tooltip CSS injected inline so it never depends on a cached external stylesheet
echo '<style>
.ust-tooltip{position:relative;display:inline-block;cursor:pointer;margin-left:6px;color:#6b7280;font-size:13px;line-height:1;vertical-align:middle;-webkit-tap-highlight-color:transparent}
.ust-tooltip .ust-tooltiptext{visibility:hidden;opacity:0;width:220px;max-width:72vw;background:#1e293b;color:#fff;text-align:left;border-radius:6px;padding:8px 10px;position:absolute;z-index:9999;bottom:125%;left:50%;margin-left:-110px;transition:opacity .2s;font-size:11px;line-height:1.4;font-weight:400;box-shadow:0 4px 12px rgba(0,0,0,.15);font-family:inherit;pointer-events:none}
.ust-tooltip .ust-tooltiptext::after{content:"";position:absolute;top:100%;left:50%;margin-left:-5px;border-width:5px;border-style:solid;border-color:#1e293b transparent transparent transparent}
.ust-tooltip:hover .ust-tooltiptext,.ust-tooltip:focus .ust-tooltiptext,.ust-tooltip.ust-tooltip-open .ust-tooltiptext{visibility:visible;opacity:1}
@media(max-width:600px){.ust-tooltip .ust-tooltiptext{width:200px;margin-left:-100px}}
</style>';

/* ---- JSON-LD structured data for SEO (conflict-safe) ---- */
$ust_title = get_the_title($post_id);
$ust_seo_plugin_active = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
$ust_permalink = get_permalink($post_id);
$ust_graph = [];
$ust_graph[] = [
    '@type' => 'WebApplication',
    'name' => $ust_title,
    'url' => $ust_permalink,
    'applicationCategory' => 'FinanceApplication',
    'operatingSystem' => 'All',
    'browserRequirements' => 'Requires JavaScript',
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
    'description' => $seo_desc ? $seo_desc : $ust_title,
];
$ust_graph[] = [
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ust_title, 'item' => $ust_permalink],
    ],
];
if (!$ust_seo_plugin_active && !empty($faqs) && is_array($faqs)) {
    $ust_faq_items = [];
    foreach ($faqs as $faq) {
        if (empty($faq['q']) || empty($faq['a'])) continue;
        $ust_faq_items[] = [
            '@type' => 'Question',
            'name' => wp_strip_all_tags($faq['q']),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_strip_all_tags($faq['a'])],
        ];
    }
    if (!empty($ust_faq_items)) {
        $ust_graph[] = ['@type' => 'FAQPage', 'mainEntity' => $ust_faq_items];
    }
}
echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => $ust_graph]) . '</script>';

/* ---- Feature styles: share/embed buttons, print, embed ---- */
echo '<style>
.usc-tool-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:6px}
.usc-tool-btn{display:inline-flex;align-items:center;gap:6px;cursor:pointer;border:1.5px solid #e5e7eb;background:#fff;color:#374151;font-weight:700;font-size:13px;padding:9px 16px;border-radius:8px;transition:all .15s ease;font-family:inherit}
.usc-tool-btn:hover{border-color:#dc2626;color:#dc2626;transform:translateY(-1px)}
.usc-toast{position:fixed;left:50%;bottom:28px;transform:translateX(-50%) translateY(20px);background:#1e293b;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;z-index:10000;opacity:0;pointer-events:none;transition:all .25s ease;box-shadow:0 6px 20px rgba(0,0,0,.25)}
.usc-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
body.usc-embed-mode{background:#fff;margin:0;padding:0}
body.usc-embed-mode .usc-card:not(:first-child){display:none !important}
@media print{
  .usc-tool-actions,.usc-action-buttons,.usc-comparison-box,#ust-col-adjuster,.usc-global-ad-container,.usc-read-full-wrap,#ust-lead-capture-box{display:none !important}
  body,.usc-calculator-page-wrapper{background:#fff !important}
}
</style>';
?>
<div class="usc-calculator-page-wrapper">
    <div class="page" style="padding: 20px 10px;">
        <!-- Card 1: Calculator Card -->
        <div class="usc-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
            <!-- Banner Header -->
            <div class="banner">
                <div class="banner-title">
                    <?php echo esc_html(strtoupper($state_name)); ?><br>
                    <?php 
                    if ($calc_type === 'income-tax') {
                        echo 'INCOME TAX CALCULATOR';
                    } elseif ($calc_type === 'property-tax') {
                        echo 'PROPERTY TAX CALCULATOR';
                    } else {
                        echo 'SALES TAX CALCULATOR';
                    }
                    ?>
                </div>
                <div class="banner-sub">
                    <?php if ($calc_type === 'income-tax') : ?>
                        FEDERAL INCOME TAX  STATE INCOME TAX  FICA WITHHOLDINGS  TAKE-HOME PAY
                    <?php elseif ($calc_type === 'property-tax') : ?>
                        REAL ESTATE TAX  COUNTY RATES  EXEMPTIONS RELIEF  5-YEAR PROJECTIONS
                    <?php else : ?>
                        STATE SALES TAX  LOCAL SURTAXES  EXEMPTIONS RELIEF  COST BENCHMARKS
                    <?php endif; ?>
                </div>
                <div class="badge">
                    <?php 
                    if ($calc_type === 'income-tax') {
                        echo '📊 ESTIMATE ANNUAL INCOME TAX';
                    } elseif ($calc_type === 'property-tax') {
                        echo '🏡 ESTIMATE ANNUAL PROPERTY TAX';
                    } else {
                        echo '🛍️ ESTIMATE PURCHASE SALES TAX';
                    }
                    ?>  FREE
                </div>
            </div>

            <!-- Calculator Container -->
            <div class="inner" style="padding: 24px;">
                <div class="usc-calculator-container">
                    <?php echo $calc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>

                <?php
                $global_enabled = get_option('ust_global_ads_enabled', '1');
                $global_code = get_option('ust_global_ads_code', '');
                if ($global_enabled === '1' && !empty($global_code)) {
                    echo '<div class="usc-global-ad-container" style="margin: 20px 0; text-align: center;">' . $global_code . '</div>';
                }
                ?>

                <?php if (in_array($calc_type, ['income-tax', 'property-tax', 'sales-tax', 'other'])) : ?>
                    <!-- COMPARE TAXES ACROSS STATES -->
                    <div class="usc-comparison-box" id="ust-comparison-box" style="display: none; margin-top: 25px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fafafa; text-align: center;">
                        <div style="font-weight: 800; font-size: 15px; color: var(--pri); margin-bottom: 8px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <?php
                            if ($calc_type === 'other') {
                                $target_calc_type = 'income-tax';
                                if (strpos($state_slug, 'sales') !== false) {
                                    $target_calc_type = 'sales-tax';
                                } elseif (strpos($state_slug, 'property') !== false) {
                                    $target_calc_type = 'property-tax';
                                }
                            } else {
                                $target_calc_type = $calc_type;
                            }

                            if ($target_calc_type === 'income-tax') {
                                echo '<span>🌎</span> COMPARE INCOME TAX ACROSS STATES';
                            } elseif ($target_calc_type === 'property-tax') {
                                echo '<span>🌎</span> COMPARE PROPERTY TAX ACROSS STATES';
                            } else {
                                echo '<span>🌎</span> COMPARE SALES TAX ACROSS STATES';
                            }
                            ?>
                        </div>
                        <p style="font-size: 13px; color: #4b5563; margin: 0 0 16px;">
                            <?php
                            if ($target_calc_type === 'income-tax') {
                                echo 'Compare your income taxes and take-home pay with zero-income-tax states or neighboring regions:';
                            } elseif ($target_calc_type === 'property-tax') {
                                echo 'Compare your property taxes, county rates, and assessment rules with other states:';
                            } else {
                                echo 'Compare your sales tax rates and local surcharges across different states:';
                            }
                            ?>
                        </p>
                        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
                            <?php
                            $comp_states = [
                                'california' => 'CA',
                                'texas'      => 'TX',
                                'new-york'   => 'NY',
                                'florida'    => 'FL',
                            ];
                            if ($calc_type !== 'other' && isset($comp_states[$state_slug])) {
                                $comp_list = $comp_states;
                                unset($comp_list[$state_slug]);
                                $comp_list['washington'] = 'WA';
                            } else {
                                $comp_list = $comp_states;
                            }
                            foreach ($comp_list as $slug => $abbr) {
                                $link = home_url('/' . $slug . '-' . $target_calc_type . '-calculator/');
                                echo '<a href="' . esc_url($link) . '" class="usc-comp-btn">' . esc_html($abbr) . ' vs ' . ($calc_type === 'other' ? 'USA' : esc_html(strtoupper($state_info ? $state_info['abbr'] : $state_slug))) . '</a>';
                            }
                            ?>
                        </div>
                    </div>

                    <?php if ($state_info && isset($states[$state_slug]) && $calc_type !== 'other') :
                        $col_data = function_exists('ust_get_col_index') ? ust_get_col_index() : [];
                        $current_col = isset($col_data[$state_slug]) ? $col_data[$state_slug] : 100;
                    ?>
                    <!-- COST OF LIVING ADJUSTER -->
                    <div class="usc-comparison-box" id="ust-col-adjuster" style="display: none; margin-top: 20px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fafafa;">
                        <div style="font-weight: 800; font-size: 15px; color: var(--pri); margin-bottom: 8px; text-transform: uppercase; text-align:center; display: flex; align-items: center; justify-content: center; gap: 6px;"><span>⚖️</span> Cost of Living Adjuster</div>
                        <p style="font-size: 13px; color: #4b5563; margin: 0 0 14px; text-align:center;">
                            <?php
                            if ($calc_type === 'income-tax') {
                                echo 'See how far your ' . esc_html($state_name) . ' take-home pay goes in another state, then open that state calculator:';
                            } elseif ($calc_type === 'property-tax') {
                                echo 'Compare housing and living costs in ' . esc_html($state_name) . ' with another state, then open that state calculator:';
                            } else {
                                echo 'Compare the overall cost of goods in ' . esc_html($state_name) . ' with another state, then open that state calculator:';
                            }
                            ?>
                        </p>
                        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:center;">
                            <label class="lbl" style="font-weight:700;">Compare with:</label>
                            <select id="ust-col-select" class="sel" onchange="ustColAdjust(this)" style="min-width:220px;">
                                <option value="">-- Choose State --</option>
                                <?php foreach ($states as $s_slug => $s) :
                                    if ($s_slug === $state_slug) continue;
                                    $c = isset($col_data[$s_slug]) ? $col_data[$s_slug] : 100;
                                    $col_link = home_url('/' . $s_slug . '-' . $calc_type . '-calculator/');
                                    ?>
                                    <option value="<?php echo esc_attr($s_slug); ?>" data-col="<?php echo esc_attr($c); ?>" data-name="<?php echo esc_attr($s['name']); ?>" data-link="<?php echo esc_url($col_link); ?>"><?php echo esc_html($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="ust-col-result" style="margin-top:14px; font-size:13px; line-height:1.6; color:#374151; text-align:center; display:none;"></div>
                    </div>
                    <script data-cfasync="false">
                    var ustCurrentCol = <?php echo floatval($current_col); ?>;
                    var ustCurrentStateName = "<?php echo esc_js($state_name); ?>";
                    var ustColType = "<?php echo esc_js($calc_type); ?>";
                    function ustColAdjust(sel) {
                        var opt = sel.options[sel.selectedIndex];
                        var box = document.getElementById("ust-col-result");
                        if (!opt || !opt.value) { box.style.display = "none"; return; }
                        var targetCol = parseFloat(opt.getAttribute("data-col")) || 100;
                        var name = opt.getAttribute("data-name");
                        var link = opt.getAttribute("data-link");
                        var pct = ((targetCol - ustCurrentCol) / ustCurrentCol) * 100;
                        var dir = pct >= 0 ? "more expensive" : "cheaper";
                        var absPct = Math.abs(pct).toFixed(1);
                        var msg = "";
                        if (ustColType === "income-tax") {
                            var netEl = document.getElementById("res-net-pay");
                            var lead = "";
                            if (netEl) {
                                var net = parseFloat((netEl.innerText || "").replace(/[^0-9.]/g, "")) || 0;
                                if (net > 0) {
                                    var equiv = net * (targetCol / ustCurrentCol);
                                    lead = "To keep the same lifestyle in <strong>" + name + "</strong>, your take-home would need to be about <strong>$" + Math.round(equiv).toLocaleString() + "</strong>. ";
                                }
                            }
                            msg = lead + "<strong>" + name + "</strong> is about <strong>" + absPct + "% " + dir + "</strong> than " + ustCurrentStateName + ".";
                        } else if (ustColType === "property-tax") {
                            msg = "Housing and living costs in <strong>" + name + "</strong> are about <strong>" + absPct + "% " + dir + "</strong> than " + ustCurrentStateName + ".";
                        } else {
                            msg = "The overall cost of goods in <strong>" + name + "</strong> is about <strong>" + absPct + "% " + dir + "</strong> than " + ustCurrentStateName + ".";
                        }
                        msg += "<br><a href=\"" + link + "\" style=\"display:inline-block; margin-top:10px; font-weight:700; color:var(--pri); text-decoration:underline;\">Open the " + name + " calculator ?</a>";
                        box.innerHTML = msg;
                        box.style.display = "block";
                    }
                    </script>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- ACTION BUTTONS -->
                <div class="usc-action-buttons" id="ust-action-buttons" style="display: none; margin-top: 20px; display: flex; flex-direction: column; gap: 12px;">
                    <button type="button" class="usc-action-btn-again" onclick="ustCalculateAgain()">🔄 CALCULATE AGAIN</button>
                    <button type="button" class="usc-action-btn-print" onclick="ustPrintReport()">🖨️ PRINT DETAILS REPORT</button>
                    <div class="usc-tool-actions">
                        <button type="button" class="usc-tool-btn" onclick="ustSaveScenario()">💾 Save &amp; Compare</button>
                        <button type="button" class="usc-tool-btn" onclick="ustShareCalc()">🔗 Share</button>
                        <button type="button" class="usc-tool-btn" onclick="ustCopyEmbed()">&lt;/&gt; Embed</button>
                    </div>
                </div>

                <!-- Lead Capture Box (Hidden by default) -->
                <div class="usc-lead-box" id="ust-lead-capture-box" style="display:none; margin-top:20px;">
                    <div class="usc-lead-header">🔓 Unlock Detailed Calculations Report</div>
                    <div class="usc-lead-body">
                        <p>Enter your details below to unlock the complete schedule split table and export options.</p>
                        <div class="field">
                            <label class="lbl">FULL NAME</label>
                            <input type="text" class="inp" id="ust-lead-name" placeholder="John Doe">
                        </div>
                        <div class="field" style="margin-top:12px;">
                            <label class="lbl">EMAIL ADDRESS</label>
                            <input type="email" class="inp" id="ust-lead-email" placeholder="john@example.com">
                        </div>
                        <button type="button" class="print-btn" style="margin-top: 15px; width: 100%;" onclick="submitUstLead()">Unlock Calculations</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Article Content Card -->
        <div class="usc-card" style="margin-bottom: 24px; padding: 24px;">
            <h2 style="margin-top: 0; font-size: 20px; font-weight: 800; color: var(--navy); border-bottom: 2px solid var(--or); padding-bottom: 8px; margin-bottom: 20px; font-family: inherit; display: flex; align-items: center; gap: 8px;">
                <span>📖</span> Detailed <?php echo esc_html($state_name); ?> Tax Guide & Calculation Rules
            </h2>
            <!-- Description/Article Text Preview -->
            <div class="usc-article-preview" id="ust-article-preview">
                <?php
                $raw = get_post_field('post_content', $post_id);
                $plain = wp_strip_all_tags($raw);
                echo '<p style="font-size:13.5px;line-height:1.65;color:#6b7280;margin:0;">' . esc_html(wp_trim_words($plain, 30, '...')) . '</p>';
                ?>
            </div>

            <!-- Full Article (Hidden by default) -->
            <div class="usc-article-full" id="ust-article-full">
                <div class="usc-article-content">
                    <?php
                    while (have_posts()) :
                        the_post();
                        the_content();
                    endwhile;
                    ?>
                </div>
            </div>

            <!-- Read Full Article Button -->
            <div class="usc-read-full-wrap" style="margin-top:20px; text-align:center;">
                <button class="usc-read-full-btn" id="ust-read-full-btn" onclick="ustToggleArticle(this)">
                    Read Full Article
                </button>
            </div>
        </div>

        <!-- Card 3: FAQ Accordions Card -->
        <?php if (!empty($faqs) && is_array($faqs)) : ?>
            <div class="usc-card" style="margin-bottom: 24px; padding: 24px;">
                <section class="fxtool-faq fxtool-single-faq fxtool-reveal" aria-labelledby="fxtool-single-faq-title" style="margin: 0;">
                    <h2 id="fxtool-single-faq-title" class="fxtool-section__title" style="margin-top: 0; font-size: 20px; font-weight: 800; color: var(--navy); border-bottom: 2px solid var(--or); padding-bottom: 8px; margin-bottom: 20px; font-family: inherit; display: flex; align-items: center; gap: 8px;">
                        <span>❓</span> <?php echo esc_html( sprintf( __( 'FAQ About %s', 'usa-state-tax-calculators' ), get_the_title() ) ); ?>
                    </h2>
                    <div class="fxtool-faq__list">
                        <?php foreach ($faqs as $i => $faq) :
                            if (empty($faq['q']) || empty($faq['a'])) continue;
                            ?>
                            <details class="fxtool-faq__item" <?php echo 0 === $i ? 'open' : ''; ?>>
                                <summary class="fxtool-faq__q"><?php echo esc_html($faq['q']); ?></summary>
                                <div class="fxtool-faq__a"><?php echo wp_kses_post(wpautop($faq['a'])); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
if (!empty($calc_js)) {
    echo '<script data-cfasync="false">
    function runAfterDOM(fn) {
        if (document.readyState !== "loading") {
            fn();
        } else {
            document.addEventListener("DOMContentLoaded", fn);
        }
    }
    </script>';
    $processed_js = str_replace('document.addEventListener("DOMContentLoaded",', 'runAfterDOM(', $calc_js);
    echo '<script data-cfasync="false">' . $processed_js . '</script>';
}
?>
<script data-cfasync="false">
function ustToggleArticle(btn) {
    var full    = document.getElementById('ust-article-full');
    var preview = document.getElementById('ust-article-preview');
    var isOpen  = full.classList.contains('usc-article-open');
    if (!isOpen) {
        full.classList.add('usc-article-open');
        preview.style.display = 'none';
        btn.textContent = 'Show Less';
    } else {
        full.classList.remove('usc-article-open');
        preview.style.display = 'block';
        btn.textContent = 'Read Full Article';
    }
}

var leadCaptureActive = <?php echo (get_post_meta($post_id, '_ust_enable_lead_capture', true) === '1') ? 'true' : 'false'; ?>;
var leadUnlocked = false;
var originalCalculate = null;

function checkLeadBeforeCalculate(calcFunc, force) {
    if (leadCaptureActive && !leadUnlocked) {
        if (force === true) {
            var resEl = document.getElementById("results-panel");
            if (resEl) resEl.style.display = "none";
            var leadBox = document.getElementById("ust-lead-capture-box");
            if (leadBox) {
                leadBox.style.display = "block";
                const yOffset = -20;
                const y = leadBox.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({ top: y, behavior: "smooth" });
            }
        }
    } else {
        calcFunc(force);
        trackUstUsage(<?php echo $post_id; ?>);
    }
}

function submitUstLead() {
    var name  = document.getElementById("ust-lead-name").value.trim();
    var email = document.getElementById("ust-lead-email").value.trim();
    if (!name || !email) { alert("Please enter both your name and email address."); return; }
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "<?php echo admin_url('admin-ajax.php'); ?>", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    leadUnlocked = true;
                    var lb = document.getElementById("ust-lead-capture-box");
                    if (lb) lb.style.display = "none";
                    if (originalCalculate) originalCalculate(true);
                } else { alert("Error saving your details. Please try again."); }
            } catch(e) {
                leadUnlocked = true;
                var lb = document.getElementById("ust-lead-capture-box");
                if (lb) lb.style.display = "none";
                if (originalCalculate) originalCalculate(true);
            }
        }
    };
    var nonce = (typeof ustAjax !== 'undefined') ? ustAjax.nonce : '';
    xhr.send("action=ust_submit_lead&post_id=<?php echo $post_id; ?>&nonce=" + encodeURIComponent(nonce) + "&name=" + encodeURIComponent(name) + "&email=" + encodeURIComponent(email));
}

function trackUstUsage(postId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "<?php echo admin_url('admin-ajax.php'); ?>", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    var nonce = (typeof ustAjax !== 'undefined') ? ustAjax.nonce : '';
    xhr.send("action=ust_track_usage&post_id=" + postId + "&nonce=" + encodeURIComponent(nonce));
}

// Standalone usage tracking
trackUstUsage(<?php echo $post_id; ?>);

function showFooterActions() {
    var resPanel = document.getElementById('results-panel') || document.getElementById('results');
    if (!resPanel || window.getComputedStyle(resPanel).display === 'none' || resPanel.innerHTML.trim() === '') {
        return;
    }
    var compBox = document.getElementById('ust-comparison-box');
    var actionBtns = document.getElementById('ust-action-buttons');
    if (compBox) compBox.style.setProperty('display', 'block', 'important');
    if (actionBtns) actionBtns.style.setProperty('display', 'flex', 'important');
    var colBox = document.getElementById('ust-col-adjuster');
    if (colBox) colBox.style.setProperty('display', 'block', 'important');
}

function ustCalculateAgain() {
    var container = document.querySelector('.usc-calculator-container');
    if (container) {
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function ustPrintReport() {
    window.print();
}

function watchUstResults() {
    var resPanel = document.getElementById('results-panel') || document.getElementById('results');
    if (!resPanel) { setTimeout(watchUstResults, 400); return; }
    if (window.getComputedStyle(resPanel).display !== 'none') showFooterActions();
    try {
        var obs = new MutationObserver(function() {
            if (window.getComputedStyle(resPanel).display !== 'none') {
                showFooterActions();
            } else {
                var compBox = document.getElementById('ust-comparison-box');
                var actionBtns = document.getElementById('ust-action-buttons');
                var colBox = document.getElementById('ust-col-adjuster');
                if (compBox) compBox.style.setProperty('display', 'none', 'important');
                if (actionBtns) actionBtns.style.setProperty('display', 'none', 'important');
                if (colBox) colBox.style.setProperty('display', 'none', 'important');
            }
        });
        obs.observe(resPanel, { attributes: true, attributeFilter: ['style', 'class'] });
    } catch (e) {}
}

function initUstFooterActions() {
    watchUstResults();
    document.querySelectorAll('.calc-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            [150, 400, 800, 1500].forEach(function(t) { setTimeout(showFooterActions, t); });
        });
    });
}

if (document.readyState !== "loading") {
    initUstFooterActions();
    setTimeout(initUstFooterActions, 500);
    setTimeout(initUstFooterActions, 1000);
} else {
    document.addEventListener("DOMContentLoaded", function() {
        initUstFooterActions();
        setTimeout(initUstFooterActions, 500);
        setTimeout(initUstFooterActions, 1000);
    });
}

// Centralized data persistence across all calculators
(function() {
    function initUstPersistence() {
        const mappings = {
            salary: ['income-gross', 'fed-salary', 'state-salary', 'wh-gross', 'bracket-income', 'cg-income', 'se-earnings', 'pr-salary', 'comp-salary'],
            status: ['income-filing-status', 'fed-status', 'state-status', 'wh-status', 'bracket-status', 'cg-status', 'comp-status'],
            pretax: ['income-pretax', 'fed-pretax', 'state-pretax'],
            home_value: ['property-home-value', 'gen-prop-value', 'eff-home-value', 'comp-home-value'],
            zip: ['property-zip', 'sales-zip']
        };

        let hasLoadedAny = false;

        // Load values from localStorage
        for (const [key, ids] of Object.entries(mappings)) {
            const savedVal = localStorage.getItem('ust_shared_' + key);
            if (savedVal !== null && savedVal !== '') {
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = savedVal;
                        hasLoadedAny = true;
                    }
                });
            }
        }

        // Attach listeners to save values to localStorage when changed
        for (const [key, ids] of Object.entries(mappings)) {
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    const eventName = el.tagName === 'SELECT' ? 'change' : 'input';
                    el.addEventListener(eventName, function() {
                        localStorage.setItem('ust_shared_' + key, el.value);
                        // Sync same key to any other visible fields on the same page
                        ids.forEach(otherId => {
                            const otherEl = document.getElementById(otherId);
                            if (otherEl && otherEl !== el) {
                                otherEl.value = el.value;
                            }
                        });
                    });
                }
            });
        }

        // Auto-calculate on load if we loaded any saved values
        if (hasLoadedAny) {
            const calcFunctions = [
                'calculateTax', 'calculatePropertyTax', 'calculateSalesTax',
                'calculateFederalTax', 'calculateStateTax', 'calculateRefund',
                'calculateWithholding', 'calculateBracket', 'calculateEstimatedTax',
                'calculateCapitalGains', 'calculateSE', 'calculatePayroll',
                'calculateGenSales', 'calculateGenProp', 'calculateEffectivePropRate',
                'calculateComparison'
            ];
            setTimeout(() => {
                for (const funcName of calcFunctions) {
                    if (typeof window[funcName] === 'function') {
                        try {
                            if (typeof originalCalculate === 'function') {
                                originalCalculate(true);
                            } else {
                                window[funcName](true);
                            }
                        } catch(e) {
                            console.error("Auto-calculation error:", e);
                        }
                        break;
                    }
                }
            }, 400);
        }
    }

    if (document.readyState !== "loading") {
        initUstPersistence();
    } else {
        document.addEventListener("DOMContentLoaded", initUstPersistence);
    }
})();

/* ---- Tooltip click/tap support (ust-tooltip) ---- */
(function() {
    function closeAllUstTooltips(except) {
        document.querySelectorAll('.ust-tooltip.ust-tooltip-open').forEach(function(el) {
            if (el !== except) el.classList.remove('ust-tooltip-open');
        });
    }
    function initUstTooltips() {
        document.querySelectorAll('.ust-tooltip').forEach(function(tip) {
            if (tip.dataset.ustTipReady) return;
            tip.dataset.ustTipReady = '1';
            tip.setAttribute('tabindex', '0');
            tip.setAttribute('role', 'button');
            tip.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var willOpen = !this.classList.contains('ust-tooltip-open');
                closeAllUstTooltips(this);
                this.classList.toggle('ust-tooltip-open', willOpen);
            });
            tip.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
                else if (e.key === 'Escape') { this.classList.remove('ust-tooltip-open'); }
            });
        });
        if (!document.body.dataset.ustTipDocReady) {
            document.body.dataset.ustTipDocReady = '1';
            document.addEventListener('click', function() { closeAllUstTooltips(null); });
        }
    }
    if (document.readyState !== "loading") {
        initUstTooltips();
    } else {
        document.addEventListener("DOMContentLoaded", initUstTooltips);
    }
})();
</script>
<script data-cfasync="false">
/* ---- Share / Embed / Dark-mode features ---- */
function ustShowToast(msg){
  var t=document.getElementById('ust-toast');
  if(!t){t=document.createElement('div');t.id='ust-toast';t.className='usc-toast';document.body.appendChild(t);}
  t.textContent=msg;t.classList.add('show');
  clearTimeout(window.__ustToastT);window.__ustToastT=setTimeout(function(){t.classList.remove('show');},2200);
}
function ustFallbackCopy(text,okMsg){
  try{var ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);ustShowToast(okMsg);}
  catch(e){window.prompt('Copy this:',text);}
}
function ustCopyText(text,okMsg){
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(function(){ustShowToast(okMsg);},function(){ustFallbackCopy(text,okMsg);});}
  else{ustFallbackCopy(text,okMsg);}
}
function ustShareCalc(){
  var url=window.location.href.split('#')[0].split('?')[0];
  if(navigator.share){navigator.share({title:document.title,url:url}).catch(function(){});}
  else{ustCopyText(url,'Link copied to clipboard!');}
}
function ustCopyEmbed(){
  var url=window.location.href.split('#')[0].split('?')[0];
  var src=url+'?embed=1';
  var code='<iframe src="'+src+'" width="100%" height="900" style="border:1px solid #e5e7eb;border-radius:12px;max-width:680px;" loading="lazy" title="'+document.title.replace(/"/g,'')+'"></iframe>';
  ustCopyText(code,'Embed code copied!');
}

/* ---- Generic Save & Compare scenarios (additive, read-only, never breaks calc) ---- */
(function(){
  try {
    var KEY = 'ustScenarios::' + location.pathname;
    function root(){ return document.querySelector('.page .inner') || document.querySelector('.page') || document.body; }
    function load(){ try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch(e){ return []; } }
    function store(list){ try { localStorage.setItem(KEY, JSON.stringify(list.slice(-4))); } catch(e){} }
    function snapshot(){
      var r = root(), fields = [], results = [];
      r.querySelectorAll('input, select').forEach(function(el){
        if (['hidden','button','submit','checkbox','radio'].indexOf(el.type) > -1) return;
        if (el.offsetParent === null) return;
        var lbl = '', field = el.closest('.field');
        if (field){ var l = field.querySelector('.lbl'); if (l) lbl = l.textContent.replace(/\u2139\ufe0f/g,'').trim(); }
        if (!lbl) lbl = el.getAttribute('placeholder') || el.id || 'Field';
        var val = (el.tagName === 'SELECT' && el.selectedIndex >= 0) ? el.options[el.selectedIndex].text : el.value;
        if (val !== '' && val != null) fields.push({ label: lbl, value: String(val) });
      });
      document.querySelectorAll('.hero-card').forEach(function(c){
        var lbl = c.querySelector('.hero-lbl'), val = c.querySelector('.hero-val');
        if (lbl && val) results.push({ label: lbl.textContent.trim(), value: val.textContent.trim() });
      });
      if (!results.length) {
        var seen = {};
        document.querySelectorAll('[id^="res-"]').forEach(function(el){
          if (results.length >= 10 || el.offsetParent === null) return;
          var txt = (el.textContent || '').trim();
          if (!txt || txt.length > 24 || (txt.indexOf('$') === -1 && txt.indexOf('%') === -1)) return;
          var suffix = el.id.replace(/^res-/, '');
          if (seen[suffix]) return; seen[suffix] = 1;
          var lblEl = document.getElementById('lbl-' + suffix);
          var label = lblEl ? lblEl.textContent.trim() : suffix.replace(/-/g, ' ');
          results.push({ label: label, value: txt });
        });
      }
      return { fields: fields, results: results };
    }
    window.ustSaveScenario = function(){
      try {
        var snap = snapshot();
        if (!snap.results.length && !snap.fields.length){ ustShowToast('Run a calculation first'); return; }
        var list = load(); list.push(snap); store(list); renderCompare();
        ustShowToast('Scenario saved (' + load().length + ')');
        var p = document.getElementById('ust-compare-panel'); if (p) p.scrollIntoView({behavior:'smooth', block:'nearest'});
      } catch(e){}
    };
    window.ustClearScenarios = function(){ try { localStorage.removeItem(KEY); var p=document.getElementById('ust-compare-panel'); if(p) p.remove(); ustShowToast('Comparison cleared'); } catch(e){} };
    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function renderCompare(){
      var list = load();
      var panel = document.getElementById('ust-compare-panel');
      if (!list.length){ if (panel) panel.remove(); return; }
      if (!panel){
        panel = document.createElement('div');
        panel.id = 'ust-compare-panel'; panel.className = 'usc-compare-panel';
        var res = document.getElementById('results') || root();
        if (res && res.parentNode) res.parentNode.insertBefore(panel, res.nextSibling); else root().appendChild(panel);
      }
      var html = '<div class="usc-compare-head"><strong>\ud83d\udcca Compare saved scenarios (' + list.length + ')</strong><button type="button" class="usc-tool-btn" onclick="ustClearScenarios()">Clear all</button></div>';
      html += '<div class="usc-compare-scroll"><table class="usc-compare-table"><thead><tr><th>Metric</th>';
      list.forEach(function(s, i){ html += '<th>Scenario ' + (i+1) + '</th>'; });
      html += '</tr></thead><tbody>';
      (list[0].results || []).forEach(function(r, ri){
        html += '<tr class="usc-compare-result"><td>' + esc(r.label) + '</td>';
        list.forEach(function(s){ html += '<td>' + esc((s.results[ri] && s.results[ri].value) || '\u2014') + '</td>'; });
        html += '</tr>';
      });
      (list[0].fields || []).forEach(function(f, fi){
        html += '<tr><td>' + esc(f.label) + '</td>';
        list.forEach(function(s){ var fld = s.fields[fi]; html += '<td>' + esc((fld && fld.value) || '\u2014') + '</td>'; });
        html += '</tr>';
      });
      html += '</tbody></table></div>';
      panel.innerHTML = html;
    }
    function init(){
        if (document.querySelector('[id$="-scenarios-container"]')) {
            document.querySelectorAll('[onclick="ustSaveScenario()"]').forEach(function(b){ b.style.display = 'none'; });
        }
        renderCompare();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  } catch(e){}
})();
</script>
<?php
if ($ust_is_embed) {
    wp_footer();
    echo '</body></html>';
} else {
    get_footer();
}
