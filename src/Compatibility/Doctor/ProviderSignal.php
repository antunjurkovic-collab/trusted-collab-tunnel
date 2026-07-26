<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class ProviderSignal
{
    public function __construct(
        string $id,
        string $value,
        string $source
    ) {
        $this->id = BoundedText::identifier($id);
        $this->value = BoundedText::utf8($value, 256);
        $this->source = BoundedText::identifier($source);
    }

    public readonly string $id;
    public readonly string $value;
    public readonly string $source;

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'source' => $this->source,
            'effect' => 'diagnostic_only',
        ];
    }
}
