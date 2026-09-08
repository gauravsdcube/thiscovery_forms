<?php

use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use yii\helpers\Html;

/** @var array $values resolved or overlay */
/** @var array $defaults */
/** @var string $namePrefix integrity or ModuleSettings[integrity] */
/** @var bool $allowInherit */

$allowInherit = !empty($allowInherit);
$defaults = $defaults ?? IntegritySettings::defaults();
$values = $values ?? [];
$inherit = Yii::t('ThiscoveryFormsModule.base', 'Use site default');

$tri = static function (string $key) use ($values, $allowInherit, $inherit, $namePrefix, $defaults) {
    $current = array_key_exists($key, $values) ? $values[$key] : ($allowInherit ? '' : ($defaults[$key] ?? 1));
    $opts = [];
    if ($allowInherit) {
        $opts[''] = $inherit . ' (' . (!empty($defaults[$key]) ? Yii::t('ThiscoveryFormsModule.base', 'On') : Yii::t('ThiscoveryFormsModule.base', 'Off')) . ')';
    }
    $opts['1'] = Yii::t('ThiscoveryFormsModule.base', 'On');
    $opts['0'] = Yii::t('ThiscoveryFormsModule.base', 'Off');
    $selected = ($current === '' || $current === null) ? '' : (!empty($current) ? '1' : '0');
    echo Html::dropDownList($namePrefix . '[' . $key . ']', $selected, $opts, ['class' => 'form-control']);
};

$num = static function (string $key, $step = 1) use ($values, $allowInherit, $namePrefix, $defaults) {
    $current = array_key_exists($key, $values) ? $values[$key] : ($allowInherit ? '' : ($defaults[$key] ?? ''));
    echo Html::input('number', $namePrefix . '[' . $key . ']', $current, [
        'class' => 'form-control',
        'step' => $step,
        'placeholder' => $allowInherit ? Yii::t('ThiscoveryFormsModule.base', 'Default: {n}', ['n' => $defaults[$key] ?? '']) : '',
    ]);
};

$features = [
    'bot_protection' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Bot protection'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Records honeypot fills, missing browser session, failed verification, and similar signs. These are flags on the response. A single bot signal does not mark the response as fraudulent on its own.'),
    ],
    'rate_limiting' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Rate limiting'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Stops the same hashed IP or browser session submitting faster than the count and window below. This is a gate (the submit is refused), not a quality flag. Shared NAT (hospital, campus, VPN) can lock several genuine people out — set the window generously for those studies.'),
    ],
    'duplicate_detection' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Duplicate detection'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Flags another complete response from the same signed-in user, the same invitation token, or the same hashed IP or session. If Hash IP is Off, IP matching is skipped; session and user/token matches still apply. Shared NAT can look like one connection. Duplicates are kept for review.'),
    ],
    'speed_detection' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Speed detection'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Compares completion time with your minimum seconds and with the typical (median) time for this survey. Fast completions are flagged. They are not rejected on speed alone. Browser timings are supporting signals and can be spoofed.'),
    ],
    'straightline_detection' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Straight-lining detection'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Looks for identical answers across a large grid, almost no variation in ratings, or obvious sequences such as 1,2,3,4,5. Straight-lined answers stay in the data and are flagged for review.'),
    ],
    'attention_checks' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Attention checks'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Scores questions you marked as attention checks on the Builder (instructed items such as “Please select Agree”). Matching is exact after trimming and case-folding (option code or label). One failed check cannot by itself mark a response Suspicious or Excluded.'),
    ],
    'consistency_checks' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Logical consistency checks'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Applies the consistency rules on this tab. When every condition in a rule matches, the response is flagged with that rule’s label. Rules never auto-reject.'),
    ],
    'freetext_checks' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Free-text quality checks'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Looks for empty or very short answers, repeated characters or words, text that only repeats the question, and low letter-variety (heuristic, not language analysis). Similar wording across respondents is a separate similarity check. The product does not detect or claim that text was written by AI.'),
    ],
    'similarity_detection' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Similar-response detection'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Compares this submission with others on the same survey. Unusually similar answer patterns or free-text are grouped on the review screen. Similarity is one signal in the overall score, not proof of collusion.'),
    ],
    'integrity_scoring' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Integrity scoring'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Builds the overall quality score from the weighted signals and suggests Trusted, Review required, Suspicious, or Excluded. One signal type can at most produce Review required. Suspicious and automatic exclusion need at least two categories. Status is a suggestion. Managers can override it; excluded answers are kept.'),
    ],
    'question_timing' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Record time on individual questions'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Also stores time spent focused on each question, not only each page. Leave Off unless you need that detail. Page times are still recorded when quality checks are on.'),
    ],
    'hash_ip' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Hash IP addresses used for fraud checks'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'On: store HMAC hashes of the IP and a /24 (or IPv6 prefix) network hash — never the raw address — so duplicate checks can run. Off: store no IP hashes and skip IP-based duplicate matching. Shared NAT (hospital, campus, VPN) can still look like several people on one hash when On. Technical hashes are only shown to managers.'),
    ],
    'auto_exclude' => [
        'label' => Yii::t('ThiscoveryFormsModule.base', 'Allow automatic exclusion when several signals agree'),
        'guide' => Yii::t('ThiscoveryFormsModule.base', 'Off by default. When On, a response can be marked Excluded only if the score is very low and at least two different signal types fired. A single issue still cannot exclude someone. You can always reinstate the response.'),
    ],
];
$inheritEnabled = $allowInherit && !array_key_exists('enabled', $values);
$enabledChecked = $inheritEnabled
    ? !empty($defaults['enabled'])
    : !empty($values['enabled']);
$siteDefaultOn = !empty($defaults['enabled']);
?>
<div class="cf-integrity-enable" data-cf-integrity-enable>
    <?php if ($allowInherit): ?>
        <label class="checkbox">
            <?= Html::checkbox($namePrefix . '[enabled_inherit]', $inheritEnabled, [
                'value' => '1',
                'uncheck' => '0',
                'data-cf-integrity-inherit' => '1',
            ]) ?>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Use site default ({state})', [
                'state' => $siteDefaultOn
                    ? Yii::t('ThiscoveryFormsModule.base', 'On')
                    : Yii::t('ThiscoveryFormsModule.base', 'Off'),
            ]) ?>
        </label>
    <?php endif; ?>
    <label class="checkbox">
        <?= Html::checkbox($namePrefix . '[enabled]', $enabledChecked, [
            'value' => '1',
            'uncheck' => '0',
            'data-cf-integrity-enabled' => '1',
            'data-cf-default-on' => $siteDefaultOn ? '1' : '0',
        ]) ?>
        <?= Yii::t('ThiscoveryFormsModule.base', 'Enable integrity checks') ?>
    </label>
    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Tick this to record quality scores and integrity metadata on complete responses. When it is off, no scores, timings, or integrity hashes are stored. Access mode and CAPTCHA below still apply on their own.')]) ?>
</div>
<p class="help-block">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Quality uses several signals together. A single issue such as a fast completion, a shared IP, or one failed attention check does not on its own mark a response as fraudulent. Use the ? next to each setting for a short explanation.') ?>
</p>
<h5 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Access mode') ?></h5>
<div class="row g-3">
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Who can open the survey') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Public / open link is the usual share URL. Unique invitation link requires ?access= on the URL (generate links from this tab). Signed-in with an email address checks that the HumHub account has an email field — it is not a per-survey email proof. Logged-in and restricted to space members also require a signed-in account.')]) ?>
            <?php
            $modeOpts = $allowInherit ? ['' => $inherit] : [];
            $modeOpts += IntegritySettings::accessModeLabels();
            $modeVal = $values['access_mode'] ?? ($allowInherit ? '' : IntegritySettings::ACCESS_PUBLIC);
            echo Html::dropDownList($namePrefix . '[access_mode]', $modeVal, $modeOpts, ['class' => 'form-control']);
            ?>
        </div>
    </div>
</div>

<h5 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'CAPTCHA') ?></h5>
<p class="help-block">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Turn CAPTCHA on or off here for this site or survey. Works even when integrity scoring is off. HumHub Altcha needs no external keys. Cloudflare Turnstile is optional and uses keys from Administration only.') ?>
</p>
<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'CAPTCHA on submit') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'On: show a verification check on submit according to “CAPTCHA when” below. Off: never show CAPTCHA on submit (open-rate CAPTCHA below can still run if you turn that on separately).')]) ?>
            <?php $tri('captcha'); ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'CAPTCHA when') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Only used when “CAPTCHA on submit” is On. Always shows on every submit. Only when behaviour looks suspicious shows it after a honeypot, missing session, or rate-limit signal (those signals need integrity checks on) and keeps it until the check is completed. Off never shows CAPTCHA on submit.')]) ?>
            <?php
            $capOpts = $allowInherit ? ['' => $inherit] : [];
            $capOpts += IntegritySettings::captchaModeLabels();
            echo Html::dropDownList($namePrefix . '[captcha_mode]', $values['captcha_mode'] ?? ($allowInherit ? '' : IntegritySettings::CAPTCHA_SUSPICIOUS), $capOpts, ['class' => 'form-control']);
            ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'CAPTCHA provider') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'HumHub Altcha works out of the box. Choose Cloudflare Turnstile only when site and secret keys are set in Administration.')]) ?>
            <?php
            $provOpts = $allowInherit ? ['' => $inherit] : [];
            $provOpts += IntegritySettings::captchaProviderLabels();
            echo Html::dropDownList(
                $namePrefix . '[captcha_provider]',
                $values['captcha_provider'] ?? ($allowInherit ? '' : IntegritySettings::CAPTCHA_PROVIDER_ALTCHA),
                $provOpts,
                ['class' => 'form-control']
            );
            ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'CAPTCHA before form opens') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Off by default. When On, respondents who open the survey faster than the open-rate limits must pass CAPTCHA before the fill page loads. Uses the same CAPTCHA provider.')]) ?>
            <?php $tri('open_captcha'); ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Max opens per window') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'How many times the same IP or session may open the fill page in the window before the open CAPTCHA gate triggers. Only used when “CAPTCHA before form opens” is On.')]) ?>
            <?php $num('open_rate_count'); ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cf-field">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Open-rate window (minutes)') ?></label>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Length of the open-rate window in minutes. Example: 30 opens per 10 minutes.')]) ?>
            <?php $num('open_rate_window'); ?>
        </div>
    </div>
</div>
<?php if (!$allowInherit): ?>
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="cf-field">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Cloudflare Turnstile site key') ?></label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Public site key from your Cloudflare Turnstile widget. Only used when the CAPTCHA provider is Cloudflare Turnstile. Leave blank if you use HumHub Altcha. Survey-level settings cannot supply a different key.')]) ?>
                <?= Html::textInput($namePrefix . '[turnstile_site_key]', $values['turnstile_site_key'] ?? '', ['class' => 'form-control', 'autocomplete' => 'off']) ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="cf-field">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Turnstile secret key') ?></label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Secret key used only on the server to verify the widget when the provider is Cloudflare Turnstile. Do not share it. Stored in module settings, not in survey answers.')]) ?>
                <?= Html::passwordInput($namePrefix . '[turnstile_secret]', $values['turnstile_secret'] ?? '', ['class' => 'form-control', 'autocomplete' => 'off']) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div data-cf-integrity-when-on>
<p class="help-block text-muted" data-cf-integrity-quality-hint style="display:none">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Turn on “Enable integrity checks” above to configure quality scoring options.') ?>
</p>
<h5 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Quality checks') ?></h5>
<div class="row g-3 mt-1">
    <?php foreach ($features as $key => $meta): ?>
        <div class="col-md-6">
            <div class="cf-field">
                <label class="cf-label"><?= Html::encode($meta['label']) ?></label>
                <?= $this->render('_setting_guide', ['text' => $meta['guide']]) ?>
                <?php $tri($key); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h5 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Thresholds and weights') ?></h5>
<p class="help-block">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Weights are how many points that category can subtract from a starting score of 100. Thresholds decide Trusted versus Review versus Suspicious. Leave a box empty on a survey to use the site default.') ?>
</p>
<div class="row g-3">
    <?php
    $nums = [
        'rate_limit_count' => [
            Yii::t('ThiscoveryFormsModule.base', 'Max submits per window'),
            Yii::t('ThiscoveryFormsModule.base', 'How many submit attempts from the same IP or session are allowed in the window. Further attempts are refused until the window resets.'),
        ],
        'rate_limit_window' => [
            Yii::t('ThiscoveryFormsModule.base', 'Rate window (minutes)'),
            Yii::t('ThiscoveryFormsModule.base', 'Length of the rate-limit window in minutes. Example: 8 submits per 10 minutes.'),
        ],
        'speed_percent' => [
            Yii::t('ThiscoveryFormsModule.base', 'Speeding: percent of typical time'),
            Yii::t('ThiscoveryFormsModule.base', 'Flag when completion is faster than this percent of the survey’s median time (once enough completions exist). 40 means under 40% of typical time.'),
        ],
        'speed_min_seconds' => [
            Yii::t('ThiscoveryFormsModule.base', 'Speeding: minimum seconds'),
            Yii::t('ThiscoveryFormsModule.base', 'Also flag completions shorter than this many seconds, even before a median is known. Set this to a floor a real person could not beat.'),
        ],
        'straightline_min_items' => [
            Yii::t('ThiscoveryFormsModule.base', 'Straight-line: minimum items'),
            Yii::t('ThiscoveryFormsModule.base', 'A grid or rating set must have at least this many items before identical or sequential answers are treated as straight-lining.'),
        ],
        'freetext_min_chars' => [
            Yii::t('ThiscoveryFormsModule.base', 'Free text: minimum characters'),
            Yii::t('ThiscoveryFormsModule.base', 'On text questions that expect a real answer, shorter than this is flagged as too short. Empty required text is flagged separately.'),
        ],
        'similarity_threshold' => [
            Yii::t('ThiscoveryFormsModule.base', 'Similarity percent'),
            Yii::t('ThiscoveryFormsModule.base', 'How alike two responses must be (0–100) before they are grouped as similar. Higher is stricter. 90 is a strong match, not a mild resemblance.'),
        ],
        'trust_threshold' => [
            Yii::t('ThiscoveryFormsModule.base', 'Trusted score at or above'),
            Yii::t('ThiscoveryFormsModule.base', 'Overall scores at or above this are Trusted. Below it, the response needs review. Suspicious or automatic exclusion still require at least two different signal types — a single issue cannot produce those statuses, even if you raise this weight.'),
        ],
        'review_threshold' => [
            Yii::t('ThiscoveryFormsModule.base', 'Review score at or above'),
            Yii::t('ThiscoveryFormsModule.base', 'Scores at or above this (but below Trusted) are Review required. Below this they can be Suspicious only if at least two signal types fired. Automatic exclusion, if enabled, only applies well below this and with several signals.'),
        ],
        'weight_bot' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: bot signals'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted for bot indicators (honeypot, session, rate limit, failed CAPTCHA). One indicator uses only part of this weight.'),
        ],
        'weight_duplicate' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: duplicates'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted when duplicate user, token, or IP/session matches are found.'),
        ],
        'weight_speed' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: speeding'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted when the response is faster than your speeding thresholds.'),
        ],
        'weight_attention' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: attention checks'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted for failed attention checks. A single failure is capped so it cannot drop a Trusted response on its own.'),
        ],
        'weight_straightline' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: straight-lining'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted for identical grids, flat ratings, or sequential patterns.'),
        ],
        'weight_consistency' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: consistency'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted when consistency rules fire. Each matching rule is recorded by name on the response.'),
        ],
        'weight_freetext' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: free text'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted for short, repeated, or copied-looking free text.'),
        ],
        'weight_similarity' => [
            Yii::t('ThiscoveryFormsModule.base', 'Weight: similarity'),
            Yii::t('ThiscoveryFormsModule.base', 'Maximum points subtracted when this response is unusually similar to others. Matching response IDs are listed on the review screen.'),
        ],
    ];
    foreach ($nums as $key => [$label, $guide]): ?>
        <div class="col-md-4">
            <div class="cf-field">
                <label class="cf-label"><?= Html::encode($label) ?></label>
                <?= $this->render('_setting_guide', ['text' => $guide]) ?>
                <?php $num($key); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>
