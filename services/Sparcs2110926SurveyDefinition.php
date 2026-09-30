<?php

/**
 * SPARCS2 survey IRAS 376326 v1.0 (10/09/26) — import-ready payloads.
 *
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;

class Sparcs2110926SurveyDefinition
{
    public const FORM_TITLE = 'SPARCS2 110926';

    public static function meta(): array
    {
        return [
            'title' => self::FORM_TITLE,
            'description' => 'Experiences of families after leaving neonatal units. University of Cambridge research study. IRAS 376326 v1.0 (10/09/26).',
            'thank_you_content' => '<p>Thank you for taking the time to complete this survey. Your responses will help us understand the needs of families with babies who have received neonatal care, and contribute to improving support for future families.</p>'
                . '<p>If you were not eligible to take part, thank you for your interest in the SPARCS2 survey.</p>',
            'allow_anonymous' => 1,
            'allow_multiple' => 0,
            'allow_resume' => 1,
            'hide_humhub_header' => 1,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function fields(): array
    {
        $years = [];
        for ($y = (int)date('Y'); $y >= 2020; $y--) {
            $years[] = (string)$y;
        }
        $years[] = 'Before 2020';

        $a5Yes = self::showIf('a5_with_you', 'Yes');
        $a5PassedAway = 'My child has passed away';
        $a5OtherReason = 'My child is not with me for another reason (for example, following adoption, fostering, or do not have custody)';
        $a7Some = 'Some additional needs, for example with mobility, vision, or learning, managed by occasional extra support (e.g., therapy appointments, specialist equipment, or a support plan at nursery/school)';
        $a7Complex = 'Complex additional needs across several areas of care requiring ongoing or daily support (for example combined physical, medical, and developmental needs, multiple specialists, involved, or a formal care/educational plan)';
        $needFollowUp = self::showIfAny([
            ['a7_needs', $a7Some],
            ['a7_needs', $a7Complex],
        ]);
        $ageAtLeast2 = self::showIfChildWithParentAndAgeAtLeast(2);
        $ageAtLeast5 = self::showIfChildWithParentAndAgeAtLeast(5);
        $b1Discussed = self::showIfAny([
            ['b1_discussed', 'Yes, this was fully discussed'],
            ['b1_discussed', 'Yes, but I still had some questions'],
        ]);

        $fields = [];

        $fields[] = self::rich(
            'intro_about',
            'About this survey',
            '<p>We want to understand the experiences of families after leaving neonatal units. The questions should take around 10-15 minutes to answer. Answering is voluntary throughout: you can skip any question, and you can stop at any time without giving a reason. Your answers are confidential and anonymised for research use.</p>'
            . '<p>The survey is being run by researchers at the University of Cambridge. Please <a href="/sparcs2-survey-information" target="_blank" rel="noopener">click here to read the participant information sheet</a>.</p>'
            . '<p>At the end of the survey, you can enter your details to receive £10 compensation for your time.</p>'
        );

        $fields[] = self::rich(
            'intro_neonatal',
            'What is a neonatal unit?',
            '<details><summary>Click here to find more about what a neonatal unit is</summary>'
            . '<p>A neonatal unit is a specialist area of a hospital that cares for babies who need extra medical care. Babies may need neonatal care for many different reasons, for example because they were born early (premature), were very small or unwell, or needed extra care and treatment after birth.</p>'
            . '<p>Neonatal units may have different names, including neonatal intensive care unit (NICU) or special care baby unit (SCBU). In this survey, we use “neonatal unit” to include all of these types of care.</p>'
            . '</details>'
        );

        $fields[] = self::pageBreak('pb_eligibility', 'eligibility', 'Eligibility');

        $fields[] = self::radio('elig_age16', 'Are you 16 or older?', ['Yes', 'No'], true);
        $fields[] = self::radio('elig_uk', 'Do you live in the UK?', ['Yes', 'No'], true);
        $fields[] = self::dropdown('elig_year', 'What year did your baby leave the neonatal unit?', $years, true);

        $fields[] = self::rich(
            'elig_ineligible_msg',
            'Not eligible',
            '<p>Thank you for your interest in the SPARCS2 survey. We are currently collecting responses only from parents aged 16 or over whose child has experienced neonatal care in the UK since 2020.</p>',
            self::showIfAny([
                ['elig_age16', 'No'],
                ['elig_uk', 'No'],
                ['elig_year', 'Before 2020'],
            ])
        );

        $fields[] = self::pageBreak(
            'pb_elig_end',
            'consent',
            'Consent',
            self::gotoEndIfAny([
                ['elig_age16', 'No'],
                ['elig_uk', 'No'],
                ['elig_year', 'Before 2020'],
            ])
        );

        $fields[] = self::rich(
            'consent_intro',
            'Consent',
            '<p>By ticking below, you confirm:</p><ul>'
            . '<li>I agree to participate in this study</li>'
            . '<li>I am able to understand the information provided and make my own decision about taking part</li>'
            . '<li>I have read and understood how my data will be stored and used</li></ul>'
        );

        $fields[] = self::checkbox(
            'consent_confirm',
            'Please confirm each statement (select all three)',
            [
                'I agree to participate in this study',
                'I am able to understand the information provided and make my own decision about taking part',
                'I have read and understood how my data will be stored and used',
            ],
            true,
            ['min_select' => 3]
        );

        $fields[] = self::pageBreak('pb_section_a', 'section-a', 'Section A: Your child\'s neonatal unit journey');

        $fields[] = self::rich(
            'section_a_intro',
            'Section A introduction',
            '<p>We understand that recalling your experiences during and after neonatal care may be difficult for some parents. The survey is intended for any parents who have experienced neonatal unit care and therefore some questions deal with sensitive topics, including loss of a child. You can skip any question, so please only answer what feels comfortable. Organisations that provide support to parents are listed at the end of the survey.</p>'
        );

        $fields[] = self::number(
            'a1_children',
            'A1. How many of your children required care in a neonatal unit?',
            true,
            ['number_min' => 0, 'number_max' => 20]
        );

        $fields[] = self::pageBreak(
            'pb_a1_zero',
            'section-a-continued',
            'Section A: Your child\'s neonatal unit journey',
            self::gotoEndIf('a1_children', '0')
        );

        $fields[] = self::rich(
            'a1_multiple_msg',
            'If you had more than one child in neonatal care',
            '<p>Please answer the rest of this survey about your child who spent time in a neonatal unit most recently. If you had twins, triplets, or more, then answer about your baby who you feel had the most care needs</p>',
            self::showIf('a1_children', '1', FormField::OP_GT)
        );

        $fields[] = self::radio('a2_premature', 'A2. Was your child born early (premature)? (before 37 weeks)', ['Yes', 'No'], false);

        $fields[] = self::radio(
            'a3_stay_length',
            'A3. Approximately how long did your child stay in a neonatal unit?',
            [
                'Two days or less',
                'More than two days, but less than a week',
                'At least a week, but less than one month',
                'At least a month, but less than two months',
                '2 months to less than 4 months',
                '4 months or more',
            ],
            false
        );

        $fields[] = self::radio(
            'a4_expected',
            'A4. Before they were born, did you expect your child to need neonatal unit care?',
            [
                'Yes, I knew neonatal care would be needed',
                'I wasn\'t certain but knew neonatal care might be needed',
                'I wasn\'t expecting my child to need neonatal care',
                'Not sure',
            ],
            false
        );

        $fields[] = self::radio(
            'a5_with_you',
            'A5. Is your child still with you today?',
            [
                'Yes',
                $a5PassedAway,
                $a5OtherReason,
            ],
            false
        );

        $fields[] = self::rich(
            'a5_bereavement',
            'Support after loss',
            '<p>We are so sorry for the loss of your child and very grateful that you are contributing to our research. You can skip any question, so please only answer what feels comfortable. A list of organisations offering support to bereaved parents is included at the end of this survey.</p>',
            self::showIf('a5_with_you', $a5PassedAway)
        );

        $fields[] = self::rich(
            'a5_separated',
            'Thank you',
            '<p>We are very grateful that you are contributing to our research. You can skip any question, so please only answer what feels comfortable. A list of organisations offering support to parents is included at the end of this survey.</p>',
            self::showIf('a5_with_you', $a5OtherReason)
        );

        $fields[] = self::pageBreak(
            'pb_a5_skip_bc',
            'section-a-age-needs',
            'Section A: Your child\'s neonatal unit journey',
            self::gotoPageIfAny('section-d', [
                ['a5_with_you', $a5PassedAway],
                ['a5_with_you', $a5OtherReason],
            ])
        );

        $fields[] = self::number(
            'a6_age_value',
            'A6. How old is your child now?',
            false,
            $a5Yes + ['help_text' => 'Enter a number, then choose weeks, months or years.', 'number_min' => 0, 'number_max' => 120]
        );

        $fields[] = self::radio(
            'a6_age_unit',
            'A6. Age unit',
            ['Weeks', 'Months', 'Years'],
            false,
            $a5Yes + ['help_text' => 'old']
        );

        $fields[] = self::radio(
            'a7_needs',
            'A7. How would you describe your child\'s health and care needs now?',
            [
                'No additional needs beyond most children of their age',
                $a7Some,
                $a7Complex,
            ],
            false,
            $a5Yes
        );

        $fields[] = self::checkbox(
            'a8_conditions',
            'A8. Have you ever been told by a healthcare professional that your child has any of the following serious or persistent health or developmental conditions? (Select all that apply)',
            [
                'Eye or sight problems',
                'Heart problems',
                'Skin problems',
                'Ear or hearing problems',
                'Nose or throat problems',
                'Feeding or digestion problems',
                'Bone problems',
                'Allergies and intolerances',
                'Breathing problems (including wheezing or asthma)',
                'Epilepsy (including fits)',
                'Blood disorders',
                'Urinary and/or kidney problems',
                'Diabetes',
                'Cerebral Palsy',
                'Genetic or congenital problems and chromosomal disorders',
                'Growth concerns (e.g. underweight)',
                'Developmental issues',
                'Other health problem(s)',
                'No health problems',
            ],
            false,
            $needFollowUp + ['exclusive_option' => 'No health problems']
        );

        $fields[] = self::checkbox(
            'a9_concerns',
            'A9. Do you have specific concerns about your child\'s development, even if these are not recognised by health professionals or you have not asked a health professional about them?',
            [
                'Behavioural issues (difficulty with attention or concentration, being unusually active or restless, difficulty managing emotions or reactions)',
                'Learning issues',
                'Social development (relating to other people)',
                'Motor development (movements)',
                'Feeding or growth',
                'Breathing or respiratory issues',
                'Hearing and vision',
                'Sleeping issues',
                'Excessive crying',
                'No specific concerns',
            ],
            false,
            $needFollowUp + ['exclusive_option' => 'No specific concerns']
        );

        $fields[] = self::radio(
            'a10_needs_met',
            'A10. To what extent do you feel your child\'s health needs are being met at the moment?',
            ['Completely', 'Mostly', 'Somewhat', 'A little', 'Not at all'],
            false,
            $needFollowUp
        );

        $fields[] = self::pageBreak('pb_section_b', 'section-b', 'Section B: Support after neonatal unit care', $a5Yes);

        $fields[] = self::rich(
            'section_b_intro',
            'Your experience',
            '<p>Your experience</p>',
            $a5Yes
        );

        $fields[] = self::radio(
            'b1_discussed',
            'B1. Did you discuss your child\'s long-term health and development with any professionals before leaving neonatal care?',
            [
                'Yes, this was fully discussed',
                'Yes, but I still had some questions',
                'No, this wasn\'t discussed',
            ],
            false,
            $a5Yes
        );

        $fields[] = self::radio(
            'b2i_helpful',
            'B2i. Did you find this discussion helpful overall?',
            [
                'Yes, very helpful',
                'Somewhat helpful',
                'Not very helpful',
                'Not helpful at all',
                'Not sure',
            ],
            false,
            $b1Discussed
        );

        $fields[] = self::radio(
            'b2ii_timing',
            'B2ii. Looking back, how did you feel about the timing of this discussion?',
            [
                'I was keen to discuss this',
                'I would have preferred to discuss it at a later time',
                'I would have preferred not to have this discussion at all',
                'Not sure',
            ],
            false,
            $b1Discussed
        );

        $fields[] = self::radio(
            'b3_would_like',
            'B3. Would you have liked to have this kind of discussion before leaving neonatal care?',
            ['Yes', 'No', 'I don\'t know'],
            false,
            self::showIf('b1_discussed', 'No, this wasn\'t discussed')
        );

        $fields[] = self::radio(
            'b4_where_next',
            'B4. When your child left the neonatal unit, where did they go next?',
            [
                'Straight to another hospital ward (for example children\'s intensive care, a surgical or breathing/respiratory ward)',
                'To a neonatal unit at a different hospital',
                'Home',
                'Somewhere else',
            ],
            false,
            $a5Yes
        );

        $fields[] = self::textarea(
            'b4_where_other',
            'B4. Somewhere else (please specify)',
            false,
            self::showIf('b4_where_next', 'Somewhere else')
        );

        $fields[] = self::radio(
            'b5_followup',
            'B5. When your child came home, was follow-up arranged for your child with any professionals (e.g., GP, health visitor, therapist, community neonatal nurses)?',
            [
                'I was given one or more appointments',
                'I had contact details but no appointments',
                'No, but I would have liked some',
                'No, but I didn\'t feel we needed any',
                'Not sure',
            ],
            false,
            $a5Yes
        );

        $fields[] = self::grid(
            'b6_supported',
            'B6. How supported did you feel with your child\'s health and care needs by professionals (e.g. doctors, health visitors, social workers) at each of these stages? (choose N/A if it doesn\'t apply)',
            [
                'First few weeks at home',
                'First 6 months at home',
                '6 months – 1 year',
                'Starting nursery/preschool',
                'Starting school',
            ],
            [
                'Very unsupported',
                'Somewhat unsupported',
                'Neither supported nor unsupported',
                'Somewhat supported',
                'Very supported',
                'N/A',
            ],
            $a5Yes
        );

        $fields[] = self::radio(
            'b7_progress_2y',
            'B7. Has your child had a 2-year progress check?',
            [
                'Yes, with a health visitor',
                'Yes, with a child development team (paediatrician, occupational therapist, speech and language therapist)',
                'No',
                'Not yet',
                'Don\'t know',
            ],
            false,
            $ageAtLeast2
        );

        $fields[] = self::radio(
            'b8_nursery_know',
            'B8. Did your nursery/preschool staff know about your child\'s neonatal unit stay?',
            [
                'Yes, I told them without being asked',
                'Yes, they asked me',
                'No',
                'Not sure',
                'My child does not attend nursery/preschool',
            ],
            false,
            $ageAtLeast2
        );

        $fields[] = self::radio(
            'b9_progress_4y',
            'B9. Has your child had a 4-year progress check?',
            [
                'Yes, with a health visitor',
                'Yes, with a child development team (paediatrician, occupational therapist, speech and language therapist)',
                'No',
                'Not yet',
                'Don\'t know',
            ],
            false,
            $ageAtLeast5
        );

        $fields[] = self::checkbox(
            'b10_school_transition',
            'B10. Were any of the following offered to help your child transition into school? (Select all that apply)',
            [
                'School visits',
                'Transition meetings/events',
                'Shared information between nursery and school',
                'Phased/gradual start',
                'A transition document about my child\'s needs',
                'A named contact at the school',
                'Special educational needs coordinator involvement in planning',
                'None of the above was offered',
                'Not sure',
            ],
            false,
            $ageAtLeast5 + ['exclusive_option' => 'None of the above was offered|Not sure']
        );

        $fields[] = self::pageBreak('pb_section_c', 'section-c', 'Section C: Family services after neonatal unit care', $a5Yes);

        $fields[] = self::checkbox(
            'c1_services_used',
            'C1. Since leaving the neonatal unit, have you used any of the following for your child? (Select all that apply)',
            [
                'Breastfeeding support',
                'Other infant feeding, weaning or nutrition support',
                'Advice/services for my child\'s health, including sleep',
                'Playgroups, play sessions or baby classes',
                'Advice/services for my child\'s disability or learning needs',
                'Parenting support or classes',
                'None of these',
            ],
            false,
            $a5Yes + ['exclusive_option' => 'None of these']
        );

        $fields[] = self::checkbox(
            'c2_why_not',
            'C2. Which best describes why you didn\'t access these services (check all that apply)?',
            [
                'I didn\'t feel I needed this support',
                'I wanted them but wasn\'t offered',
                'I wanted them but wasn\'t aware it existed',
                'I tried but couldn\'t access them (e.g. waiting list, not available locally)',
                'What was on offer wasn\'t suitable for us',
                'Other',
            ],
            false,
            self::showIf('c1_services_used', 'None of these') + ['other_specify' => '0']
        );

        $fields[] = self::textarea(
            'c2_other',
            'C2. Other (please specify)',
            false,
            self::showIf('c2_why_not', 'Other')
        );

        $fields[] = self::grid(
            'c3_support_levels',
            'C3. Please tell us how much support you received, of the different kinds listed below, since leaving the neonatal unit (One answer per row.)',
            [
                'GP reviews',
                'Paediatrician reviews',
                'Developmental reviews',
                'Health visitor reviews',
                'Physiotherapy',
                'Occupational therapy',
                'Speech and language therapy',
                'Feeding support (lactation/dietician)',
                'Sleep support',
                'Parenting guidance',
                'Parent emotional/mental health support',
                'Financial or practical support',
                'Respite or short breaks',
                'Peer/parent community support',
                'Support for siblings/other children',
                'Social work support',
                'Educational support (e.g. SEN co-ordinator)',
            ],
            [
                'Received enough',
                'Received some but not enough',
                'Received none',
                'Didn\'t need this',
                'Prefer not to say or not sure',
            ],
            $a5Yes
        );

        $c4to6 = self::showIfAny([
            ['c3_support_levels', 'Received some but not enough'],
            ['c3_support_levels', 'Received none'],
        ]);

        $fields[] = self::checkbox(
            'c4_barriers',
            'C4. For the services you felt you needed but didn\'t receive, what got in the way? (Select all that apply)',
            [
                'I didn\'t know about this service',
                'The waiting list was too long',
                'I was told we were not eligible',
                'The service was too far away',
                'The cost was too much',
                'Other',
                'Not sure',
            ],
            false,
            $c4to6 + ['other_specify' => '0']
        );

        $fields[] = self::textarea(
            'c5_barrier_detail',
            'C5. What difficulty did you have with accessing services?',
            false,
            self::showIf('c4_barriers', 'Other')
        );

        $fields[] = self::radio(
            'c6_wait_length',
            'C6. For any support you had to wait for, roughly how long was the longest wait?',
            ['Less than 1 month', '1–3 months', '3–6 months', '6–12 months', 'Over a year', 'Not applicable'],
            false,
            $c4to6
        );

        $fields[] = self::pageBreak('pb_section_d', 'section-d', 'Section D: In your own words');

        $fields[] = self::rich(
            'section_d_intro',
            'Section D introduction',
            '<p>All questions are optional — answer as much or as little as you would like.</p>'
        );

        $fields[] = self::textarea(
            'd1_services_better',
            'D1. Based on your personal experience, what can services do better to help support babies who spend time in a neonatal unit, and to their families in the future?',
            false
        );
        $fields[] = self::textarea(
            'd2_anything_else',
            'D2. Is there anything else about your experience since your child left the neonatal unit you\'d like to share?',
            false
        );

        $fields[] = self::checkbox(
            'd3_research_priorities',
            'D3. What do you think is most important for doctors to research about children who have been in a neonatal unit? (Select up to 3)',
            [
                'Long-term physical health',
                'Long-term learning and educational needs',
                'Emotional and mental health for the child',
                'Impact on the wider family (parents, siblings, relationships)',
                'Financial or practical impact on the family',
                'How different services communicate and work together',
                'Genetic and medical predictors of long-term outcomes',
                'Something else (please specify)',
                'Not sure',
            ],
            false,
            ['max_select' => 3, 'exclusive_option' => 'Not sure']
        );

        $fields[] = self::textarea(
            'd3_other',
            'D3. Something else (please specify)',
            false,
            self::showIf('d3_research_priorities', 'Something else (please specify)')
        );

        $fields[] = self::textarea(
            'd4_support_sources',
            'D4. Please tell us about any sources of support that have been helpful to you since your child was born. These might be professional services such as Health Visitor or GP, or support from friends or family, remotely or in person.',
            false
        );

        $fields[] = self::pageBreak('pb_section_e', 'section-e', 'Section E: About your family');

        $fields[] = self::rich(
            'section_e_intro',
            'Section E introduction',
            '<p>We want to make sure that our survey represents all families who use neonatal care as far as possible. Therefore, we would like to understand a bit more about you and your family.</p>'
        );

        $fields[] = self::radio('e1_relationship', 'E1. What is your relationship to your child?', ['Mother', 'Father', 'Carer or other'], false);
        $fields[] = self::radio(
            'e2_age_at_birth',
            'E2. What was your age when your child was born?',
            ['Younger than 18', '18 – 24 years', '25-34 years', '35-44 years', '45 years or older', 'Prefer not to say'],
            false
        );

        $fields[] = self::dropdown(
            'e3_ethnicity',
            'E3. What is your ethnic group?',
            [
                'White - English/Welsh/Scottish/Northern Irish/British',
                'White – Irish',
                'White - Gypsy or Irish Traveller',
                'White – Roma',
                'Any other White background',
                'Mixed/multiple ethnic groups - White and Black Caribbean',
                'Mixed/multiple ethnic groups - White and Black African',
                'Mixed/multiple ethnic groups - White and Asian',
                'Any other mixed/multiple ethnic background',
                'Asian/British Asian - Indian',
                'Asian/British Asian - Pakistani',
                'Asian/British Asian - Bangladeshi',
                'Asian/British Asian - Chinese',
                'Any other Asian background',
                'Black/African/Caribbean/Black British - African',
                'Black/African/Caribbean/Black British - Caribbean',
                'Any other Black/African/Caribbean background',
                'Other ethnic group - Arab',
                'Any other ethnic group',
                'Prefer not to say',
            ],
            false
        );

        $fields[] = self::text('e3_other_white', 'E3. Any other White background: please specify', false, self::showIf('e3_ethnicity', 'Any other White background'));
        $fields[] = self::text('e3_other_mixed', 'E3. Any other mixed/multiple ethnic background: please specify', false, self::showIf('e3_ethnicity', 'Any other mixed/multiple ethnic background'));
        $fields[] = self::text('e3_other_asian', 'E3. Any other Asian background: please specify', false, self::showIf('e3_ethnicity', 'Any other Asian background'));
        $fields[] = self::text('e3_other_black', 'E3. Any other Black/African/Caribbean background: please specify', false, self::showIf('e3_ethnicity', 'Any other Black/African/Caribbean background'));
        $fields[] = self::text('e3_other_ethnic', 'E3. Any other ethnic group: please specify', false, self::showIf('e3_ethnicity', 'Any other ethnic group'));

        $fields[] = self::dropdown(
            'e4_uk_region',
            'E4. What part of the UK do you currently live in?',
            [
                'North East England',
                'North West England',
                'Yorkshire and the Humber',
                'East Midlands',
                'West Midlands',
                'East of England',
                'London',
                'South East England',
                'South West England',
                'Wales',
                'Scotland',
                'Northern Ireland',
                'Prefer not to say',
            ],
            false
        );

        $fields[] = self::radio(
            'e5_employment',
            'E5. What best describes what you are doing at the moment?',
            [
                'Employed (full time)',
                'Employed (part time)',
                'Self-employed',
                'Parental leave',
                'Unemployed',
                'Student',
                'Looking after home/family',
                'Long-term sick/disabled',
                'Other',
            ],
            false
        );

        $fields[] = self::radio(
            'e6_education',
            'E6. What is your highest educational qualification?',
            [
                'University Higher Degree – Doctorate (PhD)',
                'University Higher Degree – Master\'s Degree (MA, MSc, MPhil)',
                'University Degree – (e.g. BA, BSc)',
                'Higher education diploma',
                'A / AS / S levels (or education to 18 years)',
                'O level / GCSE (or education to 16 years)',
                'Other academic qualifications',
                'None of these qualifications',
            ],
            false
        );

        $fields[] = self::pageBreak('pb_compensation', 'compensation', 'End of survey');

        $fields[] = self::rich(
            'comp_intro',
            'Thank you',
            '<p>Thank you for taking the time to complete this survey. Your responses will help us understand the needs of families with babies who have received neonatal care, and contribute to improving support for future families.</p>'
            . '<p>As a thank you for your time, we would like to offer you £10 voucher or bank payment. So we can send you this, please enter your details below:</p>'
        );

        $fields[] = self::text('comp_name', 'Name', false, ['pii' => '1']);
        $fields[] = self::email('comp_email', 'Email', false, ['pii' => '1']);
        $fields[] = self::text('comp_phone', 'Phone (optional)', false, ['pii' => '1']);

        $fields[] = self::rich(
            'interview_intro',
            'Invitation to interview',
            '<p>We\'d love to hear more about the experiences of some families in one-to-one interviews, usually online. This is entirely optional and separate from the survey above. The interview would take around 30-60 minutes and would explore more about the support your family received after neonatal unit care. We are offering a £30 voucher to all parents who are selected to take part. If you would be happy for your details to be passed to the University of Cambridge research team to be considered for an interview, then please tick the box below:</p>'
            . '<p>Full details are in the <a href="/sparcs2-interview-information" target="_blank" rel="noopener">Study Information Sheet – Interview</a>.</p>'
        );

        $fields[] = self::checkbox(
            'interview_optin',
            'Interview opt-in',
            ['Yes, I would be happy to be contacted about taking part in an interview'],
            false
        );

        $fields[] = self::rich(
            'future_intro',
            'Future research studies',
            '<p>Our University of Cambridge research team is interested in learning about different aspects of child development and care after neonatal unit stays. If you would be happy for the research team to securely store your contact details for the purpose of contacting you about future studies we are running, then please tick the box below. You will be free to decide whether or not you would like take part in any other studies after full information and discussion.</p>'
        );

        $fields[] = self::checkbox(
            'future_optin',
            'Future studies opt-in',
            ['Yes, I would be happy to be contacted about taking part in future studies'],
            false
        );

        $fields[] = self::rich(
            'support_resources',
            'Sources of support',
            '<h4>General support for parents of babies requiring neonatal care</h4><ul>'
            . '<li>Bliss: <a href="https://www.bliss.org.uk/health-professionals/information-and-resources/resources-for-parents" target="_blank" rel="noopener">bliss.org.uk/health-professionals/information-and-resources/resources-for-parents</a></li>'
            . '<li>Contact (for families with disabled children): <a href="https://contact.org.uk/" target="_blank" rel="noopener">contact.org.uk</a></li>'
            . '<li>Living Made Easy (for advice on all types of daily living equipment for disabled adult and children): <a href="https://livingmadeeasy.org.uk/" target="_blank" rel="noopener">livingmadeeasy.org.uk</a></li>'
            . '<li>Genetic Alliance UK (supporting those affected by a genetic condition): <a href="https://geneticalliance.org.uk/" target="_blank" rel="noopener">geneticalliance.org.uk</a></li>'
            . '<li>Peeps (for those affected by hypoxic-ischaemic encephalopathy): <a href="https://www.peeps-hie.org/" target="_blank" rel="noopener">peeps-hie.org</a></li>'
            . '<li>Mind (for mental health support): <a href="https://www.mind.org.uk/" target="_blank" rel="noopener">mind.org.uk</a></li>'
            . '<li>UNICEF UK (Support for Parents): <a href="https://www.unicef.org.uk/babyfriendly/support-for-parents/" target="_blank" rel="noopener">unicef.org.uk/babyfriendly/support-for-parents</a></li>'
            . '<li>Spoons (private Facebook support group for neonatal parents): <a href="https://www.facebook.com/groups/SPOONSMCR" target="_blank" rel="noopener">facebook.com/groups/SPOONSMCR</a></li>'
            . '</ul><h4>Bereavement support</h4>'
            . '<p>If you have experienced the loss of a baby or child, the following organisations offer information and support.</p><ul>'
            . '<li>Sands (Stillbirth and Neonatal Death Charity): <a href="https://www.sands.org.uk/support-you/how-we-offer-support" target="_blank" rel="noopener">sands.org.uk/support-you/how-we-offer-support</a></li>'
            . '<li>Child Bereavement UK (Supports families when a baby or child of any age dies or is dying, or when a child is facing bereavement): <a href="https://www.childbereavementuk.org/" target="_blank" rel="noopener">childbereavementuk.org</a></li>'
            . '<li>Aching Arms (Charity run by bereaved parents that supports families who have suffered the loss of a baby during pregnancy, at birth or soon after): <a href="https://www.achingarms.co.uk/" target="_blank" rel="noopener">achingarms.co.uk</a></li>'
            . '</ul><p>If you need help urgently, please contact your GP, call 111, or go to A&amp;E.</p>'
        );

        return $fields;
    }

    private static function rich(string $key, string $label, string $html, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_RICH_TEXT,
            'label' => $label,
            'rich_content' => $html,
            'required' => '',
        ], $extra);
    }

    private static function pageBreak(string $key, string $pageKey, string $title, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_PAGE_BREAK,
            'label' => 'Page break',
            'page_key' => $pageKey,
            'page_title' => $title,
            'required' => '',
        ], $extra);
    }

    private static function radio(string $key, string $label, array $options, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_RADIO,
            'label' => $label,
            'options' => $options,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function dropdown(string $key, string $label, array $options, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_DROPDOWN,
            'label' => $label,
            'options' => $options,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function checkbox(string $key, string $label, array $options, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_CHECKBOX,
            'label' => $label,
            'options' => $options,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function text(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_TEXT,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function email(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_EMAIL,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function number(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_NUMBER,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function textarea(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_TEXTAREA,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function grid(string $key, string $label, array $rows, array $columns, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'variable' => $key,
            'type' => FormField::TYPE_GRID_SINGLE,
            'label' => $label,
            'grid_rows' => $rows,
            'grid_columns' => $columns,
            'required' => '',
        ], $extra);
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function showIfAny(array $pairs): array
    {
        $parts = [];
        foreach ($pairs as [$field, $value]) {
            $parts[] = self::formulaLeaf($field, FormField::OP_EQUALS, $value);
        }
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_formula' => implode(' or ', $parts),
        ];
    }

    private static function showIf(string $field, string $value, string $operator = FormField::OP_EQUALS): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_formula' => self::formulaLeaf($field, $operator, $value),
        ];
    }

    private static function formulaLeaf(string $field, string $operator, string $value): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $field) ?: 'q';
        $number = \humhub\modules\thiscoveryForms\services\formula\Decimal::canonical($value);
        $shown = $number !== null ? $number : '"' . str_replace('"', '\\"', $value) . '"';
        return match ($operator) {
            FormField::OP_GT => '[' . $name . '] > ' . $shown,
            FormField::OP_GTE => '[' . $name . '] >= ' . $shown,
            FormField::OP_LT => '[' . $name . '] < ' . $shown,
            FormField::OP_LTE => '[' . $name . '] <= ' . $shown,
            FormField::OP_NOT_EQUALS => '[' . $name . '] != ' . $shown,
            default => '[' . $name . '] = ' . $shown,
        };
    }

    private static function showIfGte(string $field, string $value): array
    {
        return self::showIf($field, $value, FormField::OP_GTE);
    }

    /**
     * Child still with parent (A5 = Yes) and A6 number + unit converted to at least $years old.
     */
    private static function showIfChildWithParentAndAgeAtLeast(int $years): array
    {
        $age = self::showIfAgeAtLeast($years);
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_formula' => '[a5_with_you] = "Yes" and (' . $age['logic_formula'] . ')',
        ];
    }

    /**
     * A6 number + unit converted to at least $years old.
     */
    private static function showIfAgeAtLeast(int $years): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_formula' => '([a6_age_unit] = "Years" and [a6_age_value] >= ' . $years
                . ') or ([a6_age_unit] = "Months" and [a6_age_value] >= ' . ($years * 12)
                . ') or ([a6_age_unit] = "Weeks" and [a6_age_value] >= ' . ($years * 52) . ')',
        ];
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function gotoEndIfAny(array $pairs): array
    {
        $parts = [];
        foreach ($pairs as [$field, $value]) {
            $parts[] = self::formulaLeaf($field, FormField::OP_EQUALS, $value);
        }
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_END,
            'logic_formula' => implode(' or ', $parts),
        ];
    }

    private static function gotoEndIf(string $field, string $value): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_END,
            'logic_formula' => self::formulaLeaf($field, FormField::OP_EQUALS, $value),
        ];
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function gotoPageIfAny(string $pageKey, array $pairs): array
    {
        $parts = [];
        foreach ($pairs as [$field, $value]) {
            $parts[] = self::formulaLeaf($field, FormField::OP_EQUALS, $value);
        }
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_PAGE,
            'logic_goto' => $pageKey,
            'logic_formula' => implode(' or ', $parts),
        ];
    }
}
