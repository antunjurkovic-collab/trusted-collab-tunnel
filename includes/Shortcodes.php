<?php
if (!defined('ABSPATH')) { exit; }

function tct_register_shortcodes() {
    add_shortcode('tct_savings_estimator', 'tct_render_savings_estimator');
}
add_action('init', 'tct_register_shortcodes');

function tct_render_savings_estimator($atts) {
    $a = shortcode_atts([
        'default_requests' => '10000',
        'html_kb' => '250',
        'json_kb' => '40',
        'hit_304' => '60', // percent
        'tokens_per_kb_html' => '750',
        'tokens_per_kb_json' => '400',
        'token_cost_per_million' => '3.00',
        'share_percent' => '30',
    ], $atts);

    ob_start();
    ?>
    <div class="tct-savings" style="background:#f9fbfd;border:1px solid #e2e8f0;padding:16px;border-radius:8px;">
      <h3>Estimated Savings Calculator</h3>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-width:880px;">
        <label>Monthly Requests<input type="number" id="tct_req" value="<?php echo esc_attr($a['default_requests']); ?>" style="width:100%"></label>
        <label>JSON 304 Rate (%)<input type="number" id="tct_hit" value="<?php echo esc_attr($a['hit_304']); ?>" style="width:100%"></label>
        <label>Avg HTML Size (kB)<input type="number" id="tct_html" value="<?php echo esc_attr($a['html_kb']); ?>" style="width:100%"></label>
        <label>Avg JSON Size (kB)<input type="number" id="tct_json" value="<?php echo esc_attr($a['json_kb']); ?>" style="width:100%"></label>
        <label>Tokens/kB (HTML cleaned)<input type="number" id="tct_t_html" value="<?php echo esc_attr($a['tokens_per_kb_html']); ?>" style="width:100%"></label>
        <label>Tokens/kB (JSON)<input type="number" id="tct_t_json" value="<?php echo esc_attr($a['tokens_per_kb_json']); ?>" style="width:100%"></label>
        <label>Token Cost ($/1M tokens)<input type="number" step="0.01" id="tct_cost" value="<?php echo esc_attr($a['token_cost_per_million']); ?>" style="width:100%"></label>
        <label>Share to Publisher (%)<input type="number" id="tct_share" value="<?php echo esc_attr($a['share_percent']); ?>" style="width:100%"></label>
      </div>
      <div id="tct_out" style="margin-top:12px;font-size:14px;line-height:1.6"></div>
    </div>
    <script>
    (function(){
      function calc(){
        const req = parseFloat(document.getElementById('tct_req').value)||0;
        const hit = (parseFloat(document.getElementById('tct_hit').value)||0)/100.0;
        const htmlKB = parseFloat(document.getElementById('tct_html').value)||0;
        const jsonKB = parseFloat(document.getElementById('tct_json').value)||0;
        const tHtml = parseFloat(document.getElementById('tct_t_html').value)||0;
        const tJson = parseFloat(document.getElementById('tct_t_json').value)||0;
        const cost = parseFloat(document.getElementById('tct_cost').value)||0;
        const share = (parseFloat(document.getElementById('tct_share').value)||0)/100.0;

        const jsonKBPerReq = (1.0 - hit) * jsonKB; // 304 -> 0 body
        const bwHTML = req * htmlKB;
        const bwJSON = req * jsonKBPerReq;
        const bwSavedKB = Math.max(0, bwHTML - bwJSON);

        const tokHTML = req * htmlKB * tHtml;
        const tokJSON = req * jsonKBPerReq * tJson;
        const tokSaved = Math.max(0, tokHTML - tokJSON);
        const tokSavedCost = tokSaved/1_000_000.0 * cost;
        const publisherShare = tokSavedCost * share;

        const fmt = n=> new Intl.NumberFormat('en-US', {maximumFractionDigits:2}).format(n);
        const out = document.getElementById('tct_out');
        out.innerHTML = [
          `<strong>Bandwidth:</strong> HTML ~ ${fmt(bwHTML)} kB vs TCT JSON ~ ${fmt(bwJSON)} kB → <strong>Saved ~ ${fmt(bwSavedKB)} kB</strong>`,
          `<strong>Tokens:</strong> HTML ~ ${fmt(tokHTML)} vs JSON ~ ${fmt(tokJSON)} → <strong>Saved ~ ${fmt(tokSaved)}</strong>`,
          `<strong>Cost saved:</strong> ~$${fmt(tokSavedCost)} / mo`,
          `<strong>Publisher share (${Math.round(share*100)}%):</strong> ~$${fmt(publisherShare)} / mo`
        ].join('<br>');
      }
      ['tct_req','tct_hit','tct_html','tct_json','tct_t_html','tct_t_json','tct_cost','tct_share']
        .forEach(id=>{ const el=document.getElementById(id); if(el) el.addEventListener('input', calc); });
      calc();
    })();
    </script>
    <?php
    return ob_get_clean();
}

