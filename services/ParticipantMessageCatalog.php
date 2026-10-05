<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * Fixed sentences a participant sees on every form.
 * The English source is the Yii message id, so a fill request can translate it in place.
 */
class ParticipantMessageCatalog
{
    /**
     * @return array<string, array{group: string, source: string}>
     */
    public static function entries(): array
    {
        static $entries = null;
        if ($entries !== null) {
            return $entries;
        }
        $entries = [];
        foreach (self::definitions() as $key => $pair) {
            $entries[$key] = ['group' => $pair[0], 'source' => $pair[1]];
        }
        return $entries;
    }

    public static function keyFor(string $source): ?string
    {
        static $bySource = null;
        if ($bySource === null) {
            $bySource = [];
            foreach (self::entries() as $key => $entry) {
                $bySource[$entry['source']] = $key;
            }
        }
        return $bySource[$source] ?? null;
    }

    /**
     * @return array<string, array<string, array{group: string, source: string}>>
     */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::entries() as $key => $entry) {
            $out[$entry['group']][$key] = $entry;
        }
        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'navigation' => 'Navigation and saving',
            'validation' => 'Checks and limits',
            'questions' => 'Question controls',
            'endings' => 'Captcha, gates, and endings',
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private static function definitions(): array
    {
        return [
            'next' => ['navigation', 'Next'],
            'back' => ['navigation', 'Back'],
            'submit' => ['navigation', 'Submit'],
            'finish' => ['navigation', 'Finish'],
            'update_submission' => ['navigation', 'Update submission'],
            'submit_review' => ['navigation', 'Submit for review'],
            'continue' => ['navigation', 'Continue'],
            'cancel' => ['navigation', 'Cancel'],
            'page_of' => ['navigation', 'Page {current} of {total}'],
            'page_n_of' => ['navigation', 'Page {n} of {count}'],
            'please_select' => ['navigation', 'Please select'],
            'select_prompt' => ['navigation', 'Select…'],
            'your_answer' => ['navigation', 'Your answer'],
            'please_specify' => ['navigation', 'Please specify'],
            'type_answer' => ['navigation', 'Type your answer'],
            'save_later' => ['navigation', 'Save & continue later'],
            'save_progress' => ['navigation', 'Save progress'],
            'email_optional' => ['navigation', 'Email (optional)'],
            'email_me_code' => ['navigation', 'Email me the resume code'],
            'progress_saved' => ['navigation', 'Your progress is saved'],
            'resume_code_help' => ['navigation', 'Use this code to continue later. Anyone with the code can open your saved response.'],
            'copy_code' => ['navigation', 'Copy code'],
            'copied' => ['navigation', 'Copied'],
            'copy_failed' => ['navigation', 'Could not copy'],
            'send_email' => ['navigation', 'Send email'],
            'email_placeholder' => ['navigation', 'you@example.com'],
            'how_continue' => ['navigation', 'How would you like to continue?'],
            'resume_saved' => ['navigation', 'Resume a saved response'],
            'enter_code' => ['navigation', 'Enter the code you were given when you saved your progress.'],
            'continue_saved' => ['navigation', 'Continue your saved response'],
            'resume_code' => ['navigation', 'Resume code'],
            'submit_new' => ['navigation', 'Submit a new response'],
            'start_beginning' => ['navigation', 'Start this form from the beginning.'],
            'start_new' => ['navigation', 'Start a new response'],
            'continuing_code' => ['navigation', 'Continuing saved response {code}'],
            'add_another' => ['navigation', 'Add another'],
            'remove_row' => ['navigation', 'Remove {label} {index}'],
            'remove_row_confirm' => ['navigation', 'Remove {label} {index}? These answers are kept but not shown.'],
            'row' => ['navigation', 'Row'],
            'loop_heading' => ['navigation', '{label}, {index} of {count}'],
            'save_progress_failed' => ['navigation', 'Could not save your progress. Please try again.'],
            'no_saved' => ['navigation', 'No saved response found for that code.'],
            'progress_email_failed' => ['navigation', 'Progress was saved, but the email could not be sent. Please copy your code below.'],
            'progress_emailed' => ['navigation', 'Progress saved and resume code emailed to {email}.'],
            'progress_saved_copy' => ['navigation', 'Progress saved. Copy your resume code to continue later.'],
            'email_failed' => ['navigation', 'Could not send the email. Check the address and try again.'],
            'resume_emailed' => ['navigation', 'Resume code emailed to {email}.'],
            'already_response' => ['navigation', 'This response has already been submitted.'],
            'save_later_help' => ['navigation', 'We will save your answers and give you a resume code. Optionally email the code to yourself.'],
            'fields_completed' => ['navigation', 'fields completed'],
            'language' => ['navigation', 'Language'],
            'company_website' => ['navigation', 'Company website'],
            'previous_answer' => ['navigation', 'Your previous answer'],
            'revise_round' => ['navigation', 'You can revise it in this round.'],
            'edit_submission' => ['navigation', 'Edit your submission'],

            'required_mark' => ['validation', 'Required'],
            'required_field' => ['validation', 'This question is required.'],
            'required_label' => ['validation', '"{label}" is required.'],
            'required_repeat' => ['validation', '"{label}" is required for {repeat}.'],
            'required_page' => ['validation', 'Please complete the required fields on this page.'],
            'fix_page' => ['validation', 'Please fix the highlighted questions, then try again.'],
            'checkbox_min' => ['validation', 'Select the required number of options.'],
            'select_at_least' => ['validation', 'Select at least {min} options.'],
            'select_every' => ['validation', 'Select every option.'],
            'checkbox_max' => ['validation', 'Select at most {max} options.'],
            'requires_min' => ['validation', '"{label}" requires at least {min} selections.'],
            'requires_every' => ['validation', '"{label}" requires every option to be selected.'],
            'allows_max' => ['validation', '"{label}" allows at most {max} selections.'],
            'exclusive_option' => ['validation', '"{label}" cannot combine "{option}" with other choices.'],
            'number_min' => ['validation', 'Enter a number of at least {min}.'],
            'number_max' => ['validation', 'Enter a number of at most {max}.'],
            'must_number' => ['validation', '"{label}" must be a number.'],
            'label_min' => ['validation', '"{label}" must be at least {min}.'],
            'label_max' => ['validation', '"{label}" must be at most {max}.'],
            'between' => ['validation', '"{label}" must be between {min} and {max}.'],
            'rating_value' => ['validation', '"{label}" must be a valid rating value.'],
            'scale_step' => ['validation', '"{label}" must follow the configured scale step.'],
            'text_min' => ['validation', 'Enter at least {n} characters.'],
            'label_text_min' => ['validation', '"{label}" must be at least {n} characters.'],
            'label_text_max' => ['validation', '"{label}" can be at most {n} characters.'],
            'bad_format' => ['validation', 'This answer is not in the expected format.'],
            'label_format' => ['validation', '"{label}" is not in the expected format.'],
            'bad_email' => ['validation', 'Enter a valid email address, for example name@example.com.'],
            'label_email' => ['validation', '"{label}" must be a valid email.'],
            'date_min' => ['validation', 'Enter a date on or after {date}.'],
            'date_max' => ['validation', 'Enter a date on or before {date}.'],
            'must_date' => ['validation', '"{label}" must be a date.'],
            'label_date_min' => ['validation', '"{label}" must be on or after {date}.'],
            'label_date_max' => ['validation', '"{label}" must be on or before {date}.'],
            'answer_check' => ['validation', 'This answer does not fit with your other answers.'],
            'label_check' => ['validation', '"{label}" does not fit with your other answers.'],
            'specify_label' => ['validation', 'Please specify your answer for "{label}".'],
            'invalid_option' => ['validation', '"{label}" has an invalid option.'],
            'invalid_value' => ['validation', '"{label}" has an invalid value.'],
            'duplicate_rank' => ['validation', '"{label}" has duplicate ranked items.'],
            'add_comment' => ['validation', 'Please add a comment for "{label}".'],
            'required_rows' => ['validation', 'Add the required rows before submitting.'],
            'edition' => ['validation', 'This form edition could not be confirmed. Please reload the page and try again.'],
            'change_reason' => ['validation', 'Give a reason for changing this response.'],
            'change_reason_label' => ['validation', 'Reason for changing this response'],
            'change_reason_help' => ['validation', 'Kept in the response\'s change history with your name.'],
            'already_submitted_error' => ['validation', 'You have already submitted this form.'],
            'save_failed' => ['validation', 'Could not save your submission. Please try again.'],
            'save_field_failed' => ['validation', 'Could not save field "{label}".'],
            'invalid_request' => ['validation', 'Invalid request.'],
            'too_many_requests' => ['validation', 'Too many requests. Please wait and try again.'],

            'rank_help' => ['questions', 'Drag the handle or use the arrows to rank (1 = highest preference).'],
            'drag_reorder' => ['questions', 'Drag to reorder'],
            'move_up' => ['questions', 'Move up'],
            'move_down' => ['questions', 'Move down'],
            'move_item_up' => ['questions', 'Move {item} up'],
            'move_item_down' => ['questions', 'Move {item} down'],
            'rank_handle' => ['questions', '{item}, position {n} of {count}. Use the up and down arrow keys to move it.'],
            'rank_moved' => ['questions', '{item} moved to position {n} of {count}.'],
            'score' => ['questions', 'Score'],
            'vas_value' => ['questions', '{n} out of {max}'],
            'vas_empty' => ['questions', 'No answer yet'],
            'drill_level' => ['questions', '{question}, level {n}'],
            'best' => ['questions', 'Best'],
            'worst' => ['questions', 'Worst'],
            'item' => ['questions', 'Item'],
            'best_item' => ['questions', 'Best: {item}'],
            'worst_item' => ['questions', 'Worst: {item}'],
            'set_n' => ['questions', 'Set {n} of {count}'],
            'scroll_options' => ['questions', 'Scroll sideways to see all options'],
            'grid_label' => ['questions', 'Answer grid. Scroll sideways to see all options.'],
            'upload' => ['questions', 'Upload file'],
            'upload_help' => ['questions', 'Allowed: {types}. Up to {mb} MB.'],
            'upload_failed' => ['questions', 'Could not upload the file.'],
            'why_choose' => ['questions', 'Why did you choose this?'],
            'optional' => ['questions', 'optional'],
            'consensus_frozen' => ['questions', 'This item reached consensus and cannot be changed.'],
            'consent_end' => ['questions', 'End of the information sheet'],
            'consent_confirm' => ['questions', 'I confirm this is my decision.'],
            'type_name' => ['questions', 'Type your name'],
            'draw_signature' => ['questions', 'Draw a signature, or type your name instead'],
            'clear_signature' => ['questions', 'Clear signature'],
            'witness_name' => ['questions', 'Witness name'],
            'witness_role' => ['questions', 'Witness role'],
            'map_missing' => ['questions', 'Thiscovery Mapping must be installed and enabled to answer map questions.'],

            'verify_human' => ['endings', 'Please verify you are human'],
            'captcha_hint' => ['endings', 'Complete the verification check to submit.'],
            'captcha_retry' => ['endings', 'Please complete the verification check and try again.'],
            'too_many_submissions' => ['endings', 'Too many submissions from this connection. Please wait a few minutes and try again.'],
            'unique_link' => ['endings', 'This survey needs a unique invitation link.'],
            'sign_in' => ['endings', 'You need to be signed in to take this survey.'],
            'not_allowed' => ['endings', 'You are not allowed to submit this form.'],
            'closed' => ['endings', 'This form is closed and no longer accepts submissions.'],
            'draft' => ['endings', 'This form is still a draft.'],
            'no_wave' => ['endings', 'There is no open wave for this survey yet.'],
            'no_round' => ['endings', 'There is no open round for this survey yet.'],
            'need_invite' => ['endings', 'You need a panel invitation to take part in this survey.'],
            'thank_you' => ['endings', 'Thank you!'],
            'project_review' => ['endings', 'Your project has been submitted for review.'],
            'submission_saved' => ['endings', 'Your submission has been saved.'],
            'group_full_heading' => ['endings', 'This group is full'],
            'group_full' => ['endings', 'This group is full.'],
            'withdrawal_code' => ['endings', 'Keep this withdrawal code. It is shown once: {code}'],
            'already_default' => ['endings', 'You have already submitted {formName}. Multiple submissions are not allowed'],
            'back_to_form' => ['endings', 'Back to form'],
            'view_project' => ['endings', 'View your project'],
        ];
    }
}
