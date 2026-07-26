<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class DoctorCheck
{
    public function __construct(
        string $id,
        public readonly Outcome $outcome,
        string $uri,
        string $expected,
        string $observed,
        string $failureCode = '',
        string $likelyLayer = 'public_delivery_path',
        string $attribution = 'observed',
        string $recipeId = '',
        string $rerunAction = 'Run Deployment Doctor again.',
        public readonly bool $mandatory = true
    ) {
        $this->id = BoundedText::identifier($id);
        $this->uri = BoundedText::utf8($uri, 2048);
        $this->expected = BoundedText::utf8($expected, 4096);
        $this->observed = BoundedText::utf8($observed, 4096);
        $this->failureCode = BoundedText::identifier($failureCode);
        $this->likelyLayer = BoundedText::identifier($likelyLayer);
        $this->attribution = in_array($attribution, ['observed', 'inference'], true)
            ? $attribution
            : 'inference';
        $this->recipeId = BoundedText::identifier($recipeId);
        $this->rerunAction = BoundedText::utf8($rerunAction, 4096);
    }

    public readonly string $id;
    public readonly string $uri;
    public readonly string $expected;
    public readonly string $observed;
    public readonly string $failureCode;
    public readonly string $likelyLayer;
    public readonly string $attribution;
    public readonly string $recipeId;
    public readonly string $rerunAction;

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'outcome' => $this->outcome->value,
            'mandatory' => $this->mandatory,
            'uri' => $this->uri,
            'expected' => $this->expected,
            'observed' => $this->observed,
            'failure_code' => $this->failureCode,
            'likely_layer' => $this->likelyLayer,
            'attribution' => $this->attribution,
            'recipe_id' => $this->recipeId,
            'rerun_action' => $this->rerunAction,
        ];
    }
}
