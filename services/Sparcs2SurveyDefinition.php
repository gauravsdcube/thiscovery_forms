<?php

/**
 * SPARCS2 survey field definitions (import-ready payloads).
 *
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;

class Sparcs2SurveyDefinition
{
    public const FORM_TITLE = 'SPARCS2 — Survey';

    public static function meta(): array
    {
        return [
            'title' => self::FORM_TITLE,
            'description' => 'Experiences of families after leaving neonatal units. University of Cambridge research study.',
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

        $fields = [];

        $fields[] = self::rich(
            'intro_about',
            'About this survey',
            '<p>We want to understand the experiences of families after leaving neonatal units. The questions should take around 10–15 minutes to answer. Answering is voluntary throughout: you can skip any question, and you can stop at any time without giving a reason. Your answers are confidential and anonymised for research use.</p>'
            . '<p>The survey is being run by researchers at the University of Cambridge. Please read the participant information sheet before you begin.</p>'
            . '<p>At the end of the survey, you can enter your details to receive £10 compensation for your time.</p>'
        );

        $fields[] = self::rich(
            'intro_neonatal',
            'What is a neonatal unit?',
            '<p>A neonatal unit is a specialist area of a hospital that cares for babies who need extra medical care. Babies may need neonatal care for many different reasons, for example because they were born early (premature), were very small or unwell, or needed extra care and treatment after birth.</p>'
            . '<p>Neonatal units may have different names, including neonatal intensive care unit (NICU) or special care baby unit (SCBU). In this survey, we use “neonatal unit” to include all of these types of care.</p>'
        );

        $fields[] = self::pageBreak('pb_eligibility', 'eligibility', 'Eligibility');

        $fields[] = self::radio('elig_age16', 'Are you 16 or older?', ['Yes', 'No'], true);
        $fields[] = self::radio('elig_uk', 'Do you live in the UK?', ['Yes', 'No'], true);
        $fields[] = self::dropdown('elig_year', 'What year was your baby in a neonatal unit?', $years, true);

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
            ['help_text' => 'If you had more than one child in neonatal care, answer about the child who spent time in a unit most recently (or who had the most care needs if you had twins or more).']
        );

        $fields[] = self::pageBreak(
            'pb_a1_zero',
            'section-a',
            'Section A',
            self::gotoEndIf('a1_children', '0')
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
                'My child has died',
                'My child is not with me for another reason (for example, following adoption, fostering, or do not have custody)',
            ],
            false
        );

        $fields[] = self::rich(
            'a5_bereavement',
            'Support after loss',
            '<p>We are so sorry for the loss of your child and very grateful that you are contributing to our research. You can skip any question, so please only answer what feels comfortable. A list of organisations offering support to bereaved parents is included at the end of this survey.</p>',
            self::showIf('a5_with_you', 'My child has died')
        );

        $fields[] = self::rich(
            'a5_separated',
            'Thank you',
            '<p>We are very grateful that you are contributing to our research. You can skip any question, so please only answer what feels comfortable. A list of organisations offering support to parents is included at the end of this survey.</p>',
            self::showIf('a5_with_you', 'My child is not with me for another reason (for example, following adoption, fostering, or do not have custody)')
        );

        $fields[] = self::pageBreak(
            'pb_a5_skip_bc',
            'section-d',
            'Section D: In your own words',
            self::gotoPageIfAny('section-d', [
                ['a5_with_you', 'My child has died'],
                ['a5_with_you', 'My child is not with me for another reason (for example, following adoption, fostering, or do not have custody)'],
            ])
        );

        $fields[] = self::dropdown(
            'a6_age_band',
            'A6. How old is your child now?',
            [
                'Less than 6 months',
                '6 months to less than 1 year',
                '1 year to less than 2 years',
                '2 years to less than 5 years',
                '5 years or older',
            ],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::radio(
            'a7_needs',
            'A7. How would you describe your child\'s health and care needs now?',
            [
                'No additional needs beyond most children of their age',
                'Some additional needs, for example with mobility, vision, or learning, managed by occasional extra support (e.g., therapy appointments, specialist equipment, or a support plan at nursery/school)',
                'Complex additional needs across several areas of care requiring ongoing or daily support (for example combined physical, medical, and developmental needs, multiple specialists involved, or a formal care/educational plan)',
            ],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $needFollowUp = self::showIfAdditionalNeeds();

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
                'Behavioural issues',
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

        $fields[] = self::pageBreak('pb_section_b', 'section-b', 'Section B: Support after neonatal unit care', self::showIf('a5_with_you', 'Yes'));

        $fields[] = self::radio(
            'b1_discussed',
            'B1. Did you discuss your child\'s long-term health and development with any professionals before leaving neonatal care?',
            [
                'Yes, this was fully discussed',
                'Yes, but I still had some questions',
                'No, this wasn\'t discussed',
            ],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::radio(
            'b2_helpful',
            'B2. Did you find this discussion helpful overall?',
            [
                'Yes, I was keen to discuss the longer term',
                'Somewhat helpful',
                'No, I would have preferred to discuss at a later time',
                'No, I would have preferred not to have this discussion at all',
                'Not sure',
            ],
            false,
            self::showIfAny([
                ['b1_discussed', 'Yes, this was fully discussed'],
                ['b1_discussed', 'Yes, but I still had some questions'],
            ])
        );

        $fields[] = self::radio(
            'b3_would_like',
            'B3. Would you have liked to have this kind of discussion before leaving neonatal care?',
            ['Yes', 'No', 'I don\'t know'],
            false,
            self::showIf('b1_discussed', 'No, this wasn\'t discussed')
        );

        $fields[] = self::radio(
            'b4_followup',
            'B4. When you left the neonatal unit, was any follow-up care arranged for your child with any professionals (e.g., hospital, therapists, GP)?',
            [
                'I was given one or more appointments',
                'I had contact details but no appointments',
                'No, but I would have liked some',
                'No, but I didn\'t feel we needed any',
                'Not sure',
            ],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::grid(
            'b5_supported',
            'B5. How supported did you feel with your child\'s health and care needs by professionals (e.g. doctors, health visitors, social workers) at each of these stages? (choose N/A if it doesn\'t apply)',
            [
                'First few weeks at home',
                'First 6 months at home',
                '6 months – 1 year',
                'Starting nursery/preschool',
                'Starting school',
            ],
            ['Very supported', 'Somewhat supported', 'Neither supported nor unsupported', 'Somewhat unsupported', 'Very unsupported', 'N/A'],
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::radio(
            'b6_progress_2y',
            'B6. Has your child had a 2-year progress check with a child development team (including for example a paediatrician, occupational therapist, speech and language therapist?)',
            ['Yes', 'No', 'Not yet', 'Don\'t know'],
            false,
            self::showIfAny([
                ['a6_age_band', '2 years to less than 5 years'],
                ['a6_age_band', '5 years or older'],
            ])
        );

        $fields[] = self::radio(
            'b7_nursery_know',
            'B7. Did your nursery/preschool staff know about your child\'s neonatal unit stay?',
            [
                'Yes, I told them without being asked',
                'Yes, they asked me',
                'No',
                'Not sure',
                'My child does not attend nursery/preschool',
            ],
            false,
            self::showIfAny([
                ['a6_age_band', '2 years to less than 5 years'],
                ['a6_age_band', '5 years or older'],
            ])
        );

        $fields[] = self::radio(
            'b8_progress_4y',
            'B8. Has your child had a 4-year progress check with a child development team (including for example a paediatrician, occupational therapist, speech and language therapist?)',
            ['Yes', 'No', 'Not yet', 'Don\'t know'],
            false,
            self::showIf('a6_age_band', '5 years or older')
        );

        $fields[] = self::checkbox(
            'b9_school_transition',
            'B9. Were any of the following offered to help your child transition into school? (Select all that apply)',
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
            self::showIf('a6_age_band', '5 years or older') + ['exclusive_option' => 'None of the above was offered']
        );

        $fields[] = self::pageBreak('pb_section_c', 'section-c', 'Section C: Family services after neonatal unit care', self::showIf('a5_with_you', 'Yes'));

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
            self::showIf('a5_with_you', 'Yes') + ['exclusive_option' => 'None of these']
        );

        $fields[] = self::checkbox(
            'c2_why_not',
            'C2. Which best describes why you didn\'t access these services (check all that apply)?',
            [
                'I didn\'t feel I needed this support',
                'I wanted it but wasn\'t offered',
                'I wanted it but wasn\'t aware it existed',
                'I tried but couldn\'t access it (e.g. waiting list, not available locally)',
                'What was on offer wasn\'t suitable for us',
                'Other',
            ],
            false,
            self::hideIf('c1_services_used', 'None of these')
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
            ['Received enough', 'Received some but not enough', 'Received none', 'Didn\'t need this', 'Prefer not to say or not sure'],
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::checkbox(
            'c4_barriers',
            'C4. For the services you felt you needed but didn\'t receive, what got in the way? (Select all that apply)',
            [
                'I didn\'t know about this service',
                'I tried, but couldn\'t access this service',
                'The waiting list was too long',
                'I was told we were not eligible',
                'The service was too far away',
                'The cost was too much',
                'Other',
                'Not sure',
            ],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::textarea('c5_barrier_detail', 'C5. What difficulty did you have with accessing services?', false, self::showIf('c4_barriers', 'Other'));

        $fields[] = self::radio(
            'c6_wait_length',
            'C6. For any support you had to wait for, roughly how long was the longest wait?',
            ['Less than 1 month', '1–3 months', '3–6 months', '6–12 months', 'Over a year', 'Not applicable'],
            false,
            self::showIf('a5_with_you', 'Yes')
        );

        $fields[] = self::pageBreak('pb_section_d', 'section-d', 'Section D: In your own words');

        $fields[] = self::rich(
            'section_d_intro',
            'Section D introduction',
            '<p>All questions are optional — answer as much or as little as you would like.</p>'
        );

        $fields[] = self::textarea('d1_wish_different', 'D1. Looking back, what\'s one thing you wish had been different about the support you and your child received?', false);
        $fields[] = self::textarea('d2_anything_else', 'D2. Is there anything else about your experience since your child left the neonatal unit you\'d like to share?', false);

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

        $fields[] = self::textarea('d3_other', 'D3. Something else (please specify)', false, self::showIf('d3_research_priorities', 'Something else (please specify)'));

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
                'Asian/Asian British - Indian',
                'Asian/Asian British - Pakistani',
                'Asian/Asian British - Bangladeshi',
                'Asian/Asian British - Chinese',
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

        $fields[] = self::pageBreak('pb_compensation', 'compensation', 'Compensation and optional follow-up');

        $fields[] = self::rich(
            'comp_intro',
            'Compensation',
            '<p>As a thank you for your time, we would like to offer you a £10 voucher or bank payment. So we can send you this, please enter your details below.</p>'
        );

        $fields[] = self::text('comp_name', 'Name', false);
        $fields[] = self::email('comp_email', 'Email', false);
        $fields[] = self::text('comp_phone', 'Phone (optional)', false);

        $fields[] = self::rich(
            'interview_intro',
            'Invitation to interview',
            '<p>We\'d love to hear more about the experiences of some families in one-to-one interviews, usually online. This is entirely optional and separate from the survey above. The interview would take ~30–60 minutes and would explore more about the support your family received after neonatal unit care. We are offering a £30 voucher to all parents who are selected to take part.</p>'
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
            '<p>Our University of Cambridge research team is interested in learning about different aspects of child development and care after neonatal unit stays. If you would be happy for the research team to securely store your contact details for the purpose of contacting you about future studies we are running, then please tick the box below. You will be free to decide whether or not you would like to take part in any other studies after full information and discussion.</p>'
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
            '<h4>General support for parents of babies requiring neonatal care</h4>'
            . '<ul><li>Bliss: <a href="https://www.bliss.org.uk/" target="_blank" rel="noopener">bliss.org.uk</a></li>'
            . '<li>Contact: <a href="https://contact.org.uk/" target="_blank" rel="noopener">contact.org.uk</a></li>'
            . '<li>Mind: <a href="https://www.mind.org.uk/" target="_blank" rel="noopener">mind.org.uk</a></li>'
            . '<li>UNICEF UK Baby Friendly: <a href="https://www.unicef.org.uk/babyfriendly/" target="_blank" rel="noopener">unicef.org.uk/babyfriendly</a></li></ul>'
            . '<h4>Bereavement support</h4>'
            . '<ul><li>Sands: <a href="https://www.sands.org.uk/" target="_blank" rel="noopener">sands.org.uk</a></li>'
            . '<li>Child Bereavement UK: <a href="https://www.childbereavementuk.org/" target="_blank" rel="noopener">childbereavementuk.org</a></li>'
            . '<li>Aching Arms: <a href="https://www.achingarms.co.uk/" target="_blank" rel="noopener">achingarms.co.uk</a></li></ul>'
            . '<p>If you need help urgently, please contact your GP, call 111, or go to A&amp;E.</p>'
        );

        return $fields;
    }

    public static function exportJson(): string
    {
        return json_encode([
            'format' => QuestionImportExportService::FORMAT,
            'version' => QuestionImportExportService::VERSION,
            'kind' => 'survey',
            'title' => self::FORM_TITLE,
            'fields' => self::fields(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function rich(string $key, string $label, string $html, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
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
            'type' => FormField::TYPE_RADIO,
            'label' => $label,
            'options' => self::codedOptions($key, $options),
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function dropdown(string $key, string $label, array $options, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_DROPDOWN,
            'label' => $label,
            'options' => self::codedOptions($key, $options),
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function checkbox(string $key, string $label, array $options, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_CHECKBOX,
            'label' => $label,
            'options' => self::codedOptions($key, $options),
            'required' => $required ? '1' : '',
        ], $extra);
    }

    /**
     * Prefix every stored choice with a stable question-specific code while
     * keeping the participant-facing label unchanged.
     *
     * @param string[] $labels
     * @return string[]
     */
    private static function codedOptions(string $key, array $labels): array
    {
        $out = [];
        foreach (array_values($labels) as $index => $label) {
            $out[] = sprintf('%s_%02d | %s', $key, $index + 1, $label);
        }
        return $out;
    }

    /**
     * Grid rows/columns are stored as plain strings (no code|label split).
     * Prepend a bracketed code so exports carry a stable identifier while
     * respondents see it as a short prefix: "[b5_r_01] First few weeks at home".
     */
    private static function codedGridItems(string $prefix, array $labels): array
    {
        $out = [];
        foreach (array_values($labels) as $index => $label) {
            $out[] = sprintf('[%s_%02d] %s', $prefix, $index + 1, $label);
        }
        return $out;
    }

    private static function text(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_TEXT,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function email(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_EMAIL,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function number(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_NUMBER,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function textarea(string $key, string $label, bool $required, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_TEXTAREA,
            'label' => $label,
            'required' => $required ? '1' : '',
        ], $extra);
    }

    private static function grid(string $key, string $label, array $rows, array $columns, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => FormField::TYPE_GRID_SINGLE,
            'label' => $label,
            'grid_rows' => self::codedGridItems($key . '_r', $rows),
            'grid_columns' => self::codedGridItems($key . '_c', $columns),
            'required' => '',
        ], $extra);
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function showIfAny(array $pairs): array
    {
        $rules = [];
        foreach ($pairs as [$field, $value]) {
            $rules[] = ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value];
        }
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_combinator' => 'or',
            'logic_rules' => $rules,
        ];
    }

    private static function showIf(string $field, string $value, string $operator = FormField::OP_EQUALS): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_SHOW,
            'logic_combinator' => 'and',
            'logic_rules' => [
                ['fieldKey' => $field, 'operator' => $operator, 'value' => $value],
            ],
        ];
    }

    private static function hideIf(string $field, string $value): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_HIDE,
            'logic_combinator' => 'and',
            'logic_rules' => [
                ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value],
            ],
        ];
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function gotoEndIfAny(array $pairs): array
    {
        $rules = [];
        foreach ($pairs as [$field, $value]) {
            $rules[] = ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value];
        }
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_END,
            'logic_combinator' => 'or',
            'logic_rules' => $rules,
        ];
    }

    private static function gotoEndIf(string $field, string $value): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_END,
            'logic_combinator' => 'and',
            'logic_rules' => [
                ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value],
            ],
        ];
    }

    private static function showIfAdditionalNeeds(): array
    {
        $some = 'Some additional needs, for example with mobility, vision, or learning, managed by occasional extra support (e.g., therapy appointments, specialist equipment, or a support plan at nursery/school)';
        $complex = 'Complex additional needs across several areas of care requiring ongoing or daily support (for example combined physical, medical, and developmental needs, multiple specialists involved, or a formal care/educational plan)';

        return self::showIfAny([
            ['a7_needs', $some],
            ['a7_needs', $complex],
        ]);
    }

    /** @param list<array{0:string,1:string}> $pairs */
    private static function gotoPageIfAny(string $pageKey, array $pairs): array
    {
        $rules = [];
        foreach ($pairs as [$field, $value]) {
            $rules[] = ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value];
        }
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_PAGE,
            'logic_goto' => $pageKey,
            'logic_combinator' => 'or',
            'logic_rules' => $rules,
        ];
    }

    private static function gotoPageIf(string $pageKey, string $field, string $value): array
    {
        return [
            'logic_action' => LogicEngine::ACTION_GOTO_PAGE,
            'logic_goto' => $pageKey,
            'logic_combinator' => 'and',
            'logic_rules' => [
                ['fieldKey' => $field, 'operator' => FormField::OP_EQUALS, 'value' => $value],
            ],
        ];
    }
}
