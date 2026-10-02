<?php

namespace humhub\modules\thiscoveryForms\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;

/**
 * Compares one completed response with the rest of its form in the background, so the
 * submit request does not, and records both sides of each similar pair (INT-3).
 */
class SimilarityScanJob extends ActiveJob
{
    public int $formId = 0;
    public int $answerId = 0;

    public function run()
    {
        if ($this->formId > 0 && $this->answerId > 0) {
            (new IntegrityService())->rescanSimilarity($this->formId, $this->answerId);
        }
    }
}
