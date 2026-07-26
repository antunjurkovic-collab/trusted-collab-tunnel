<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class LayerResult
{
    public function __construct(
        public readonly Outcome $outcome,
        string $detail
    ) {
        $this->detail = BoundedText::utf8($detail, 4096);
    }

    public readonly string $detail;

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'detail' => $this->detail,
        ];
    }
}
