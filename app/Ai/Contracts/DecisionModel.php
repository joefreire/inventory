<?php

namespace App\Ai\Contracts;

use App\Ai\Exceptions\AiException;

/**
 * A decision model: judges a state against closed questions and returns probabilities
 * instead of text (choice, yes/no "noul" and score questions).
 */
interface DecisionModel
{
    /**
     * @param  string|array<string, mixed>  $state  what is being judged
     * @param  array<string, array<string, mixed>>  $questions  id => {type, instructions, criteria}
     * @param  list<string>  $images  base64 images shared by every question
     * @return array<string, array<string, mixed>> id => answer
     *
     * @throws AiException
     */
    public function decide(string|array $state, array $questions, array $images = []): array;

    /** Name shown to the user ("modelos usados"). */
    public function name(): string;

    /** Most options one choice question may have (the model's own limit). */
    public function maxChoiceOptions(): int;
}
