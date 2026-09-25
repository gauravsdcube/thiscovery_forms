<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use Yii;
use yii\base\Exception;

/**
 * Extract plain text from DOCX or text-based PDF uploads.
 */
class DocumentTextExtractor
{
    /**
     * @return array{text: string, source: string}
     * @throws Exception
     */
    public function extractFromUpload(string $tempPath, string $originalName, string $extension): array
    {
        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === '' && $originalName !== '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }

        if ($ext === 'docx') {
            $text = $this->fromDocx($tempPath);
            $source = 'docx';
        } elseif ($ext === 'pdf') {
            $text = $this->fromPdf($tempPath);
            $source = 'pdf';
        } elseif (in_array($ext, ['txt', 'md', 'text'], true)) {
            $text = (string)@file_get_contents($tempPath);
            $source = 'text';
        } else {
            throw new Exception(Yii::t(
                'ThiscoveryFormsModule.base',
                'Unsupported file type. Upload a Word (.docx), PDF, or plain text file.'
            ));
        }

        $text = $this->normalizeText($text);
        if (trim($text) === '') {
            throw new Exception(Yii::t(
                'ThiscoveryFormsModule.base',
                'No extractable text was found. Scanned image PDFs are not supported yet — paste the brief or use a text-based file.'
            ));
        }

        return ['text' => $text, 'source' => $source];
    }

    public function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    }

    protected function fromDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'ZIP support is required to read Word files.'));
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'Could not open the Word file.'));
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'The Word file has no document content.'));
        }
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:tab[^\/]*\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br[^\/]*\/>/', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function fromPdf(string $path): string
    {
        $raw = (string)@file_get_contents($path);
        if ($raw === '') {
            return '';
        }

        $chunks = [];
        if (preg_match_all('/stream\s*\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded === false) {
                    $decoded = $stream;
                }
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', (string)$decoded, $literals)) {
                    foreach ($literals[0] as $lit) {
                        $s = substr($lit, 1, -1);
                        $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $s);
                        $s = preg_replace('/\\\\[0-7]{1,3}/', '', $s) ?? $s;
                        if (preg_match('/[A-Za-z0-9]{2,}/', $s)) {
                            $chunks[] = $s;
                        }
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', (string)$decoded, $tj)) {
                    foreach ($tj[1] as $arr) {
                        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $arr, $parts)) {
                            $line = '';
                            foreach ($parts[0] as $lit) {
                                $s = substr($lit, 1, -1);
                                $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $s);
                                $line .= $s;
                            }
                            if (trim($line) !== '') {
                                $chunks[] = $line;
                            }
                        }
                    }
                }
            }
        }

        return implode("\n", $chunks);
    }
}
