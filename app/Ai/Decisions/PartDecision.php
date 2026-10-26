<?php

namespace App\Ai\Decisions;

use App\Ai\Identification\PartDescription;

/**
 * One closed question (or group) asked to the decision model about a part.
 *
 * Every decision of an analysis goes in the same request, so adding a decision
 * costs no extra round trip: write a class and list it in config/ai.php
 * ('decisions'). Instances are per analysis: questions() may keep state that
 * interpret() needs.
 */
interface PartDecision
{
    /** Key of the result in the analysis response (e.g. "category"). */
    public function key(): string;

    /**
     * @return array<string, array<string, mixed>> question id => {type, instructions, criteria}
     */
    public function questions(PartDescription $part): array;

    /**
     * @param  array<string, array<string, mixed>>  $answers  every answer of the request
     */
    public function interpret(array $answers): mixed;
}
