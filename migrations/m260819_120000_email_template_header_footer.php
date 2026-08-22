<?php

use humhub\components\Migration;

class m260819_120000_email_template_header_footer extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('form_email_template', 'header_html', $this->text()->null()->after('subject'));
        $this->safeAddColumn('form_email_template', 'footer_html', $this->text()->null()->after('body_html'));
        $this->safeAddColumn('form_email_template', 'header_bg_color', $this->string(7)->null()->after('footer_html'));
        $this->safeAddColumn('form_email_template', 'header_font_color', $this->string(7)->null()->after('header_bg_color'));
        $this->safeAddColumn('form_email_template', 'footer_bg_color', $this->string(7)->null()->after('header_font_color'));
        $this->safeAddColumn('form_email_template', 'footer_font_color', $this->string(7)->null()->after('footer_bg_color'));

        $this->update('form_email_template', [
            'header_bg_color' => '#f0f4f8',
            'header_font_color' => '#1f2937',
            'footer_bg_color' => '#f8f9fa',
            'footer_font_color' => '#6b7280',
        ], [
            'or',
            ['header_bg_color' => null],
            ['header_bg_color' => ''],
        ]);
    }

    public function safeDown()
    {
        $this->safeDropColumn('form_email_template', 'footer_font_color');
        $this->safeDropColumn('form_email_template', 'footer_bg_color');
        $this->safeDropColumn('form_email_template', 'header_font_color');
        $this->safeDropColumn('form_email_template', 'header_bg_color');
        $this->safeDropColumn('form_email_template', 'footer_html');
        $this->safeDropColumn('form_email_template', 'header_html');
    }
}
